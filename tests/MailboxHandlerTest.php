<?php

class MailboxHandlerTest extends \PHPUnit\Framework\TestCase {
    public function tearDown(): void {
        db_query('DELETE FROM mailbox');
        db_query('DELETE FROM alias');
        db_query('DELETE FROM domain_admins');
        db_query('DELETE FROM domain');

        parent::tearDown();
    }

    public function setUp(): void {
        global $CONF;
        parent::setUp();

        $CONF['quota'] = 'YES';
    }

    public function testBasic() {
        $x = new MailboxHandler();

        $list = $x->getList("");

        $this->assertTrue($list);

        $results = $x->result();

        $this->assertEmpty($results);
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
                'quota' => 99999911111,
                'maxquota' => 99999999999,
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

        $x = new MailboxHandler(1, 'admin', true);

        $values = [
            'localpart' => 'david.test',
            'domain' => 'example.com',
            'active' => 1,
            'password' => 'test1234',
            'password2' => 'test1234',
            'name' => 'test person',
            'quota' => 1,
            'welcome_mail' => 0,
            'email_other' => '',
            'username' => 'david.test@example.com',

        ];

        $r = $x->init('david.test@example.com');
        $this->assertTrue($r);
        $x->getList('');
        $list = $x->result();
        $this->assertEquals(0, count($list));

        $x->set($values);
        $x->save();

        $x->getList('');
        $list = $x->result();

        $this->assertEquals(1, count($list), json_encode($x->errormsg));

        $found = false;

        foreach ($list as $key => $details) {
            if ($key == 'david.test@example.com') {
                $this->assertEquals('example.com', $details['domain']);
                $this->assertEquals('david.test@example.com', $details['username']);
                $this->assertEquals('test person', $details['name']);

                $this->assertNotEmpty($details['_modified']);
                $this->assertNotEmpty($details['_created']);

                $this->assertEquals($details['_modified'], $details['_created']); // new data should have them equal.
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, "check output : " . json_encode($list));

        // need to make updated != created.
        sleep(1);

        // Try and edit.

        $h = new MailboxHandler(0, 'admin', true);
        $h->init('david.test@example.com');

        $r = $h->set([
            'password' => '',
            'password2' => '',
            'name' => 'test person 1234',
            'quota' => 123456,
            'active' => 1,
            'email_other' => 'fred@example.com',
            'username' => 'david.test@example.com'
        ]);

        $this->assertEmpty($h->errormsg, json_encode($h->errormsg));
        $this->assertEmpty($h->infomsg);
        $this->assertTrue($r);
        $this->assertTrue($h->save());

        $h->getList('');
        $list = $h->result();
        $this->assertEquals(1, count($list));
        $found = false;
        foreach ($list as $key => $details) {
            if ($key == 'david.test@example.com') {
                // Found!
                $this->assertEquals('example.com', $details['domain']);
                $this->assertEquals('david.test@example.com', $details['username']);
                $this->assertEquals(123456, $details['quota']);
                $this->assertEquals('test person 1234', $details['name']);

                $this->assertNotEmpty($details['_modified']);
                $this->assertNotEmpty($details['_created']);

                $this->assertNotEquals($details['_modified'], $details['_created']);

                $found = true;
                break;
            }
        }

        $this->assertTrue($found);
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
