<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MfaLoginHttpTest extends TestCase
{
    private const DOMAIN = 'mfa-http.example.com';
    private const USERNAME = 'test@mfa-http.example.com';
    private const SECRET = 'JBSWY3DPEHPK3PXP';
    private string $directory;
    private string $url;
    private string $cookie = '';
    private $server;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/pfa-mfa-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->directory));
        $config = Config::getInstance()->getAll();
        $config['totp'] = 'YES';
        $config['default_language'] = 'en';
        $config['language_hook'] = '';
        file_put_contents($this->directory . '/config.json', json_encode($config, JSON_THROW_ON_ERROR));
        self::assertSame(1, db_insert('domain', [
            'domain' => self::DOMAIN, 'description' => 'MFA HTTP test', 'transport' => 'virtual',
        ]));
        self::assertSame(1, db_insert('admin', [
            'username' => self::USERNAME, 'password' => 'unused', 'active' => true, 'totp_secret' => self::SECRET,
        ]));
        self::assertSame(1, db_insert('mailbox', [
            'username' => self::USERNAME, 'password' => 'unused', 'name' => 'MFA test', 'maildir' => 'mfa-http/',
            'local_part' => 'test', 'domain' => self::DOMAIN, 'active' => true, 'totp_secret' => self::SECRET,
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
        array_push($command, '-d', 'session.save_path=' . $this->directory, '-d', 'log_errors=1', '-d', 'error_log=' . $this->directory . '/error.log', '-S', $address, __DIR__ . '/fixtures/mfa-http-router.php');
        $env = getenv();
        $env['PFA_MFA_TEST_CONFIG'] = $this->directory . '/config.json';
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

    public static function loginTypes(): array
    {
        return ['admin' => ['/login-mfa.php', 'admin'], 'mailbox' => ['/users/login-mfa.php', 'user']];
    }

    #[DataProvider('loginTypes')]
    public function testInvalidCodeIsLoggedAndDisplayed(string $path, string $role): void
    {
        $token = $this->startPendingSession($role);
        [$status, $body] = $this->request($path, ['CSRF_Token' => $token, 'fTOTP_code' => 'invalid']);
        self::assertSame(200, $status, $body);
        self::assertStringContainsString(Config::lang('pTotp_failed'), $body);
        self::assertStringContainsString('second factor login failed (username: ' . self::USERNAME, file_get_contents($this->directory . '/error.log'));
        $state = $this->state();
        self::assertFalse($state['mfa_complete']);
        self::assertSame([], $state['roles']);
    }

    #[DataProvider('loginTypes')]
    public function testValidCodeCompletesAuthentication(string $path, string $role): void
    {
        $token = $this->startPendingSession($role);
        // Avoid generating a code immediately before its time window expires.
        if (time() % 30 === 29) {
            usleep(1100000);
        }
        [$status, $body, $headers] = $this->request($path, ['CSRF_Token' => $token, 'fTOTP_code' => \OTPHP\TOTP::create(self::SECRET)->now()]);
        self::assertSame(302, $status, $body);
        self::assertContains('Location: main.php', $headers);
        $state = $this->state();
        self::assertTrue($state['mfa_complete']);
        self::assertContains($role, $state['roles']);
    }

    #[DataProvider('loginTypes')]
    public function testInvalidCsrfCannotCompleteAuthentication(string $path, string $role): void
    {
        $this->startPendingSession($role);
        [$status] = $this->request($path, ['CSRF_Token' => 'invalid', 'fTOTP_code' => \OTPHP\TOTP::create(self::SECRET)->now()]);
        self::assertSame(419, $status);
        self::assertFalse($this->state()['mfa_complete']);
    }

    public function testPendingSessionCannotEditForwarding(): void
    {
        self::assertSame(1, db_insert('alias', ['address' => self::USERNAME, 'goto' => self::USERNAME, 'domain' => self::DOMAIN, 'active' => true]));
        $token = $this->startPendingSession('user');
        $path = '/edit.php?table=alias&edit=' . rawurlencode(self::USERNAME);
        foreach ([null, ['CSRF_Token' => $token, 'value' => ['goto' => 'other@example.com']]] as $post) {
            [$status, $body] = $this->request($path, $post);
            self::assertSame(302, $status, $body);
            self::assertSame('', $body);
            $alias = db_query_one('SELECT goto FROM ' . table_by_key('alias') . ' WHERE address = :address', ['address' => self::USERNAME]);
            self::assertSame(self::USERNAME, $alias['goto']);
            self::assertFalse($this->state()['mfa_complete']);
            self::assertSame([], $this->state()['roles']);
        }
    }

    private function startPendingSession(string $role): string
    {
        [$status, $body] = $this->request('/__session?role=' . $role);
        self::assertSame(200, $status, $body);
        return json_decode($body, true, 512, JSON_THROW_ON_ERROR)['token'];
    }

    private function state(): array
    {
        [$status, $body] = $this->request('/__state');
        self::assertSame(200, $status, $body);
        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
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
