<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TotpManagementHttpTest extends TestCase
{
    private const DOMAIN = 'totp-http.example.com';
    private const USERNAME = 'test@totp-http.example.com';
    private const SECRET = 'JBSWY3DPEHPK3PXP';
    private string $directory;
    private string $url;
    private string $cookie = '';
    private $server;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/pfa-totp-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->directory));
        $config = Config::getInstance()->getAll();
        $config['totp'] = 'YES';
        $config['default_language'] = 'en';
        $config['language_hook'] = '';
        $config['mailbox_post_TOTP_change_secret_script'] = '';
        file_put_contents($this->directory . '/config.json', json_encode($config, JSON_THROW_ON_ERROR));
        self::assertSame(1, db_insert('domain', [
            'domain' => self::DOMAIN, 'description' => 'MFA HTTP test', 'transport' => 'virtual',
        ]));
        self::assertSame(1, db_insert('admin', [
            'username' => self::USERNAME, 'password' => pacrypt('fixture-password'), 'active' => true, 'totp_secret' => null,
        ]));
        self::assertSame(1, db_insert('mailbox', [
            'username' => self::USERNAME, 'password' => pacrypt('fixture-password'), 'name' => 'MFA test', 'maildir' => 'mfa-http/',
            'local_part' => 'test', 'domain' => self::DOMAIN, 'active' => true, 'totp_secret' => null,
        ]));
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->url = 'http://' . $address;
        $command = [PHP_BINARY];
        if (php_ini_loaded_file()) {
            array_push($command, '-c', php_ini_loaded_file());
        }
        array_push($command, '-d', 'session.save_path=' . $this->directory, '-d', 'log_errors=1', '-d', 'error_log=' . $this->directory . '/error.log', '-S', $address, __DIR__ . '/fixtures/totp-http-router.php');
        $env = getenv();
        $env['PFA_TOTP_TEST_CONFIG'] = $this->directory . '/config.json';
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
        db_execute('DELETE FROM ' . table_by_key('alias') . ' WHERE address = :address', ['address' => self::USERNAME]);
        foreach (['admin', 'mailbox'] as $table) {
            db_execute('DELETE FROM ' . table_by_key($table) . ' WHERE username = :username', ['username' => self::USERNAME]);
        }
        db_execute('DELETE FROM ' . table_by_key('domain') . ' WHERE domain = :domain', ['domain' => self::DOMAIN]);
        if (isset($this->directory) && is_dir($this->directory)) {
            foreach (new DirectoryIterator($this->directory) as $file) {
                if (!$file->isDot()) {
                    unlink($file->getPathname());
                }
            }
            rmdir($this->directory);
        }
    }

    public static function accountTypes(): array
    {
        return ['admin' => ['admin', 'admin'], 'mailbox' => ['mailbox', 'user']];
    }

    #[DataProvider('accountTypes')]
    public function testActivationRendersConfirmationAndResetAction(string $table, string $role): void
    {
        $token = $this->startSession($role);
        [$status, $body] = $this->request('/users/totp.php', [
            'CSRF_Token' => $token, 'fPassword_current' => 'fixture-password',
            'fTOTP_secret' => self::SECRET, 'fTOTP_code' => $this->currentCode(self::SECRET),
        ]);
        self::assertSame(200, $status, $body);
        self::assertStringContainsString(Config::lang('pTotp_stored'), $body);
        self::assertStringContainsString('TOTP is enabled.', $body);
        self::assertStringContainsString('Reset TOTP', $body);
        self::assertMatchesRegularExpression('/id="edit_form"[^>]*style="display:none"/', $body);
        self::assertSame(self::SECRET, $this->storedSecret($table));
        [$status, $body] = $this->request('/users/totp.php');
        self::assertSame(200, $status, $body);
        self::assertStringContainsString('TOTP is enabled.', $body);
    }

    #[DataProvider('accountTypes')]
    public function testRejectedReplacementPreservesExistingSecret(string $table, string $role): void
    {
        db_update($table, 'username', self::USERNAME, ['totp_secret' => self::SECRET]);
        $token = $this->startSession($role);
        $newSecret = 'JBSWY3DPEHPK3PXQ';
        foreach ([
            ['wrong-password', $this->currentCode($newSecret)],
            ['fixture-password', 'invalid'],
        ] as [$password, $code]) {
            $token = $this->startSession($role);
            [$status, $body] = $this->request('/users/totp.php', [
                'CSRF_Token' => $token, 'fPassword_current' => $password,
                'fTOTP_secret' => $newSecret, 'fTOTP_code' => $code,
            ]);
            self::assertSame(200, $status, $body);
            self::assertSame(self::SECRET, $this->storedSecret($table));
            self::assertStringContainsString('TOTP is enabled.', $body);
        }
        $token = $this->startSession($role);
        [$status] = $this->request('/users/totp.php', [
            'CSRF_Token' => $token, 'fPassword_current' => 'fixture-password',
            'fTOTP_secret' => $newSecret, 'fTOTP_code' => $this->currentCode($newSecret),
        ]);
        self::assertSame(200, $status);
        self::assertSame($newSecret, $this->storedSecret($table));
    }

    private function currentCode(string $secret): string
    {
        if (time() % 30 === 29) {
            usleep(1100000);
        }
        return \OTPHP\TOTP::create($secret)->now();
    }

    private function storedSecret(string $table): ?string
    {
        $row = db_query_one('SELECT totp_secret FROM ' . table_by_key($table) . ' WHERE username = :username', ['username' => self::USERNAME]);
        return $row['totp_secret'];
    }

    private function startSession(string $role): string
    {
        [$status, $body] = $this->request('/__session?role=' . $role);
        self::assertSame(200, $status, $body);
        return json_decode($body, true, 512, JSON_THROW_ON_ERROR)['token'];
    }

    private function request(string $path, ?array $post = null): array
    {
        $options = ['method' => $post === null ? 'GET' : 'POST', 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10,
            'header' => "Cookie: {$this->cookie}\r\nContent-Type: application/x-www-form-urlencoded\r\n"];
        if ($post !== null) {
            $options['content'] = http_build_query($post);
        }
        $body = file_get_contents($this->url . $path, false, stream_context_create(['http' => $options]));
        self::assertNotFalse($body);
        foreach ($http_response_header as $header) {
            if (preg_match('/^Set-Cookie: (postfixadmin_session=[^;]+)/i', $header, $match)) {
                $this->cookie = $match[1];
            }
        }
        return [(int)explode(' ', $http_response_header[0])[1], $body, $http_response_header];
    }
}
