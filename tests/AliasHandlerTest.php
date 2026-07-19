<?php

class AliasHandlerTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        // Fake being an admin.
        $_SESSION = [
            'sessid' => [
                'roles' => ['global-admin']
            ]
        ];
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        db_query('DELETE FROM alias');
        db_query('DELETE FROM mailbox');
        db_query('DELETE FROM domain_admins');
        db_query('DELETE FROM domain');

        parent::tearDown();
    }

    public function testBasic()
    {
        $x = new AliasHandler();
        $list = $x->getList("");
        $this->assertTrue($list);
        $results = $x->result();
        $this->assertEmpty($results);
    }

    public function testUserCanOnlyEditOwnMailboxForwarding()
    {
        $this->addDomain('example.com', 'admin');
        $this->addMailboxAlias('alice@example.com', 'example.com');
        $this->addMailboxAlias('bob@example.com', 'example.com');

        $_SESSION = [
            'sessid' => [
                'roles' => ['user']
            ]
        ];

        $handler = new AliasHandler(0, 'alice@example.com', false);
        $formconf = $handler->webformConfig();

        $this->assertEquals('address', $formconf['user_hardcoded_field']);
        $this->assertEquals('users/main.php', $formconf['listview']);
        $this->assertFalse($formconf['user_can_list']);
        $this->assertFalse($formconf['user_can_delete']);
        $this->assertTrue($handler->init('alice@example.com'));

        $struct = $handler->getStruct();
        $this->assertSame(1, $struct['goto']['display_in_form']);
        $this->assertSame(1, $struct['goto_mailbox']['display_in_form']);
        $this->assertSame(0, $struct['description']['display_in_form']);
        $this->assertSame(0, $struct['active']['display_in_form']);

        $this->assertTrue($handler->set([
            'address' => 'bob@example.com',
            'description' => 'changed by user',
            'goto' => ['forward@example.net'],
            'goto_mailbox' => 1,
            'active' => 0,
        ]));
        $this->assertTrue($handler->save(), json_encode($handler->errormsg));

        $alice = new AliasHandler();
        $this->assertTrue($alice->init('alice@example.com'));
        $result = $alice->result();
        $this->assertSame(['forward@example.net'], $result['goto']);
        $this->assertSame(1, $result['goto_mailbox']);
        $this->assertSame('Mailbox alias', $result['description']);
        $this->assertEquals(1, $result['active']);

        $other = new AliasHandler(0, 'alice@example.com', false);
        $this->assertFalse($other->init('bob@example.com'));
    }

    public function testUserAliasCreationIsDisabled()
    {
        $_SESSION = [
            'sessid' => [
                'roles' => ['user']
            ]
        ];

        $handler = new AliasHandler(1, 'alice@example.com', false);
        $formconf = $handler->webformConfig();

        $this->assertFalse($formconf['user_can_create']);
    }

    public function testUserForwardingPreservesVacationAndLocalDelivery()
    {
        $this->addDomain('example.com', 'admin');
        $this->addMailboxAlias('alice@example.com', 'example.com');

        $vacation = 'alice#example.com@' . Config::read_string('vacation_domain');
        db_update(
            'alias',
            'address',
            'alice@example.com',
            ['goto' => implode(',', [
                'alice@example.com',
                $vacation,
                'old-forward@example.net',
            ])]
        );

        $_SESSION = [
            'sessid' => [
                'roles' => ['user']
            ]
        ];

        $handler = new AliasHandler(0, 'alice@example.com', false);
        $this->assertTrue($handler->init('alice@example.com'));
        $this->assertTrue($handler->set([
            'goto' => ['new-forward@example.net'],
            'goto_mailbox' => 1,
        ]));
        $this->assertTrue($handler->save(), json_encode($handler->errormsg));

        $stored = new AliasHandler();
        $this->assertTrue($stored->init('alice@example.com'));
        $result = $stored->result();
        $this->assertSame(['new-forward@example.net'], $result['goto']);
        $this->assertSame(1, $result['goto_mailbox']);
        $this->assertSame(1, $result['on_vacation']);

        # Switching to forward-only removes local delivery but keeps vacation.
        $handler = new AliasHandler(0, 'alice@example.com', false);
        $this->assertTrue($handler->init('alice@example.com'));
        $this->assertTrue($handler->set([
            'goto' => ['forward-only@example.net'],
            'goto_mailbox' => 0,
        ]));
        $this->assertTrue($handler->save(), json_encode($handler->errormsg));

        $stored = new AliasHandler();
        $this->assertTrue($stored->init('alice@example.com'));
        $result = $stored->result();
        $this->assertSame(['forward-only@example.net'], $result['goto']);
        $this->assertSame(0, $result['goto_mailbox']);
        $this->assertSame(1, $result['on_vacation']);

        # Removing all external targets must still work when local delivery is enabled.
        $handler = new AliasHandler(0, 'alice@example.com', false);
        $this->assertTrue($handler->init('alice@example.com'));
        $this->assertTrue($handler->set([
            'goto' => [],
            'goto_mailbox' => 1,
        ]), json_encode($handler->errormsg));
        $this->assertTrue($handler->save(), json_encode($handler->errormsg));

        $stored = new AliasHandler();
        $this->assertTrue($stored->init('alice@example.com'));
        $result = $stored->result();
        $this->assertSame([], $result['goto']);
        $this->assertSame(1, $result['goto_mailbox']);
        $this->assertSame(1, $result['on_vacation']);
    }

    public function testUserForwardingRespectsEditAliasSetting()
    {
        $this->addDomain('example.com', 'admin');
        $this->addMailboxAlias('alice@example.com', 'example.com');

        $_SESSION = [
            'sessid' => [
                'roles' => ['user']
            ]
        ];

        $previous = Config::read('edit_alias');
        Config::write('edit_alias', 'NO');

        try {
            $handler = new AliasHandler(0, 'alice@example.com', false);
            $this->assertFalse($handler->init('alice@example.com'));
        } finally {
            Config::write('edit_alias', $previous);
        }
    }

    public function testUserCannotForgeVacationTargetAndDuplicatesAreRemoved()
    {
        $this->addDomain('example.com', 'admin');
        $this->addMailboxAlias('alice@example.com', 'example.com');

        $_SESSION = [
            'sessid' => [
                'roles' => ['user']
            ]
        ];

        $vacation = 'alice#example.com@' . Config::read_string('vacation_domain');
        $handler = new AliasHandler(0, 'alice@example.com', false);
        $this->assertTrue($handler->init('alice@example.com'));
        $this->assertTrue($handler->set([
            'goto' => [
                $vacation,
                $vacation,
                'forward@example.net',
                'forward@example.net',
            ],
            'goto_mailbox' => 1,
        ]));
        $this->assertTrue($handler->save(), json_encode($handler->errormsg));

        $stored = new AliasHandler();
        $this->assertTrue($stored->init('alice@example.com'));
        $result = $stored->result();
        $this->assertSame(['forward@example.net'], $result['goto']);
        $this->assertSame(1, $result['goto_mailbox']);
        $this->assertSame(0, $result['on_vacation']);
    }

    public function testDomainAdminForwardingRespectsAliasControlAdminSetting()
    {
        $this->addDomain('example.com', 'admin');
        $this->addMailboxAlias('alice@example.com', 'example.com');

        $_SESSION = [
            'sessid' => [
                'roles' => ['admin']
            ]
        ];

        $previous = Config::read('alias_control_admin');
        Config::write('alias_control_admin', 'NO');

        try {
            $handler = new AliasHandler(0, 'admin', true);
            $this->assertFalse($handler->init('alice@example.com'));
        } finally {
            Config::write('alias_control_admin', $previous);
        }
    }


    public function testCannotAddAliasUntilDomainIsThere()
    {

        // Fake us being an admin.

        $_SESSION = [
            'sessid' => [
                'roles' => ['global-admin']
            ]
        ];

        // Trying to add an alias when the domain doesn't exist fails.

        $x = new AliasHandler(1, 'admin', true);

        try {
            $r = $x->init('david.test@example.com');
            $this->fail("Should not see this - example.com is not present");
        } catch (\Exception $e) {
            $this->assertEquals('Error: This domain does not exist!', $e->getMessage());
        }
    }


    /**
     * @see https://github.com/postfixadmin/postfixadmin/pull/375 and https://github.com/postfixadmin/postfixadmin/issues/358
     */
    public function testCannotAddAliasThatPointsToItself()
    {
        // Fake being an admin.
        $_SESSION = [
            'sessid' => [
                'roles' => ['global-admin']
            ]
        ];
        // Add example.com
        $dh = new DomainHandler(1, 'admin', true);

        $dh->init('example.com');

        $ret = $dh->set(
            [
                'domain' => 'example.com',
                'description' => 'test domain',
                'aliases' => 11,
                'mailboxes' => 12,
                'active' => 1,
                'backupmx' => 0,
                'default_aliases' => 1
            ]
        );


        $this->assertEmpty($dh->errormsg);
        $this->assertEmpty($dh->infomsg);

        $this->assertTrue($ret);

        $ret = $dh->save();


        $this->assertTrue($ret);

        // Need to add 'admin' as a domain_admin
        db_insert('domain_admins', ['username' => 'admin', 'domain' => 'example.com', 'created' => '2020-01-01', 'active' => 1], ['created'], true);

        $dh = new DomainHandler(0, 'admin', true);
        $dh->getList('');
        $result = $dh->result();

        $this->assertEmpty($dh->infomsg);
        $this->assertEmpty($dh->errormsg);

        $this->assertNotEmpty($result);

        $this->assertEquals('example.com', $result['example.com']['domain']);
        $this->assertEquals('test domain', $result['example.com']['description']);

        $this->assertEquals(11, $result['example.com']['aliases']);
        $this->assertEquals(12, $result['example.com']['mailboxes']); // default aliases.

        $this->assertEquals(4, $result['example.com']['alias_count']); // default aliases.
        $this->assertEquals(0, $result['example.com']['mailbox_count']);
        $this->assertEquals(1, $result['example.com']['active']);

        $x = new AliasHandler(1, 'admin', true);

        $values = [
            'localpart' => 'david.test',
            'domain' => 'example.com',
            'active' => 1,
            'address' => 'david.test@example.com',
            'goto' => ['david.test@example.com']
        ];

        $r = $x->init('david.test@example.com');
        $this->assertTrue($r);
        $x->getList('');
        $list = $x->result();
        $this->assertEquals(4, count($list)); // default aliases.

        $x->set($values);
        $x->save();

        $this->assertNotEmpty($x->errormsg);
        $this->assertEquals(
            [
                'goto' => "david.test@example.com: Forward may not point to itself",
                0 => "one or more values are invalid!"
            ], $x->errormsg);
    }

    public function testAddingDataEtc()
    {

        // Fake being an admin.
        $_SESSION = [
            'sessid' => [
                'roles' => ['global-admin']
            ]
        ];

        $this->addDomain('example.com', 'admin');


        $x = new AliasHandler(1, 'admin', true);

        $values = [
            'localpart' => 'david.test',
            'domain' => 'example.com',
            'active' => 1,
            'address' => 'david.test@example.com',
            'goto' => ['dest@example.com'],
            'description' => 'A reason this exists.'
        ];

        $r = $x->init('david.test@example.com');
        $this->assertTrue($r);
        $x->getList('');
        $list = $x->result();
        $this->assertEquals(4, count($list)); // default aliases.

        $x->set($values);
        $x->save();

        $x->getList('');

        $list = $x->result();
        $this->assertEquals(5, count($list));

        $found = false;

        foreach ($list as $alias => $details) {
            if ($alias == 'david.test@example.com') {
                // Found!
                $this->assertEquals('example.com', $details['domain']);
                $this->assertEquals('david.test@example.com', $details['address']);
                $this->assertEquals(['dest@example.com'], $details['goto']);
                $this->assertEquals('A reason this exists.', $details['description']);
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, "check output : " . json_encode($list));
    }


    private function addDomain(string $domain, string $username): void
    {
        // Fake being an admin.
        $_SESSION = [
            'sessid' => [
                'roles' => ['global-admin']
            ]
        ];
        // Add example.com
        $dh = new DomainHandler(1, $username, true);

        $dh->init('example.com');

        $ret = $dh->set(
            [
                'domain' => $domain,
                'description' => 'test domain',
                'aliases' => 11,
                'mailboxes' => 12,
                'active' => 1,
                'backupmx' => 0,
                'default_aliases' => 1
            ]
        );


        $this->assertEmpty($dh->errormsg);
        $this->assertEmpty($dh->infomsg);
        $this->assertTrue($ret);
        $ret = $dh->save();
        $this->assertTrue($ret);

        // Need to add 'admin' as a domain_admin
        db_insert('domain_admins', ['username' => $username, 'domain' => $domain, 'created' => '2020-01-01', 'active' => 1], ['created'], true);


        $dh = new DomainHandler(0, $username, true);
        $dh->getList('');
        $result = $dh->result();
        $this->assertEmpty($dh->infomsg);
        $this->assertEmpty($dh->errormsg);

        $this->assertNotEmpty($result);

        $expected = [
            'domain' => 'example.com',
            'description' => 'test domain',
            'aliases' => 11,
            'alias_count' => 4,
            'mailboxes' => 12,
            'mailbox_count' => 0,
            'backupmx' => 0,
            'active' => 1,
        ];

        foreach ($expected as $k => $v) {
            $this->assertEquals($v, $result[$domain][$k]);
        }
    }

    private function addMailboxAlias(string $username, string $domain): void
    {
        list($localPart) = explode('@', $username);

        db_insert('mailbox', [
            'username' => $username,
            'password' => 'test',
            'name' => 'Forward Test',
            'maildir' => $domain . '/' . $localPart . '/',
            'local_part' => $localPart,
            'domain' => $domain,
            'active' => 1,
        ]);

        db_insert('alias', [
            'address' => $username,
            'goto' => $username,
            'domain' => $domain,
            'description' => 'Mailbox alias',
            'active' => 1,
        ]);
    }

    public function testYouCannotAddMoreAliasesThanTheDomainLimit()
    {
        $this->addDomain('example.com', 'admin');

        // default limit is 11 aliases.... so it should exit once we get past that.


        $dh = new DomainHandler(0, 'admin', true);
        $this->assertTrue($dh->getList(''));
        $result = $dh->result();

        // alias count limit is 11.
        $this->assertEquals(11, $result['example.com']['aliases']);

        // 4 default aliases were added.
        $this->assertEquals(4, $result['example.com']['alias_count']);


        foreach (range(1, 7) as $char) {
            $newAlias = $char . '-test@example.com';

            $x = new AliasHandler(1, 'admin', true);
            $values = [
                'localpart' => explode('@', $newAlias)[0],
                'domain' => 'example.com',
                'active' => 1,
                'address' => $newAlias,
                'goto' => ['dest@example.com']
            ];

            $r = $x->init($newAlias);

            $this->assertTrue($r);

            $x->set($values);
            $this->assertTrue($x->save());
            $this->assertTrue($x->getList(''));
            $list = $x->result();
            $this->assertArrayHasKey($newAlias, $list);
            $this->assertEquals(1, $list[$newAlias]['active']);
        }

        // try and add one more - it should fail.
        $x = new AliasHandler(1, 'admin', true);
        $values = [
            'localpart' => 'z-david.test',
            'domain' => 'example.com',
            'active' => 1,
            'address' => 'z-david.test@example.com',
            'goto' => ['dest@example.com']
        ];

        $r = $x->init('z-david.test@example.com');

        // doesn't already exist.
        $this->assertFalse($r);

        // try saving ....
        $x->set($values);
        $this->assertFalse($x->save());

        $this->assertEquals([
            'address' => "You have reached your limit to create aliases!",
            0 => "one or more values are invalid!"
        ], $x->errormsg);
    }


    public function testLoadsOfAliasesGetHandledByPager()
    {
        $this->addDomain('example.com', 'admin');

        // default limit is 11 aliases.... so it should exit once we get past that.

        $dh = new DomainHandler(0, 'admin', true);
        $dh->init('example.com');

        $this->assertTrue($dh->set(
            [
                //'domain' => 'example.com',
                'aliases' => 99,
                'mailboxes' => 88,
                'backupmx' => 0,
                'active' => 1,
            ]
        ));

        $this->assertTrue($dh->save());

        $dh->getList('');

        $domain = $dh->result()['example.com'];

        $this->assertEquals(99, $domain['aliases']);
        $this->assertEquals(88, $domain['mailboxes']);

        foreach (range(1, 80) as $char) {
            $newAlias = $char . '-test@example.com';

            $x = new AliasHandler(1, 'admin', true);
            $values = [
                'localpart' => explode('@', $newAlias)[0],
                'domain' => 'example.com',
                'active' => 1,
                'address' => $newAlias,
                'goto' => ['dest@example.com']
            ];

            $r = $x->init($newAlias);

            $this->assertTrue($r);

            $x->set($values);
            $this->assertTrue($x->save());
            $this->assertTrue($x->getList(''));
            $list = $x->result();
            $this->assertArrayHasKey($newAlias, $list);
            $this->assertEquals(1, $list[$newAlias]['active']);
        }

        // try and add one more - it should fail.
        $x = new AliasHandler(0, 'admin', true);

        $x->getList('', [], 5, 20);
        $results = $x->result();

        $this->assertEquals(5, count($results));
        $this->assertTrue(isset($results['31-test@example.com']));
    }

    /**
     * The list-virtual.php active-status filter works by passing an 'active'
     * condition to AliasHandler::getList(). Verify that filters the list.
     * @see https://github.com/postfixadmin/postfixadmin/issues/1038
     */
    public function testActiveConditionFiltersAliasList()
    {
        $this->addDomain('example.com', 'admin');

        // Add one active and one inactive alias (on top of the 4 default
        // aliases, which are active).
        foreach (['act@example.com' => 1, 'inact@example.com' => 0] as $addr => $active) {
            $x = new AliasHandler(1, 'admin', true);
            $this->assertTrue($x->init($addr));
            $x->set([
                'localpart' => explode('@', $addr)[0],
                'domain'    => 'example.com',
                'active'    => $active,
                'address'   => $addr,
                'goto'      => ['dest@example.com'],
            ]);
            $this->assertTrue($x->save(), json_encode($x->errormsg));
        }

        // active-only: contains our active alias and the defaults, never the inactive one.
        $x = new AliasHandler(0, 'admin', true);
        $this->assertTrue($x->getList(['domain' => 'example.com', 'active' => 1]));
        $activeList = $x->result();
        $this->assertArrayHasKey('act@example.com', $activeList);
        $this->assertArrayNotHasKey('inact@example.com', $activeList);
        foreach ($activeList as $addr => $row) {
            $this->assertEquals(1, $row['active'], "$addr should be active");
        }

        // inactive-only: exactly our inactive alias.
        $x = new AliasHandler(0, 'admin', true);
        $this->assertTrue($x->getList(['domain' => 'example.com', 'active' => 0]));
        $inactiveList = $x->result();
        $this->assertEquals(['inact@example.com'], array_keys($inactiveList));
        $this->assertEquals(0, $inactiveList['inact@example.com']['active']);
    }


    /**
     * delete-inactive.php enumerates the inactive aliases and deletes each one
     * through its handler. Verify that removes only the inactive aliases and
     * leaves active ones untouched.
     * @see https://github.com/postfixadmin/postfixadmin/issues/1057
     */
    public function testDeletingInactiveAliasesLeavesActiveOnes()
    {
        $this->addDomain('example.com', 'admin');

        foreach (['keep@example.com' => 1, 'drop1@example.com' => 0, 'drop2@example.com' => 0] as $addr => $active) {
            $x = new AliasHandler(1, 'admin', true);
            $this->assertTrue($x->init($addr));
            $x->set([
                'localpart' => explode('@', $addr)[0],
                'domain'    => 'example.com',
                'active'    => $active,
                'address'   => $addr,
                'goto'      => ['dest@example.com'],
            ]);
            $this->assertTrue($x->save(), json_encode($x->errormsg));
        }

        // Enumerate + delete the inactive ones, exactly as delete-inactive.php does.
        $handler = new AliasHandler(0, 'admin', true);
        $this->assertTrue($handler->getList(['domain' => 'example.com', 'active' => 0]));
        $inactive = array_keys($handler->result());
        sort($inactive);
        $this->assertEquals(['drop1@example.com', 'drop2@example.com'], $inactive);

        foreach ($inactive as $addr) {
            $one = new AliasHandler(0, 'admin', true);
            $this->assertTrue($one->init($addr));
            $this->assertTrue($one->delete(), json_encode($one->errormsg));
        }

        // No inactive aliases remain.
        $handler = new AliasHandler(0, 'admin', true);
        $this->assertTrue($handler->getList(['domain' => 'example.com', 'active' => 0]));
        $this->assertEmpty($handler->result());

        // The active alias (and the active default aliases) are still there.
        $handler = new AliasHandler(0, 'admin', true);
        $this->assertTrue($handler->getList(['domain' => 'example.com', 'active' => 1]));
        $this->assertArrayHasKey('keep@example.com', $handler->result());
    }
}
