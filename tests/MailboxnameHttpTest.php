<?php

use PHPUnit\Framework\TestCase;

class MailboxnameHttpTest extends TestCase
{
    private const USERNAME = 'http@name-http.example';
    private const DOMAIN = 'name-http.example';
    private string $directory;
    private string $url;
    private string $cookie = '';
    private $server;

    protected function setUp(): void
    {
        self::assertSame(1, db_insert('domain', ['domain' => self::DOMAIN, 'description' => '', 'transport' => 'virtual', 'active' => 1]));
        self::assertSame(1, db_insert('mailbox', [
            'username' => self::USERNAME, 'password' => 'fixture', 'name' => '<b>Original</b>',
            'maildir' => 'name-http.example/http/', 'quota' => 0,
            'local_part' => 'http', 'domain' => self::DOMAIN, 'active' => 1,
        ]));
        $this->directory = sys_get_temp_dir() . '/pfa-security-list-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->directory));
        $config = Config::getInstance()->getAll();
        $config['totp'] = 'YES';
        $config['edit_mailbox_name'] = [self::DOMAIN];
        $config['mailbox_postedit_script'] = '';
        $config['logging'] = 'NO';
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
        array_push($command, '-d', 'session.save_path=' . $this->directory, '-d', 'log_errors=1', '-d', 'error_log=' . $this->directory . '/error.log', '-S', $address, __DIR__ . '/fixtures/mailbox-name-http-router.php');
        $env = getenv();
        $env['PFA_NAME_TEST_CONFIG'] = $this->directory . '/config.json';
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
        db_delete('mailbox', 'domain', self::DOMAIN);
        db_delete('domain', 'domain', self::DOMAIN);
        if (isset($this->directory) && is_dir($this->directory)) {
            foreach (new DirectoryIterator($this->directory) as $file) {
                if (!$file->isDot()) {
                    unlink($file->getPathname());
                }
            }
            rmdir($this->directory);
        }
    }

    public function testSharedEditorOnlyChangesOwnNameAndRejectsSpecialAdminAction(): void
    {
        $this->request('/__session');
        [$status, $html] = $this->request('/edit.php?table=mailboxname&edit=' . urlencode(self::USERNAME));
        self::assertSame(200, $status, $html);
        self::assertStringContainsString('&lt;b&gt;Original&lt;/b&gt;', $html);
        self::assertStringNotContainsString('name="value[password]"', $html);
        self::assertStringNotContainsString('name="value[quota]"', $html);
        self::assertStringNotContainsString('name="reset_totp"', $html);
        self::assertSame(1, preg_match('/name=[\'"]CSRF_Token[\'"] value=[\'"]([^\'"]+)/', $html, $token));
        [$status] = $this->request('/edit.php', [
            'table' => 'mailboxname', 'edit' => self::USERNAME, 'CSRF_Token' => $token[1],
            'reset_totp' => '1', 'value' => ['name' => 'Changed <script data-name-fixture>', 'active' => 0],
        ]);
        self::assertSame(302, $status);
        $mailbox = db_query_one('SELECT name, active FROM ' . table_by_key('mailbox') . ' WHERE username = ?', [self::USERNAME]);
        self::assertSame('Changed <script data-name-fixture>', $mailbox['name']);
        self::assertEquals(1, $mailbox['active']);
        [$status, $html] = $this->request('/edit.php?table=mailboxname&edit=' . urlencode(self::USERNAME));
        self::assertSame(200, $status, $html);
        self::assertStringContainsString('Changed &lt;script data-name-fixture&gt;', $html);
        self::assertStringNotContainsString('<script data-name-fixture>', $html);
        [$status, $html] = $this->request('/users/main.php');
        self::assertSame(200, $status, $html);
        self::assertStringContainsString('table=mailboxname', $html);
    }

    public function testInvalidCsrfAndAnonymousAccessDoNotChangeName(): void
    {
        $this->request('/__session');
        $this->request('/edit.php', [
            'table' => 'mailboxname', 'edit' => self::USERNAME,
            'CSRF_Token' => 'invalid', 'value' => ['name' => 'Forged'],
        ]);
        self::assertSame('<b>Original</b>', db_query_one('SELECT name FROM ' . table_by_key('mailbox') . ' WHERE username = ?', [self::USERNAME])['name']);
        $this->request('/__session?anonymous=yes');
        [$status] = $this->request('/edit.php?table=mailboxname&edit=' . urlencode(self::USERNAME));
        self::assertSame(302, $status);
    }

    private function request(string $path, ?array $post = null): array
    {
        $options = ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10,
            'header' => "Cookie: {$this->cookie}\r\n"];
        if ($post !== null) {
            $options['method'] = 'POST';
            $options['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $options['content'] = http_build_query($post);
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
