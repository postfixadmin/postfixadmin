<?php

class GeneratePasswordTest extends \PHPUnit\Framework\TestCase {
    public function testBasic() {
        $one = generate_password();

        $two = generate_password();

        $this->assertNotEquals($one, $two);
        $this->assertNotEmpty($one);
        $this->assertNotEmpty($two);
        $this->assertEquals(12, strlen($one));
    }

    public function testLength() {
        $one = generate_password(1);

        $ten = generate_password(10);

        $this->assertNotEquals($one, $ten);
        $this->assertNotEmpty($one);
        $this->assertNotEmpty($ten);
        $this->assertEquals(10, strlen($ten));
        $this->assertEquals(1, strlen($one));
    }
}
