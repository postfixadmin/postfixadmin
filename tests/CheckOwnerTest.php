<?php

require_once('common.php');

class CheckOwnerTest extends \PHPUnit\Framework\TestCase {
    public function testBasic() {
        $check = check_owner('random@example.com', 'test.com');

        $this->assertFalse($check);
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
