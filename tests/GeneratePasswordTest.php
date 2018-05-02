<?php

require_once('common.php');

class GeneratePasswordTest extends PHPUnit_Framework_TestCase {
    public function testBasic() {
        $one = generate_password();

        $two = generate_password();

        $this->assertNotEquals($one, $two);
        $this->assertNotEmpty($one);
        $this->assertNotEmpty($two);
    }
}
