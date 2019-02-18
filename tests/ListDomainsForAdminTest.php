<?php

class ListDomainsForAdminTest extends \PHPUnit\Framework\TestCase {
    public function testBasic() {
        $this->assertEquals([], list_domains_for_admin('test@test.com'));
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
