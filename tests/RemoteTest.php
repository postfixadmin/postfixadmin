<?php

require_once('common.php');

require_once('simpletest/unit_tester.php');
require_once('Zend/XmlRpc/Client.php');
require_once('Zend/Http/Client.php');
require_once('Zend/Registry.php');

class RemoteTest extends UnitTestCase {

    protected $server_url = 'http://orange/david/postfixadmin/trunk/xmlrpc.php';
    protected $username = 'roger@example.com';
    protected $password = 'patchthedog';

    /* xmlrpc objects... */    
    protected $user;
    protected $vacation;
    protected $alias;

    public function __construct() {
        parent::__construct();

    }

    public function setUp() {
        parent::setUp();

        // ensure a user exists as per the above...
   
        $table_vacation = table_by_key('vacation');
        $table_alias = table_by_key('alias');
        $table_mailbox = table_by_key('mailbox');
        $table_domain = table_by_key('domain');
        $username = escape_string($this->username);
        $password = escape_string(pacrypt($this->password));
 
        db_query("DELETE FROM $table_vacation WHERE email = '$username'");
        db_query("DELETE FROM $table_alias WHERE domain = 'example.com'");
        db_query("DELETE FROM $table_mailbox WHERE domain = 'example.com'");
        db_query("DELETE FROM $table_domain WHERE domain = 'example.com'");

        // create new db records..
        $result = db_query("INSERT INTO $table_domain  (domain, aliases, mailboxes) VALUES ('example.com', 100, 100)");
        if($result['rows'] != 1) {
            die("Failed to add domain to db....");
        }

        $result = db_query("INSERT INTO $table_mailbox (username, password, name, local_part, domain) VALUES ('$username', '$password', 'test user', 'roger', 'example.com')");
        if($result['rows'] != 1) {
            die("Failed to add user to db....");
        }

        $result = db_query("INSERT INTO $table_alias (address, goto, domain) VALUES ('$username', '$username', 'example.com')");
        if($result['rows'] != 1) {
            die("Failed to add alias to db....");
        }

        try {
            $this->xmlrpc_client = new Zend_XmlRpc_Client($this->server_url);
            $http_client = $this->xmlrpc_client->getHttpClient();
            $http_client->setCookieJar();

            $login_object = $this->xmlrpc_client->getProxy('login');
            $success = $login_object->login($this->username, $this->password);
        
            if(!$success) {
                var_dump($success);
                die("Failed to login to xmlrpc interface");
            }

            $this->user = $this->xmlrpc_client->getProxy('user');
            $this->alias = $this->xmlrpc_client->getProxy('alias');
            $this->vacation = $this->xmlrpc_client->getProxy('vacation');
        }
        catch(Exception $e) {
            var_dump($e);
            var_dump($this->xmlrpc_client->getHttpClient()->getLastResponse()->getBody());
            die("Error setting up..");
        }
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
