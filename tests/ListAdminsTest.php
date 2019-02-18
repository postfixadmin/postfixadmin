<?php

class ListAdminsTest extends \PHPUnit\Framework\TestCase {
    public function testBasic() {
        $list= list_admins();

        // may be empty, depending on db.

        $this->assertTrue(is_array($list));
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
