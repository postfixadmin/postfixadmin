<?php

class MailboxHandlerTest extends \PHPUnit\Framework\TestCase {
    public function testBasic() {
        $x = new MailboxHandler();

        $list = $x->getList("");

        $this->assertTrue($list);


        $results = $x->result();

        $this->assertEmpty($results);

        $this->assertFalse($x->checkPasswordRecoveryCode('test', 'fake'));

        $token = $x->getPasswordRecoveryCode('test.peraon.does.not.exist@example.com');

        $this->assertFalse($token);
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
