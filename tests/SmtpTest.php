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

    public function testDotStuffDoublesLeadingDots(): void
    {
        $this->assertEquals("hello\r\n..\r\n..foo\r\nbar.", smtp_dot_stuff("hello\r\n.\r\n.foo\r\nbar."));
        $this->assertEquals("..start", smtp_dot_stuff(".start"));
    }

    /**
     * A domain admin could previously end DATA with a lone "." line in sendmail.php's body
     * and follow it with their own SMTP commands.
     */
    public function testDotStuffPreventsEndOfDataInjection(): void
    {
        $body = "hi\r\n.\r\nMAIL FROM:<evil@example.com>\r\nRCPT TO:<victim@example.com>\r\nDATA\r\n";
        $this->assertStringNotContainsString("\r\n.\r\n", smtp_dot_stuff($body));
    }

    /**
     * Bare LF / CR must not survive, otherwise "\n.\n" might be treated as end-of-data by a lenient server.
     */
    public function testDotStuffNormalisesLineEndings(): void
    {
        $this->assertEquals("a\r\n..\r\nb\r\n..\r\nc", smtp_dot_stuff("a\n.\nb\r.\rc"));
    }

    public function testSmtpMailRefusesLineBreaksInAddresses(): void
    {
        $this->assertFalse(@smtp_mail("victim@example.com\r\nRCPT TO:<other@example.com>", 'admin@example.com', 'subject', 'body'));
        $this->assertFalse(@smtp_mail('victim@example.com', "admin@example.com\nBcc: x@example.com", 'subject', 'body'));
    }
}
