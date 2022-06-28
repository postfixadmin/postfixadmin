<?php

class ConfigTest extends \PHPUnit\Framework\TestCase {
    public function setUp(): void {
        $c = Config::getInstance();

        $all = $c->getAll();

        $all['xmlrpc_enabled'] = false;

        $c->setAll($all);

        parent::setUp();
    }

    public function testLangF() {
        $x = Config::lang_f('must_be_numeric', 'foo@bar');

        $this->assertEquals('foo@bar must be numeric', $x);
    }

    public function testLang() {
        $x = Config::lang('must_be_numeric', 'foo@bar');

        $this->assertEquals('%s must be numeric', $x);
    }

    public function testBool() {
        $x = Config::bool('xmlrpc_enabled');

        $this->assertFalse($x);
    }
}
