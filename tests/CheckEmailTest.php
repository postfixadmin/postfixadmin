<?php

/**
 * Obviously relies on working DNS service etc.
 */
class CheckEmailTest extends \PHPUnit\Framework\TestCase {
    public function testBasic() {
        $this->assertEquals('', check_email('test@example.com'));
        $this->assertRegExp('/ not discoverable in DNS/', check_email('test@fishbeansblahblahblah' . uniqid() . '.com'));
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
