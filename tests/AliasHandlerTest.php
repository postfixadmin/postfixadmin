<?php

class AliasHandlerTest extends \PHPUnit\Framework\TestCase {
    public function testBasic() {
        $x = new AliasHandler();

        $list = $x->getList("");

        $this->assertTrue($list);

        $results = $x->result();

        $this->assertEmpty($results);
    }
}
