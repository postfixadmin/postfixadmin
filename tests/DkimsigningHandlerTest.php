<?php

class DkimsigningHandlerTest extends \PHPUnit\Framework\TestCase
{
    public function testBasic()
    {
        $x = new DkimsigningHandler();

        $list = $x->getList("");

        $this->assertTrue($list);

        $results = $x->result();

        $this->assertEmpty($results);
    }
}
