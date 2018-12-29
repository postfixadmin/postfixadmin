<?php

require_once('common.php');

class DbBasicTest extends \PHPUnit\Framework\TestCase {
    public function testInsertDeleteDomain() {
        $domain = "test". uniqid() . '.com';

        $username = 'testusername' . uniqid();

        $this->assertEquals(
            1,
            db_insert(
                'domain',
                array('domain' => $domain, 'description' => '', 'transport' => '', 'password_expiry' => 99)
            )
        );


        $this->assertEquals(1,
            db_insert(
                'mailbox',
                array('username' => $username, 'password' => 'blah', 'name' => 'blah', 'maildir' => 'blah', 'local_part' => 'blah', 'domain' => $domain,)
            )
        );

        $this->assertEquals(1,
            db_update(
                'mailbox',
                'username',
                $username,
                array('name' => 'blah updated')
            )
        );

        $ret = db_query("SELECT * FROM mailbox WHERE username = '$username'");

        $this->assertEquals(1, $ret['rows']);
        $data = db_assoc($ret['result']);

        $this->assertEquals($data['name'], 'blah updated');

        $this->assertEquals(1, db_delete('mailbox', 'username', $username));
        $this->assertEquals(1, db_delete('domain', 'domain', $domain));
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
