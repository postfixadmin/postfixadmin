<?php


use PHPUnit\Framework\TestCase;

class TotpPfTest extends TestCase
{
    public function testBasic()
    {
        $x = new TotpPf('mailbox');
        $array = $x->generate('test@example.com');

        $this->assertIsArray($array);
        $this->assertIsString($array[0]);
        $this->assertIsString($array[1]);
    }
}
