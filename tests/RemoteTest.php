<?php

require_once('common.php');

abstract class RemoteTest extends PHPUnit_Framework_TestCase {
    protected $server_url = 'http://change.me/to/work'; // http://orange/david/postfixadmin/xmlrpc.php';
    protected $username = 'user@example.com';
    protected $password = 'password1';

    /* xmlrpc objects... */
    protected $user;
    protected $vacation;
    protected $alias;

    public function setUp() {
        parent::setUp();

        if ($this->server_url == 'http://change.me/to/work') {
            $this->markTestSkipped("Test skipped; Configuration change to \$this->server_url required");
        }

        if (!class_exists('Zend_XmlRpc_Client', true)) {
            $this->markTestSkipped("Test skipped; Zend_XmlRpc_Client not found");
        }

        $this->xmlrpc_client = new Zend_XmlRpc_Client($this->server_url);
        $http_client = $this->xmlrpc_client->getHttpClient();
        $http_client->setCookieJar();

        $login_object = $this->xmlrpc_client->getProxy('login');
        $success = $login_object->login($this->username, $this->password);

        if (!$success) {
            die("Failed to login to xmlrpc interface");
        }
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
