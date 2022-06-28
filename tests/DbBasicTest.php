<?php

class DbBasicTest extends \PHPUnit\Framework\TestCase {
    private $test_domain;

    public function setUp(): void {
        $db = db_connect();
        $test_domain = 'test' . uniqid() . '.com';
        $this->test_domain = $test_domain;

        $db->exec("DELETE FROM domain WHERE domain = '$test_domain'");
    }
    public function testInsertDeleteDomain() {
        $domain = $this->test_domain;

        $username = 'testusername' . uniqid();

        $this->assertEquals(
            1,
            db_insert(
                'domain',
                array(
                    'domain' => $domain,
                    'description' => 'test',
                    'transport' => '',
                    'password_expiry' => 99
                )
            )
        );


        $this->assertEquals(1,
            db_insert(
                'mailbox',
                array(
                    'username' => $username,
                    'password' => 'blah',
                    'name' => 'blah',
                    'maildir' => 'blah',
                    'local_part' => 'blah',
                    'domain' => $domain
                )
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

        $ret = db_query_one("SELECT * FROM mailbox WHERE username = :username", array('username' => $username));


        $this->assertTrue(!empty($ret));
        $this->assertTrue(is_array($ret));


        $this->assertEquals($ret['name'], 'blah updated');

        $this->assertEquals(0, db_delete('mailbox', 'username', 'blahblahinvalid'));

        $this->assertEquals(1, db_delete('mailbox', 'username', $username));
        $this->assertEquals(1, db_delete('domain', 'domain', $domain));
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
