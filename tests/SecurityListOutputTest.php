<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SecurityListOutputTest extends TestCase
{
    private const USERNAME = 'test@security-list-http.example.com';
    private const DOMAIN = 'security-list-http.example.com';
    private string $directory;
    private string $url;
    private string $cookie = '';
    private $server;

    protected function setUp(): void
    {
        $descriptions = self::descriptions();
        foreach ($descriptions as $index => $description) {
            self::assertSame(1, db_insert('mailbox_app_password', [
                'username' => self::USERNAME, 'description' => $description, 'password_hash' => 'fixture-unused',
            ]));
            self::assertSame(1, db_insert('totp_exception_address', [
                'username' => self::USERNAME, 'description' => $description, 'ip' => '192.0.2.' . ($index + 1),
            ]));
        }
        self::assertSame(1, db_insert('totp_exception_address', [
            'username' => self::DOMAIN, 'description' => 'Domain exception', 'ip' => '192.0.2.10',
        ]));
        $this->directory = sys_get_temp_dir() . '/pfa-security-list-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->directory));
        $config = Config::getInstance()->getAll();
        $config['totp'] = 'YES';
        $config['app_passwords'] = 'YES';
        $config['default_language'] = 'en';
        $config['language_hook'] = '';
        file_put_contents($this->directory . '/config.json', json_encode($config, JSON_THROW_ON_ERROR));
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->url = 'http://' . $address;
        $command = [PHP_BINARY];
        if (php_ini_loaded_file()) {
            array_push($command, '-c', php_ini_loaded_file());
        }
        array_push($command, '-d', 'session.save_path=' . $this->directory, '-d', 'log_errors=1', '-d', 'error_log=' . $this->directory . '/error.log', '-S', $address, __DIR__ . '/fixtures/security-list-http-router.php');
        $env = getenv();
        $env['PFA_SECURITY_LIST_TEST_CONFIG'] = $this->directory . '/config.json';
        $this->server = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $this->directory . '/server.log', 'a'], 2 => ['file', $this->directory . '/server.log', 'a']], $pipes, dirname(__DIR__), $env);
        self::assertIsResource($this->server);
        fclose($pipes[0]);
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $ready = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
            if ($ready !== false) {
                fclose($ready);
                return;
            }
            usleep(50000);
        }
        self::fail('PHP HTTP server did not start: ' . file_get_contents($this->directory . '/server.log'));
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }
        foreach (['mailbox_app_password', 'totp_exception_address'] as $table) {
            db_execute('DELETE FROM ' . table_by_key($table) . ' WHERE username = :username OR username = :domain', [
                'username' => self::USERNAME, 'domain' => self::DOMAIN,
            ]);
        }
        if (isset($this->directory) && is_dir($this->directory)) {
            foreach (new DirectoryIterator($this->directory) as $file) {
                if (!$file->isDot()) {
                    unlink($file->getPathname());
                }
            }
            rmdir($this->directory);
        }
    }

    public static function pages(): array
    {
        return [
            'mailbox application passwords' => ['/users/app-passwords.php', 'user', 2, 0],
            'global-admin application passwords' => ['/app-passwords.php', 'global-admin', 2, 0],
            'mailbox TOTP exceptions' => ['/users/totp-exceptions.php', 'user', 3, 1],
            'global-admin TOTP exceptions' => ['/totp-exceptions.php', 'global-admin', 3, 0],
        ];
    }

    #[DataProvider('pages')]
    public function testStoredListValuesRenderAsText(string $path, string $role, int $descriptionColumn, int $disabled): void
    {
        [$status] = $this->request('/__session?role=' . $role);
        self::assertSame(200, $status);
        [$status, $html] = $this->request($path);
        self::assertSame(200, $status, $html);
        $document = new DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
        $xpath = new DOMXPath($document);
        self::assertSame(0, $xpath->query('//*[@data-security-fixture]')->length);
        $rows = $xpath->query('//table[@id="mailbox_table"]/tr[td]');
        $descriptions = [];
        foreach ($rows as $row) {
            $descriptions[] = $xpath->query('./td[' . $descriptionColumn . ']', $row)->item(0)->textContent;
        }
        foreach (self::descriptions() as $description) {
            self::assertContains(html_entity_decode($description, ENT_QUOTES, 'UTF-8'), $descriptions);
        }
        self::assertSame($disabled, $xpath->query('//table[@id="mailbox_table"]//button[@disabled]')->length);
        self::assertSame($rows->length, $xpath->query('//table[@id="mailbox_table"]//input[@name="CSRF_Token"]')->length);
        self::assertStringNotContainsString('&amp;lt;tag&amp;gt;', $html);
    }

    private static function descriptions(): array
    {
        return [
            '<img data-security-fixture="injected" src=x onerror="alert(1)">',
            "R&D \"quoted\" 'text' <tag> UTF-8: \u{00F1}",
            'Legacy &lt;tag&gt; &amp; text',
        ];
    }

    private function request(string $path): array
    {
        $options = ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10,
            'header' => "Cookie: {$this->cookie}\r\n"];
        $body = file_get_contents($this->url . $path, false, stream_context_create(['http' => $options]));
        self::assertNotFalse($body);
        foreach ($http_response_header as $header) {
            if (preg_match('/^Set-Cookie: (postfixadmin_session=[^;]+)/i', $header, $match)) {
                $this->cookie = $match[1];
            }
        }
        return [(int)explode(' ', $http_response_header[0])[1], $body];
    }
}
