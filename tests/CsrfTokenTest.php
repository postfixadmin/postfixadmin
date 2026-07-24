<?php


use PHPUnit\Framework\TestCase;

class CsrfTokenTest extends TestCase
{
    public function testBasic()
    {
        $token1 = CsrfToken::generate();
        $token2 = CsrfToken::generate();
        $this->assertNotEquals($token1, $token2);
        $this->assertNotEmpty($token1);
        $this->assertNotEmpty($token2);

        $this->assertTrue(CsrfToken::assertValid($token1, true));
        $this->assertTrue(CsrfToken::assertValid($token2, true));

        $this->expectException(CsrfInvalidException::class);
        CsrfToken::assertValid("different token", true);

    }

    public function testThatTokenCanOnlyBeUsedOnce()
    {
        $token1 = CsrfToken::generate();

        $this->assertNotEmpty($token1);
        $this->assertTrue(CsrfToken::assertValid($token1));

        $this->expectException(CsrfInvalidException::class);
        $this->assertTrue(CsrfToken::assertValid($token1));

    }


    public function testThatOrderOfUseDoesNotMatter()
    {
        $token1 = CsrfToken::generate();
        $token2 = CsrfToken::generate();

        $this->assertNotEmpty($token1);
        $this->assertNotEmpty($token2);

        $this->assertTrue(CsrfToken::assertValid($token2));
        $this->assertTrue(CsrfToken::assertValid($token1));

    }
}
