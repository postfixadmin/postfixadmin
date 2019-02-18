<?php

class RemoveFromArrayTest extends \PHPUnit\Framework\TestCase {
    public function testBasic() {
        $list = array('a','b','c','d');

        list($found, $new) = remove_from_array($list, 'd');
        $this->assertEquals(1, $found);
        $this->assertEquals(array('a','b','c'), $new);

        list($found, $new) = remove_from_array($list, 'a');
        $this->assertEquals(1, $found);
        $this->assertEquals(array(1 => 'b',2 => 'c',3=>'d'), $new);

        list($found, $new) = remove_from_array($list, 'x');
        $this->assertEquals(0, $found);
        $this->assertEquals(array('a','b','c','d'), $new);
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
