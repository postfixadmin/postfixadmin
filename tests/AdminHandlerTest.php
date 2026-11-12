<?php

class AdminHandlerTest extends \PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        db_execute('DELETE FROM admin');
    }
    public function testBasic()
    {
        $x = new AdminHandler();

        $list = $x->getList("");

        $this->assertTrue($list);

        $results = $x->result();

        $this->assertEmpty($results);
    }
    public function testDiscardedSearchFieldsDoNotBreakUnscopedLists(): void
    {
        $_SESSION = ['sessid' => ['roles' => ['admin', 'global-admin']]];
        foreach (['getList', 'getPagebrowser'] as $method) {
            $baseline = new AdminHandler();
            $expected = $baseline->{$method}('', []);
            foreach (['unknown' => 'x', 'password' => 'x%', 'password2' => 'x%'] as $field => $value) {
                $handler = new AdminHandler();
                $this->assertSame($expected, $handler->{$method}([$field => $value], [$field => 'LIKE']));
                $this->assertSame($baseline->result(), $handler->result());
            }
        }
        $_SESSION = [];
    }

}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
