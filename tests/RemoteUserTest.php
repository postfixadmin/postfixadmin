<?php

/**
 * Test for Postfixadmin - remote vacation stuff
 *
 * @package tests
 */

require_once('RemoteTestCase.php');

class RemoteUserTest extends RemoteTestCase
{
    public function testChangePassword()
    {
        $this->assertTrue($this->user->login($this->username, $this->password));
        $this->assertTrue($this->user->changePassword($this->password, 'foobar'));
        $this->assertTrue($this->user->login($this->username, 'foobar'));
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
