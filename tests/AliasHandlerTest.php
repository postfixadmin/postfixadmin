<?php

class AliasHandlerTest extends \PHPUnit\Framework\TestCase {
    public function testBasic() {
        $x = new AliasHandler();
        $list = $x->getList("");
        $this->assertTrue($list);
        $results = $x->result();
        $this->assertEmpty($results);
    }

    public function tearDown() : void {
        $_SESSION = [];
        db_query('DELETE FROM alias');
        db_query('DELETE FROM domain');
        db_query('DELETE FROM domain_admins');

        parent::tearDown();
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


    public function testAddingDataEtc() {
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

        $ret = $dh->store();

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
            'goto' => ['dest@example.com']
        ];

        $r = $x->init('david.test@example.com');
        $this->assertTrue($r);
        $x->getList('');
        $list = $x->result();
        $this->assertEquals(4, count($list)); // default aliases.

        $x->set($values);
        $x->store();

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
}
