<?php

require_once('common.php');

require_once('PHPUnit/Autoload.php');

require_once('Zend/XmlRpc/Client.php');
require_once('Zend/Http/Client.php');
require_once('Zend/Registry.php');

abstract class RemoteTest extends PHPUnit_Framework_TestCase {

    protected $server_url = 'http://orange/david/postfixadmin/xmlrpc.php';
    protected $username = 'roger@example.com';
    protected $password = 'patchthedog';

    /* xmlrpc objects... */    
    protected $user;
    protected $vacation;
    protected $alias;

    public function setUp() {
        parent::setUp();
        $this->xmlrpc_client = new Zend_XmlRpc_Client($this->server_url);
        $http_client = $this->xmlrpc_client->getHttpClient();
        $http_client->setCookieJar();

        $login_object = $this->xmlrpc_client->getProxy('login');
        $success = $login_object->login($this->username, $this->password);

        if(!$success) {
            var_dump($success);
            die("Failed to login to xmlrpc interface");
        }
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
