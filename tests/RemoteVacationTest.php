<?php
/**
 * Test for Postfixadmin - remote vacation stuff
 *
 * @package tests
 */

require_once('RemoteTest.php');

class RemoteVacationTest extends RemoteTest {
    /**
     * Adds the test recipient data to the database.
     */
    public function setUp(): void {
        // Ensure config.inc.php is vaguely correct.
        global $CONF;
        if ($CONF['vacation'] != 'YES' || $CONF['vacation_control'] != "YES") {
            $this->markTestSkipped("Cannot run tests; vacation not enabled - see config.inc.php");
        }
        if ($CONF['vacation_domain'] != 'autoreply.example.com') {
            $this->markTestSkipped("Cannot run tests; vacation_domain is not set to autoreply.example.com - see config.inc.php");
        }
        parent::setUp();
    }

    public function testIsVacationSupported() {
        $this->assertTrue($this->vacation->isVacationSupported());
    }

    public function testCheckVacation() {
        $this->assertFalse($this->vacation->checkVacation());
    }


    public function testGetDetails() {
        $details = $this->vacation->getDetails();
        $this->assertFalse($details); // empty by default (thanks to tearDown/setUp);
    }

    public function testSetAway() {
        $this->assertFalse($this->vacation->checkVacation());
        $this->assertTrue($this->vacation->setAway('zzzz', 'aaaa'));
        $this->assertTrue($this->vacation->checkVacation());

        $details = $this->vacation->getDetails();
        $this->assertEquals($details['subject'], 'zzzz');
        $this->assertEquals($details['body'], 'aaaa');

        $this->vacation->remove();
        $details = $this->vacation->getDetails();
        $this->assertEquals($details['subject'], 'zzzz');
        $this->assertEquals($details['body'], 'aaaa');

        $this->vacation->setAway('subject', 'body');
        $details = $this->vacation->getDetails();
        $this->assertEquals($details['subject'], 'subject');
        $this->assertEquals($details['body'], 'body');
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
