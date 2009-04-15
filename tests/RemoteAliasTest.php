<?php
/**
 * Test for Postfixadmin  
 *
 * @package tests
 */

require_once('RemoteTest.php');

class RemoteAliasTest extends RemoteTest {
    
    public function __construct() {
        parent::__construct();
        global $CONF;
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

    public function testGet() {
        try {
            /* although we created an alias record, for users, this isn't returned... */
            $this->assertEqual($this->alias->get(), array());
        }
        catch(Exception $e) {
            var_dump($this->xmlrpc_client->getHttpClient()->getLastResponse()->getBody());
        }
    }
    public function testHasStoreAndForward() {
        $this->assertTrue($this->alias->hasStoreAndForward());
    }

    public function testUpdateRemoteOnly() {
        $this->assertTrue($this->alias->update(array('roger@rabbit.com'), 'remote_only'));
        $this->assertFalse($this->alias->hasStoreAndForward());
        $this->assertTrue($this->alias->update(array('roger@rabbit.com'), 'remote_only'));
        $this->assertTrue($this->alias->update(array('roger@rabbit.com', 'fish@fish.net', 'road@runner.com'), 'remote_only'));
        $this->assertEqual($this->alias->get(), array('roger@rabbit.com', 'fish@fish.net', 'road@runner.com'));
        $this->assertFalse($this->alias->hasStoreAndForward());
    }

    public function testUpdateForwardandStore() { 
        $orig_aliases = $this->alias->get();
        if(!is_array($orig_aliases)) {
            $orig_aliases = array();
        }
        $orig_aliases[] = 'roger@robbit.com';
        $this->assertTrue($this->alias->update($orig_aliases, 'forward_and_store'));
        $this->assertEqual($this->alias->get(), $orig_aliases);
        $this->assertTrue($this->alias->update(array($this->username), 'forward_and_store'));
        $this->assertEqual($this->alias->get(), array());
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
