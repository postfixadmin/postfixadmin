<?php

use PHPUnit\Framework\TestCase;

class SmtpTest extends TestCase
{
    public function testRequireResponseAcceptsExpectedCode(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "220 ready\r\n");
        rewind($stream);

        smtp_require_response($stream, 220);
        $this->assertTrue(true);
        fclose($stream);
    }

    public function testRequireResponseRejectsUnexpectedCode(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "550 rejected\r\n");
        rewind($stream);

        $this->expectException(RuntimeException::class);
        smtp_require_response($stream, 250);
    }

    public function testSmtpWriteRejectsClosedStream(): void
    {
        $stream = fopen('php://memory', 'r+');
        fclose($stream);

        $this->expectException(RuntimeException::class);
        smtp_write($stream, "EHLO example.com\r\n");
    }
}
