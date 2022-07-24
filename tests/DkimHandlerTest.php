<?php

class DkimHandlerTest extends \PHPUnit\Framework\TestCase
{
    public function testBasic()
    {
        $x = new DkimHandler();

        $list = $x->getList("");

        $this->assertTrue($list);

        $results = $x->result();

        $this->assertEmpty($results);
    }
}
