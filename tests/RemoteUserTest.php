<?php
/**
 * Test for Postfixadmin - remote vacation stuff
 *
 * @package tests
 */

require_once('RemoteTest.php');

class RemoteUserTest extends RemoteTest {
    public function testChangePassword() {
        try {
            $this->assertTrue($this->user->login($this->username, $this->password));
            $this->assertTrue($this->user->changePassword($this->password, 'foobar'));
            $this->assertTrue($this->user->login($this->username, 'foobar'));
        } catch (Exception $e) {
            var_dump($this->xmlrpc_client->getHttpClient()->getLastResponse()->getBody());
        }
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
