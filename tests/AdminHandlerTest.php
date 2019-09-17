<?php

class AdminHandlerTest extends \PHPUnit\Framework\TestCase {
    public function testBasic() {
        $x = new AdminHandler();

        $list = $x->getList("");

        $this->assertTrue($list);

        $results = $x->result();

        $this->assertEmpty($results);
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
