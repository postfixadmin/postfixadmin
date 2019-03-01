<?php

class DomainHandlerTest extends \PHPUnit\Framework\TestCase {
    public function testBasic() {
        $x = new DomainHandler();

        $list = $x->getList("");

        $this->assertTrue($list);

        $results = $x->result();

        $this->assertEmpty($results);
    }
}
