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
      
    public static function smtpAuthenticationCases(): array
    {
        $cases = [];
        foreach ([false, true] as $raw) {
            foreach ([
                'no authentication' => ['', 'none', true],
                'configured authentication' => ['fixture-secret', 'accept', true],
                'zero password' => ['0', 'accept', true],
                'rejected authentication' => ['fixture-secret', 'reject', false],
                'rejected recipient' => ['', 'recipient-reject', false],
            ] as $name => [$password, $mode, $success]) {
                $cases[$name . ($raw ? ' raw message' : ' subject and body')] = [$password, $mode, $success, $raw];
            }
        }
        return $cases;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('smtpAuthenticationCases')]
    public function testSmtpAuthenticationIsOptional(string $password, string $mode, bool $success, bool $raw): void
    {
        $originalConf = $GLOBALS['CONF'];
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/fixtures/OptionalAuthSmtp.php', $mode],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        $this->assertIsResource($process);
        fclose($pipes[0]);
        stream_set_timeout($pipes[1], 15);

        try {
            $port = (int)fgets($pipes[1]);
            $this->assertGreaterThan(0, $port);
            $GLOBALS['CONF'] = array_replace($originalConf, [
                'smtp_server' => '127.0.0.1',
                'smtp_port' => $port,
                'smtp_type' => 'plain',
                'smtp_client' => 'fixture.example.invalid',
                'smtp_username' => 'fixture-user',
                'admin_smtp_password' => $password,
            ]);
            $to = 'recipient@example.invalid';
            $from = 'sender@example.invalid';
            $subject = 'SMTP regression';
            $body = 'Welcome fixture body';
            if ($raw) {
                $result = smtp_mail($to, $from, "Subject: $subject\r\n\r\n$body");
            } else {
                $result = smtp_mail($to, $from, $subject, $body);
            }
            $this->assertSame($success, $result);
            $capture = json_decode(stream_get_contents($pipes[1]), true, 512, JSON_THROW_ON_ERROR);
            if ($password === '') {
                $this->assertNotContains('AUTH LOGIN', $capture['commands']);
                $this->assertSame('', $capture['auth_user']);
                $this->assertSame('', $capture['auth_password']);
            } else {
                $this->assertContains('AUTH LOGIN', $capture['commands']);
                if ($mode === 'accept') {
                    $this->assertSame(base64_encode('fixture-user'), $capture['auth_user']);
                    $this->assertSame(base64_encode($password), $capture['auth_password']);
                }
            }
            if ($success) {
                $this->assertContains("MAIL FROM:<$from>", $capture['commands']);
                $this->assertContains("RCPT TO:<$to>", $capture['commands']);
                $this->assertStringContainsString("Subject: $subject", $capture['data']);
                $this->assertStringContainsString($body, $capture['data']);
                $this->assertStringNotContainsString('fixture-secret', $capture['data']);
                $this->assertStringNotContainsString(base64_encode('fixture-secret'), $capture['data']);
            } else {
                $this->assertNotContains('DATA', $capture['commands']);
                $this->assertSame('', $capture['data']);
                if ($mode === 'reject') {
                    $this->assertNotContains("MAIL FROM:<$from>", $capture['commands']);
                }
            }
        } finally {
            $GLOBALS['CONF'] = $originalConf;
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_terminate($process);
            proc_close($process);
        }
    }
}
