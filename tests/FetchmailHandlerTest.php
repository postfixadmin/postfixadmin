<?php

class FetchmailHandlerTest extends \PHPUnit\Framework\TestCase {
    public function testBasic() {
        $x = new FetchmailHandler();

        $list = $x->getList("");

        $this->assertTrue($list);

        $results = $x->result();

        $this->assertEmpty($results);
    }
}
