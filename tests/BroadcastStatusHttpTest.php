<?php

class BroadcastStatusHttpTest extends \PHPUnit\Framework\TestCase
{
    private string $directory;
    private string $url;
    private string $cookie = '';
    private int $jobId;
    private string $domain;
    private $server;

    protected function setUp(): void
    {
        $this->domain = 'broadcast-http-' . bin2hex(random_bytes(5)) . '.test';
        $this->directory = sys_get_temp_dir() . '/pfa-broadcast-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->directory));
        $config = Config::getInstance()->getAll();
        $config['sendmail'] = 'YES';
        $config['sendmail_all_admins'] = 'YES';
        $config['default_language'] = 'en';
        $config['language_hook'] = '';
        file_put_contents($this->directory . '/config.json', json_encode($config, JSON_THROW_ON_ERROR));

        db_insert('domain', ['domain' => $this->domain, 'description' => 'Broadcast HTTP test', 'transport' => 'virtual', 'active' => 1]);
        $this->jobId = BroadcastQueue::createJob(
            'broadcast-http-admin@example.test', 'sender@example.test', 'PostfixAdmin', 'HTTP test', 'Body',
            [$this->domain], true, ['bad@@example.test', 'valid@example.test']
        );
        BroadcastQueue::setWorkerMode(BroadcastQueue::MODE_DRY_RUN);

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->url = 'http://' . $address;
        $command = [PHP_BINARY];
        if (php_ini_loaded_file()) {
            array_push($command, '-c', php_ini_loaded_file());
        }
        array_push($command, '-d', 'session.save_path=' . $this->directory, '-d', 'log_errors=1', '-d', 'error_log=' . $this->directory . '/error.log', '-S', $address, __DIR__ . '/fixtures/broadcast-http-router.php');
        $env = getenv();
        $env['PFA_BROADCAST_TEST_CONFIG'] = $this->directory . '/config.json';
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
        BroadcastQueue::setWorkerMode(BroadcastQueue::MODE_LIVE);
        foreach (['broadcast_recipient', 'broadcast_job_domain'] as $table) {
            db_execute('DELETE FROM ' . table_by_key($table) . ' WHERE job_id = :id', ['id' => $this->jobId]);
        }
        db_execute('DELETE FROM ' . table_by_key('broadcast_job') . ' WHERE id = :id', ['id' => $this->jobId]);
        db_execute('DELETE FROM ' . table_by_key('domain') . ' WHERE domain = :domain', ['domain' => $this->domain]);
        foreach (new DirectoryIterator($this->directory) as $file) {
            if (!$file->isDot()) {
                unlink($file->getPathname());
            }
        }
        rmdir($this->directory);
    }

    public function testBusyAndConsecutivePostsRotateCsrfAndContinuePastInvalidRecipient(): void
    {
        [$status, $body] = $this->request('/__session');
        self::assertSame(200, $status, $body);
        $token = json_decode($body, true, 512, JSON_THROW_ON_ERROR)['token'];
        $lock = BroadcastQueue::acquireProcessLock();
        self::assertNotNull($lock);
        try {
            [$status, $body] = $this->process($token);
            self::assertSame(200, $status, $body);
            self::assertJson($body);
            $busy = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            self::assertTrue($busy['busy']);
            self::assertNotSame($token, $busy['csrf_token']);
        } finally {
            BroadcastQueue::releaseProcessLock($lock);
        }

        [$status, $body] = $this->process($busy['csrf_token']);
        self::assertSame(200, $status, $body);
        $first = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $first['failed']);
        self::assertSame(0, $first['sent']);
        self::assertNotSame($busy['csrf_token'], $first['csrf_token']);

        [$status] = $this->process($busy['csrf_token']);
        self::assertSame(419, $status);
        [$status, $body] = $this->process($first['csrf_token']);
        self::assertSame(200, $status, $body);
        $second = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('finished', $second['status']);
        self::assertSame(1, $second['sent']);
        self::assertSame(1, $second['failed']);
    }

    private function process(string $token): array
    {
        return $this->request('/broadcast-status.php', [
            'CSRF_Token' => $token, 'action' => 'process', 'job_id' => $this->jobId,
        ]);
    }

    private function request(string $path, ?array $post = null): array
    {
        $options = ['method' => $post === null ? 'GET' : 'POST', 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10,
            'header' => "Cookie: {$this->cookie}\r\nContent-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n"];
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
        return [(int)explode(' ', $http_response_header[0])[1], $body];
    }
}
