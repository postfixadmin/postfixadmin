<?php

class DomainHandlerTest extends \PHPUnit\Framework\TestCase {
    public function testBasic() {
        $x = new DomainHandler();

        $list = $x->getList("");

        $this->assertTrue($list);

        $results = $x->result();

        $this->assertEmpty($results);
    }

    public function testAddAndUpdate() {
        // Fake being an admin.
        $_SESSION = [
            'sessid' => [
                'roles' => ['global-admin']
            ]
        ];
        // Add example.com
        $username = 'admin';
        $domain = 'example.com';


        $dh = new DomainHandler(1, $username, true);

        $dh->init($domain);

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
            'domain' => $domain,
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

        // perform some token update

        $dh = new DomainHandler(0, 'admin', true);
        $dh->init($domain);

        $ret = $dh->set(
            [
                //'domain' => 'example.com',
                'aliases' => 99,
                'mailboxes' => 88,
                'backupmx' => 0,
                'active' => 1,
            ]
        );

        $this->assertTrue($ret);
        $this->assertTrue($dh->save());
        $this->assertEmpty($dh->errormsg);

        $dh->getList('');
        $d = $dh->result()[$domain];

        $this->assertEquals(99, $d['aliases']);
        $this->assertEquals(88, $d['mailboxes']);
    }
}
