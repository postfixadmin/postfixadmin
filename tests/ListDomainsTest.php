<?php

class ListDomainsTest extends \PHPUnit\Framework\TestCase {
    public function testBasic() {
        $domains = list_domains();

        $this->assertTrue(is_array($domains));
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
