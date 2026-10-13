<?php

use PHPUnit\Framework\TestCase;

class XmlRpcSessionHttpTest extends TestCase
{
    private const DOMAIN = 'xmlrpc-http.example.com';
    private const USER_A = 'a@xmlrpc-http.example.com';
    private const USER_B = 'b@xmlrpc-http.example.com';
    private const SECRET = 'JBSWY3DPEHPK3PXP';
    private string $directory;
    private string $url;
    private string $cookie = '';
    private $server;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/pfa-xmlrpc-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->directory));
        $config = Config::getInstance()->getAll();
        $config['totp'] = 'YES';
        $config['xmlrpc_enabled'] = true;
        $config['default_language'] = 'en';
        $config['language_hook'] = '';
        $config['mailbox_post_TOTP_change_secret_script'] = '';
        file_put_contents($this->directory . '/config.json', json_encode($config, JSON_THROW_ON_ERROR));
        self::assertSame(1, db_insert('domain', [
            'domain' => self::DOMAIN, 'description' => 'MFA HTTP test', 'transport' => 'virtual',
        ]));
        foreach ([self::USER_A => null, self::USER_B => self::SECRET] as $username => $secret) {
            self::assertSame(1, db_insert('mailbox', [
                'username' => $username, 'password' => pacrypt('fixture-password'), 'name' => 'RPC test', 'maildir' => $username . '/',
                'local_part' => explode('@', $username)[0], 'domain' => self::DOMAIN, 'active' => true, 'totp_secret' => $secret,
            ]));
            self::assertSame(1, db_insert('alias', [
                'address' => $username, 'goto' => 'target-' . explode('@', $username)[0] . '@external.example.com', 'domain' => self::DOMAIN, 'active' => true,
            ]));
        }
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->url = 'http://' . $address;
        $command = [PHP_BINARY];
        if (php_ini_loaded_file()) {
            array_push($command, '-c', php_ini_loaded_file());
        }
        array_push($command, '-d', 'session.save_path=' . $this->directory, '-d', 'log_errors=1', '-d', 'error_log=' . $this->directory . '/error.log', '-S', $address, __DIR__ . '/fixtures/xmlrpc-session-http-router.php');
        $env = getenv();
        $env['PFA_XMLRPC_TEST_CONFIG'] = $this->directory . '/config.json';
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
        foreach ([self::USER_A, self::USER_B] as $username) {
            db_execute('DELETE FROM ' . table_by_key('alias') . ' WHERE address = :username', ['username' => $username]);
            db_execute('DELETE FROM ' . table_by_key('mailbox') . ' WHERE username = :username', ['username' => $username]);
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

    public function testMailboxIdentityChangeRevokesRpcUntilFreshLogin(): void
    {
        self::assertTrue($this->rpc('login.login', [self::USER_A, 'fixture-password']));
        self::assertSame(['target-a@external.example.com'], $this->rpc('alias.get'));
        [$status] = $this->request('/users/login.php', [
            'CSRF_Token' => $this->token(), 'fUsername' => self::USER_B, 'fPassword' => 'fixture-password', 'lang' => 'en',
        ]);
        self::assertSame(302, $status);
        $state = $this->state();
        self::assertSame(self::USER_B, $state['username']);
        self::assertFalse($state['mfa_complete']);
        self::assertFalse($state['rpc_authorized']);
        $this->assertRpcUnavailable();
        // Finishing MFA must not restore the old RPC authorization either.
        if (time() % 30 === 29) {
            usleep(1100000);
        }
        [$status] = $this->request('/users/login-mfa.php', [
            'CSRF_Token' => $this->token(), 'fTOTP_code' => \OTPHP\TOTP::create(self::SECRET)->now(),
        ]);
        self::assertSame(302, $status);
        self::assertTrue($this->state()['mfa_complete']);
        $this->assertRpcUnavailable();
        self::assertFalse($this->rpc('login.login', [self::USER_B, 'fixture-password']));
        self::assertTrue($this->rpc('login.login', [self::USER_A, 'fixture-password']));
        self::assertSame(['target-a@external.example.com'], $this->rpc('alias.get'));
    }

    public function testLegacyPendingMfaCookieCannotUseRpcProxies(): void
    {
        [$status] = $this->request('/__legacy-pending');
        self::assertSame(200, $status);
        self::assertTrue($this->state()['rpc_authorized']);
        $this->assertRpcUnavailable();
        self::assertFalse($this->state()['rpc_authorized']);
        self::assertTrue($this->rpc('login.login', [self::USER_A, 'fixture-password']));
        self::assertSame(['target-a@external.example.com'], $this->rpc('alias.get'));
    }

    public function testCompletedWebLoginAlsoRequiresFreshRpcLogin(): void
    {
        self::assertTrue($this->rpc('login.login', [self::USER_A, 'fixture-password']));
        [$status] = $this->request('/users/login.php', [
            'CSRF_Token' => $this->token(), 'fUsername' => self::USER_A, 'fPassword' => 'fixture-password', 'lang' => 'en',
        ]);
        self::assertSame(302, $status);
        self::assertTrue($this->state()['mfa_complete']);
        self::assertFalse($this->state()['rpc_authorized']);
        $this->assertRpcUnavailable();
        self::assertTrue($this->rpc('login.login', [self::USER_A, 'fixture-password']));
        self::assertSame(['target-a@external.example.com'], $this->rpc('alias.get'));
    }

    private function assertRpcUnavailable(): void
    {
        foreach (['alias.get', 'vacation.getDetails', 'user.changePassword'] as $method) {
            [$status, $body] = $this->request('/xmlrpc.php', (string)new \Zend_XmlRpc_Request($method, []), 'text/xml');
            self::assertSame(200, $status, $body);
            $response = new \Zend_XmlRpc_Response();
            $response->loadXml($body);
            self::assertTrue($response->isFault(), $body);
            self::assertSame(620, $response->getFault()->getCode(), $body);
        }
    }

    private function rpc(string $method, array $params = [])
    {
        [$status, $body] = $this->request('/xmlrpc.php', (string)new \Zend_XmlRpc_Request($method, $params), 'text/xml');
        self::assertSame(200, $status, $body);
        $response = new \Zend_XmlRpc_Response();
        $response->loadXml($body);
        self::assertFalse($response->isFault(), $body);
        return $response->getReturnValue();
    }

    private function state(): array
    {
        [$status, $body] = $this->request('/__state');
        self::assertSame(200, $status, $body);
        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }

    private function token(): string
    {
        return $this->state()['token'];
    }

    private function request(string $path, array|string|null $post = null, string $contentType = 'application/x-www-form-urlencoded'): array
    {
        $options = ['method' => $post === null ? 'GET' : 'POST', 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10,
            'header' => "Cookie: {$this->cookie}\r\nContent-Type: {$contentType}\r\n"];
        if ($post !== null) {
            $options['content'] = is_array($post) ? http_build_query($post) : $post;
        }
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
