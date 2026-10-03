<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BroadcastSmtpTest extends TestCase
{
    public static function smtpPasswords(): array
    {
        return [
            'configured credential' => ['fixture-smtp-secret<&>'],
            'empty credential' => [''],
        ];
    }

    #[DataProvider('smtpPasswords')]
    public function testBroadcastPreservesMessageAndKeepsCredentialOutOfData(string $password): void
    {
        $originalConfig = Config::getInstance()->getAll();
        $originalConf = $GLOBALS['CONF'];
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/fixtures/BroadcastMockSmtp.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        $this->assertIsResource($process);
        fclose($pipes[0]);
        stream_set_timeout($pipes[1], 15);

        try {
            $port = (int)fgets($pipes[1]);
            $this->assertGreaterThan(0, $port);
            $settings = [
                'smtp_server' => '127.0.0.1',
                'smtp_port' => $port,
                'smtp_type' => 'plain',
                'smtp_client' => 'fixture.example.invalid',
                'smtp_username' => 'fixture-user',
                'admin_smtp_password' => $password,
            ];
            foreach ($settings as $key => $value) {
                $GLOBALS['CONF'][$key] = $value;
                Config::write($key, $value);
            }

            $job = [
                'sender' => 'sender@example.invalid',
                'sender_name' => 'Broadcast Fixture',
                'subject' => 'Broadcast regression subject',
                'body' => "Broadcast fixture body\nUTF-8: \u{00F1}",
            ];
            $method = new ReflectionMethod(BroadcastQueue::class, 'sendRecipient');
            $this->assertTrue($method->invoke(null, $job, 'recipient@example.invalid'));

            $capture = json_decode(stream_get_contents($pipes[1]), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(base64_encode('fixture-user'), $capture['auth_user']);
            $this->assertSame(base64_encode($password), $capture['auth_password']);
            $this->assertStringContainsString('To: recipient@example.invalid', $capture['data']);
            $this->assertStringContainsString('<sender@example.invalid>', $capture['data']);
            $this->assertStringContainsString('Subject: ' . $job['subject'], $capture['data']);
            $this->assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $capture['data']);
            $parts = preg_split('/\r?\n\r?\n/', $capture['data'], 2);
            $this->assertCount(2, $parts);
            $this->assertSame($job['body'], base64_decode(trim($parts[1]), true));
            if ($password !== '') {
                $this->assertStringNotContainsString($password, $capture['data']);
                $this->assertStringNotContainsString(base64_encode($password), $capture['data']);
            }
        } finally {
            Config::getInstance()->setAll($originalConfig);
            $GLOBALS['CONF'] = $originalConf;
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_terminate($process);
            proc_close($process);
        }
    }
}
