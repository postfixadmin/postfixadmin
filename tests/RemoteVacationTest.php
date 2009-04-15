<?php
/**
 * Test for Postfixadmin - remote vacation stuff
 *
 * @package tests
 */

require_once('RemoteTest.php');

class RemoteVacationTest extends RemoteTest {
    
    public function __construct() {
        parent::__construct();
        global $CONF;

        // Ensure config.inc.php is vaguely correct.
        if($CONF['vacation'] != 'YES' || $CONF['vacation_control'] != "YES") {
            die("Cannot run tests; vacation not enabled - see config.inc.php");
        }
        if($CONF['vacation_domain'] != 'autoreply.example.com') {
            die("Cannot run tests; vacation_domain is not set to autoreply.example.com - see config.inc.php");
        } 
    }


    /**
     * Adds the test recipient data to the database.
     */
    public function setUp() {
        parent::setUp();
    }
    public function tearDown() {
        parent::tearDown();
    }

    public function testIsVacationSupported() {
        try {
            $this->assertTrue($this->vacation->isVacationSupported());
        }
        catch(Exception $e){ 
            var_dump($e);
            var_dump($this->xmlrpc_client->getHttpClient()->getLastResponse()->getBody());
            die("fail..");
        }
    }

    public function testCheckVacation() {
        $this->assertFalse($this->vacation->checkVacation());
    }


    public function testGetDetails() {
        $details = $this->vacation->getDetails();
        $this->assertFalse($details); // empty by default (thansk to tearDown/setUp);
    } 

    public function testSetAway() {
        try {
            $this->assertFalse($this->vacation->checkVacation());
            $this->assertTrue($this->vacation->setAway('zzzz', 'aaaa'));
            $this->assertTrue($this->vacation->checkVacation());
        }
        catch(Exception $e) {
            var_dump($this->xmlrpc_client->getHttpClient()->getLastResponse()->getBody());
        }
        $details = $this->vacation->getDetails();
        $this->assertEqual($details['subject'], 'zzzz');
        $this->assertEqual($details['body'], 'aaaa');

        $this->vacation->remove();
        $details = $this->vacation->getDetails();
        $this->assertEqual($details['subject'], 'zzzz');
        $this->assertEqual($details['body'], 'aaaa');

        $this->vacation->setAway('subject', 'body');
        $details = $this->vacation->getDetails();
        $this->assertEqual($details['subject'], 'subject');
        $this->assertEqual($details['body'], 'body');
    }
    
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
