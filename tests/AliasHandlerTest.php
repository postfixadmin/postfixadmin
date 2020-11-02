<?php

class AliasHandlerTest extends \PHPUnit\Framework\TestCase {
    protected function setUp(): void {
        // Fake being an admin.
        $_SESSION = [
            'sessid' => [
                'roles' => ['global-admin']
            ]
        ];
        parent::setUp();
    }

    protected function tearDown(): void {
        $_SESSION = [];
        db_query('DELETE FROM alias');
        db_query('DELETE FROM domain_admins');
        db_query('DELETE FROM domain');

        parent::tearDown();
    }

    public function testBasic() {
        $x = new AliasHandler();
        $list = $x->getList("");
        $this->assertTrue($list);
        $results = $x->result();
        $this->assertEmpty($results);
    }


    public function testCannotAddAliasUntilDomainIsThere() {

        // Fake us being an admin.

        $_SESSION = [
            'sessid' => [
                'roles' => ['global-admin']
            ]
        ];

        // Trying to add an alias when the domain doesn't exist fails.

        $x = new AliasHandler(1, 'admin', true);

        $values = [
            'localpart' => 'david.test',
            'domain' => 'example.com',
            'active' => 1,
            'address' => 'david.test@example.com',
            'goto' => ['dest@example.com']
        ];

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
    public function testCannotAddAliasThatPointsToItself() {
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
                'goto' => "david.test@example.com: Alias may not point to itself",
                0 => "one or more values are invalid!"
            ], $x->errormsg);
    }

    public function testAddingDataEtc() {

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
            'goto' => ['dest@example.com']
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
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, "check output : " . json_encode($list));
    }


    private function addDomain(string $domain, string $username): void {
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

    public function testYouCannotAddMoreAliasesThanTheDomainLimit() {
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


    public function testLoadsOfAliasesGetHandledByPager() {
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
}
