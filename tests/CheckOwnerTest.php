<?php

class CheckOwnerTest extends \PHPUnit\Framework\TestCase {
    public function testBasic() {
        $check = check_owner('random@example.com', 'test.com');
        $this->assertFalse($check, "there should be no entries in test.com as it's an invalid/non-existant domain");
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
