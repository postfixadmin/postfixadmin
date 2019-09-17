<?php

class AdminpasswordHandlerTest extends \PHPUnit\Framework\TestCase {
    public function testBasic() {
        $x = new AdminpasswordHandler();

        $list = $x->getList("");

        $this->assertTrue($list);

        $results = $x->result();

        $this->assertEmpty($results);
    }
}
