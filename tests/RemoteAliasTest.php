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

    public function testGet() {
        /* although we created an alias record, for users, this isn't returned... */
        $this->assertEquals($this->alias->get(), array());
    }

    public function testHasStoreAndForward() {
        $this->assertTrue($this->alias->hasStoreAndForward());
    }

    public function testUpdateRemoteOnly() {
        $this->assertTrue($this->alias->update(array('roger@rabbit.com'), 'remote_only'));
        $this->assertFalse($this->alias->hasStoreAndForward());
        $this->assertTrue($this->alias->update(array('roger@rabbit.com'), 'remote_only'));
        $this->assertTrue($this->alias->update(array('roger@rabbit.com', 'fish@fish.net', 'road@runner.com'), 'remote_only'));
        $this->assertEquals($this->alias->get(), array('roger@rabbit.com', 'fish@fish.net', 'road@runner.com'));
        $this->assertFalse($this->alias->hasStoreAndForward());
    }

    public function testUpdateForwardandStore() {
        $orig_aliases = $this->alias->get();
        if (!is_array($orig_aliases)) {
            $orig_aliases = array();
        }
        $orig_aliases[] = 'roger@robbit.com';
        $this->assertTrue($this->alias->update($orig_aliases, 'forward_and_store'));
        $this->assertEquals($this->alias->get(), $orig_aliases);
        $this->assertTrue($this->alias->update(array($this->username), 'forward_and_store'));
        $this->assertEquals($this->alias->get(), array());
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
