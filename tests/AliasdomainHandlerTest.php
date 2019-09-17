<?php

class AliasdomainHandlerTest extends \PHPUnit\Framework\TestCase {
    public function testBasic() {
        $x = new AliasdomainHandler();

        $list = $x->getList("");

        $this->assertTrue($list);

        $results = $x->result();

        $this->assertEmpty($results);
    }
}
