<?php

class VacationHandlerTest extends \PHPUnit\Framework\TestCase {
    public function testBasic() {
        $x = new VacationHandler();

        $list = $x->getList("");

        $this->assertTrue($list);

        $results = $x->result();

        $this->assertEmpty($results);
    }
}
