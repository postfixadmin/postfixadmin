<?php


/**
 * Obviously replies on working DNS service
 */
class CheckDomainTest extends \PHPUnit\Framework\TestCase {
    public function testBasic() {
        $this->assertEquals('', check_domain('example.com'));
        $this->assertEquals('', check_domain('google.com'));
        $this->assertRegExp('/ not discoverable in DNS/', check_domain('fishbeansblahblahblah' . uniqid() . '.com'));
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
