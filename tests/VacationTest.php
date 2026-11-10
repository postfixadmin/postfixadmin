<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PostfixAdmin\VirtualVacation\VacationCli;
use PostfixAdmin\VirtualVacation\VacationMessageInspector;
use PostfixAdmin\VirtualVacation\VacationReplyComposer;
use PostfixAdmin\VirtualVacation\VacationRepository;

require_once dirname(__DIR__) . '/VIRTUAL_VACATION/vacation.php';

final class VacationShortWriteStream
{
    public mixed $context;

    public static string $contents = '';

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        self::$contents = '';
        return true;
    }

    public function stream_write(string $data): int
    {
        $chunk = substr($data, 0, 3);
        self::$contents .= $chunk;
        return strlen($chunk);
    }

    public function stream_eof(): bool
    {
        return false;
    }
}

final class VacationTest extends TestCase
{
    public function testNoActionPrintsOneLine(): void
    {
        $input = fopen('php://memory', 'r+');
        $output = fopen('php://memory', 'r+');
        $error = fopen('php://memory', 'r+');
        $cli = new VacationCli($input, $output, $error);
        $this->assertSame(69, $cli->run(['vacation.php']));
        rewind($error);
        $lines = preg_split('/\R/', trim((string)stream_get_contents($error)));
        $this->assertSame(1, count($lines));
    }

    public function testArguments(): void
    {
        $cli = new VacationCli();
        $arguments = $cli->parseArguments([
            'vacation.php',
            '--check',
            '--config=/etc/postfixadmin/vacation.ini',
            '--postfixadmin-root',
            '/var/www/postfixadmin',
        ]);
        $this->assertTrue($arguments['check']);
        $this->assertSame('/etc/postfixadmin/vacation.ini', $arguments['config']);
        $this->assertSame('/var/www/postfixadmin', $arguments['postfixadmin_root']);
        $inspection = $cli->parseArguments([
            'vacation.php',
            '--inspect-message=message.eml',
            '-f',
            'sender@example.org',
            '--',
            'user#example.org@autoreply.example.org',
        ]);
        $this->assertSame('message.eml', $inspection['inspect_message']);
        $this->assertSame('sender@example.org', $inspection['envelope_sender']);
        $this->assertSame('user#example.org@autoreply.example.org', $inspection['recipient']);
        $transport = $cli->parseArguments([
            'vacation.php',
            '-t',
            'yes',
            '-f',
            'sender@example.org',
            '--',
            'user#example.org@autoreply.example.org',
        ]);
        $this->assertSame('yes', $transport['transport_test']);
        $this->assertSame('sender@example.org', $transport['envelope_sender']);
    }

    public function testGeneratedConfiguration(): void
    {
        $cli = new VacationCli();
        $directory = $this->temporaryDirectory();
        try {
            $path = $directory . '/vacation.ini';
            file_put_contents($path, $cli->renderConfig('/var/www/html/postfixadmin', 'localhost', 25, 'mail.example.org'));
            $loaded = $cli->loadVacationConfig($path);
            $this->assertSame([], $loaded['warnings']);
            $this->assertSame('/var/www/html/postfixadmin', $loaded['values']['postfixadmin_root']);
            $this->assertSame('localhost', $loaded['values']['smtp_server']);
            $this->assertSame(25, $loaded['values']['smtp_server_port']);
            $this->assertSame('mail.example.org', $loaded['values']['smtp_helo']);
            $this->assertSame('none', $loaded['values']['smtp_security']);
            $this->assertSame(120, $loaded['values']['smtp_timeout']);
            $this->assertSame(
                VacationMessageInspector::DEFAULT_NO_VACATION_PATTERN,
                $loaded['values']['message_no_vacation_pattern'],
            );
            $explicitlyEmpty = (string)file_get_contents($path)
                . PHP_EOL . '[message]' . PHP_EOL . 'no_vacation_pattern = ""' . PHP_EOL;
            file_put_contents($path, $explicitlyEmpty);
            $this->assertSame('', $cli->loadVacationConfig($path)['values']['message_no_vacation_pattern']);
            $this->assertFalse($cli->isLegacyConfig($path));
            $this->assertSame(realpath($path), $cli->findVacationConfig($path));
            putenv('VACATION_SMTP_PASSWORD=environment-secret');
            try {
                $loaded = $cli->loadVacationConfig($path);
                $this->assertSame('environment-secret', $loaded['values']['smtp_password']);
            } finally {
                putenv('VACATION_SMTP_PASSWORD');
            }
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function testLegacyImport(): void
    {
        $cli = new VacationCli();
        $directory = $this->temporaryDirectory();
        try {
            $path = $directory . '/vacation.conf';
            file_put_contents($path, implode(PHP_EOL, [
                "\$smtp_server = 'localhost';",
                '$smtp_server_port = 25;',
                "\$smtp_helo = 'mail.example.org';",
                "\$smtp_ssl = 'starttls';",
                "\$smtp_timeout = '30';",
                "\$smtp_client = '192.0.2.10';",
                "\$smtp_authid = 'vacation';",
                "\$smtp_authpwd = 'secret';",
                "\$sendmail_bin = '/usr/sbin/sendmail';",
                "\$recipient_delimiter = '+';",
                "\$custom_noreply_pattern = 1;",
                "\$noreply_pattern = 'social|notification';",
                "\$friendly_from = 'Away';",
                '$accountname_check = 1;',
                "\$replace_from = '<%From_Date>';",
                "\$replace_until = '<%Until_Date>';",
                "\$date_format = '%d-%m-%Y';",
                '$syslog = 0;',
                '$log_level = 2;',
                '$log_to_file = 1;',
                "\$logfile = '/var/log/custom-vacation.log';",
                "\$unsupported = \$ENV{'SECRET'};",
                '',
            ]));
            $loaded = $cli->loadLegacyConfig($path);
            $this->assertSame('localhost', $loaded['values']['smtp_server']);
            $this->assertSame(25, $loaded['values']['smtp_server_port']);
            $this->assertSame('mail.example.org', $loaded['values']['smtp_helo']);
            $rendered = $cli->renderConfig('/var/www/html/postfixadmin', 'smtp.example.org', 587, 'mail.example.org', [
                'security' => $loaded['values']['smtp_ssl'],
                'timeout' => $loaded['values']['smtp_timeout'],
                'local_address' => $loaded['values']['smtp_client'],
                'username' => $loaded['values']['smtp_authid'],
                'password' => $loaded['values']['smtp_authpwd'],
                'sendmail' => $loaded['values']['sendmail_bin'],
            ], [
                'recipient_delimiter' => $loaded['values']['recipient_delimiter'],
                'custom_noreply_pattern' => $loaded['values']['custom_noreply_pattern'],
                'noreply_pattern' => $loaded['values']['noreply_pattern'],
            ], [
                'friendly_from' => $loaded['values']['friendly_from'],
                'account_name' => $loaded['values']['accountname_check'],
                'replace_from' => $loaded['values']['replace_from'],
                'replace_until' => $loaded['values']['replace_until'],
                'date_format' => 'd-m-Y',
            ], [
                'syslog' => $loaded['values']['syslog'],
                'level' => 'debug',
                'file_enabled' => $loaded['values']['log_to_file'],
                'file' => $loaded['values']['logfile'],
            ]);
            file_put_contents($directory . '/vacation.ini', $rendered);
            $phpConfiguration = $cli->loadVacationConfig($directory . '/vacation.ini');
            $this->assertSame('starttls', $phpConfiguration['values']['smtp_security']);
            $this->assertSame(30, $phpConfiguration['values']['smtp_timeout']);
            $this->assertSame('192.0.2.10', $phpConfiguration['values']['smtp_local_address']);
            $this->assertSame('vacation', $phpConfiguration['values']['smtp_username']);
            $this->assertSame('secret', $phpConfiguration['values']['smtp_password']);
            $this->assertSame('/usr/sbin/sendmail', $phpConfiguration['values']['sendmail_path']);
            $this->assertSame('+', $phpConfiguration['values']['recipient_delimiter']);
            $this->assertTrue($phpConfiguration['values']['message_custom_noreply_pattern']);
            $this->assertSame('social|notification', $phpConfiguration['values']['message_noreply_pattern']);
            $this->assertSame('Away', $phpConfiguration['values']['reply_friendly_from']);
            $this->assertTrue($phpConfiguration['values']['reply_account_name']);
            $this->assertSame('d-m-Y', $phpConfiguration['values']['reply_date_format']);
            $this->assertFalse($phpConfiguration['values']['log_syslog']);
            $this->assertSame('debug', $phpConfiguration['values']['log_level']);
            $this->assertTrue($phpConfiguration['values']['log_file_enabled']);
            $this->assertSame('/var/log/custom-vacation.log', $phpConfiguration['values']['log_file']);
            $this->assertSame(1, count($loaded['warnings']));
            $this->assertTrue($cli->isLegacyConfig($path));
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function testPostfixAdminConfigurationLoading(): void
    {
        $cli = new VacationCli();
        $directory = $this->temporaryDirectory();
        try {
            file_put_contents($directory . '/config.inc.php', <<<'PHP'
<?php
global $CONF;
$CONF = [
    'configured' => true,
    'database_type' => 'mysqli',
    'database_host' => 'localhost',
    'database_user' => 'postfix',
    'database_password' => 'default',
    'database_name' => 'postfix',
    'database_prefix' => 'pfa_',
    'database_tables' => ['vacation' => 'away'],
    'vacation_domain' => 'autoreply.example.org',
];
require __DIR__ . '/config.local.php';
PHP);
            file_put_contents($directory . '/config.local.php', <<<'PHP'
<?php
$CONF['database_password'] = 'local-secret';
PHP);
            $configuration = $cli->loadPostfixAdminConfig($directory);
            $this->assertSame('local-secret', $configuration['database_password']);
            $this->assertSame('autoreply.example.org', $configuration['vacation_domain']);
            $this->assertSame('pfa_away', $configuration['resolved_tables']['vacation']);
            $this->assertSame('pfa_alias', $configuration['resolved_tables']['alias']);
            $this->assertSame([realpath($directory)], $cli->discoverPostfixAdminRoots($directory));
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function testAddressAndDomainHelpers(): void
    {
        $cli = new VacationCli();
        $this->assertSame('example.org', $cli->baseDomain('mail.example.org'));
        $this->assertSame('noreply@example.org', $cli->defaultTestSender('mail.example.org'));
        $this->assertTrue($cli->validEmailAddress('admin@example.org'));
        $this->assertFalse($cli->validEmailAddress("admin@example.org\nBcc: victim@example.org"));
        $this->assertFalse($cli->validEmailAddress('not-an-address'));
    }

    public function testConfiguredHeloNeedsNoPrompt(): void
    {
        $input = fopen('php://memory', 'r+');
        $output = fopen('php://memory', 'r+');
        $error = fopen('php://memory', 'r+');
        $cli = new VacationCli($input, $output, $error);
        $this->assertSame('configured.example.org', $cli->resolveSmtpHelo(['smtp_helo' => 'configured.example.org']));
        rewind($output);
        $this->assertSame('', stream_get_contents($output));
    }

    public function testConfigurationIsWrittenAtomicallyWithRestrictedMode(): void
    {
        $directory = $this->temporaryDirectory();
        try {
            $path = $directory . '/vacation.ini';
            $method = new ReflectionMethod(VacationCli::class, 'writeConfigurationFile');
            $method->invoke(new VacationCli(), $path, "[smtp]\npassword = secret\n");
            $this->assertSame("[smtp]\npassword = secret\n", file_get_contents($path));
            if (PHP_OS_FAMILY !== 'Windows') {
                clearstatcache(true, $path);
                $this->assertSame(0640, fileperms($path) & 0777);
            }
            $this->assertSame([], glob($directory . '/.vacation.ini.*') ?: []);
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function testCompleteWritesHandleShortStreamWrites(): void
    {
        $scheme = 'vacationshortwrite';
        if (in_array($scheme, stream_get_wrappers(), true)) {
            stream_wrapper_unregister($scheme);
        }
        stream_wrapper_register($scheme, VacationShortWriteStream::class);
        try {
            $stream = fopen($scheme . '://output', 'w');
            $this->assertTrue(is_resource($stream));
            $method = new ReflectionMethod(VacationCli::class, 'writeAll');
            $method->invoke(new VacationCli(), $stream, 'complete payload', 'write failed');
            fclose($stream);
            $this->assertSame('complete payload', VacationShortWriteStream::$contents);
        } finally {
            stream_wrapper_unregister($scheme);
        }
    }

    public function testRemoteSmtpClearsLegacyLoopbackBinding(): void
    {
        $method = new ReflectionMethod(VacationCli::class, 'resolveDeliveryConfiguration');
        $configuration = $method->invoke(new VacationCli(), [
            'smtp_server' => 'smtp.example.org',
            'smtp_local_address' => 'localhost',
        ], 'user@example.org');
        $this->assertSame('', $configuration['smtp_local_address']);
    }

    public function testDependencyResultsAreExplicit(): void
    {
        $cli = new VacationCli();
        $results = [];
        $cli->checkDependencies(['database_type' => 'sqlite'], $results);
        $names = array_map(static fn ($result) => $result->name, $results);
        $this->assertTrue(in_array('mbstring', $names, true));
        $this->assertTrue(in_array('mailparse', $names, true));
        $this->assertTrue(in_array('PDO', $names, true));
        $this->assertTrue(in_array('pdo_sqlite', $names, true));
    }

    public function testMessageInspectionRules(): void
    {
        $inspector = new VacationMessageInspector();
        $base = [
            'From' => 'Sender <sender@example.org>',
            'To' => "User <user@example.org>,\r\n another@example.org",
            'Subject' => 'Test message',
            'Message-ID' => '<message@example.org>',
        ];
        $configuration = [
            'vacation_domain' => 'autoreply.example.org',
            'recipient_delimiter' => '+',
        ];
        $eligible = $inspector->inspectHeaders(
            $base + ['Auto-Submitted' => 'no'],
            'sender@example.org',
            'user+tag#example.org@autoreply.example.org',
            $configuration,
        );
        $this->assertTrue($eligible->eligible);
        $this->assertSame('user@example.org', $eligible->envelopeRecipient);
        $this->assertSame('sender@example.org', $eligible->from);
        $this->assertSame('user@example.org, another@example.org', $eligible->to);

        $rejections = [
            ['X-Spam-Flag', 'YES'],
            ['X-Spam-Status', 'Yes, score=20'],
            ['X-Facebook-Notify', ''],
            ['X-Amazon-Mail-Relay-Type', 'notification'],
            ['Precedence', 'bulk'],
            ['X-Loop', 'Postfix Admin Virtual Vacation'],
            ['Auto-Submitted', 'auto-replied'],
            ['List-Id', '<list.example.org>'],
            ['List-Post', '<mailto:list@example.org>'],
            ['List-Unsubscribe', '<mailto:unsubscribe@example.org>'],
            ['X-Barracuda-Spam-Status', 'Yes'],
            ['X-DSPAM-Result', 'Blacklisted'],
            ['X-Virus-Status', 'infected'],
            ['X-Antivirus-Status', 'infected'],
            ['X-AVAS-Virus-Status', 'infected'],
            ['X-AVAS-Spam-Status', 'spam'],
            ['X-SpamTest-Status', 'spam'],
            ['X-CRM114-Status', 'spam'],
            ['X-Razor-Status', 'spam'],
            ['X-Pyzor-Status', 'spam'],
            ['X-OSBF-Lua-Score', '0.95 [S]'],
            ['X-Autogenerated', 'reply'],
            ['X-Auto-Response-Suppress', 'OOF'],
        ];
        foreach ($rejections as [$header, $value]) {
            $result = $inspector->inspectHeaders(
                $base + [$header => $value],
                'sender@example.org',
                'user#example.org@autoreply.example.org',
                $configuration,
            );
            $this->assertFalse($result->eligible);
        }
    }

    public function testMessageAddressSafety(): void
    {
        $inspector = new VacationMessageInspector();
        $base = [
            'From' => 'sender@example.org',
            'To' => 'user@example.org',
            'Message-ID' => '<message@example.org>',
        ];
        $this->assertFalse($inspector->inspectHeaders(
            $base,
            'user@example.org',
            'user@example.org',
        )->eligible);
        $this->assertFalse($inspector->inspectHeaders(
            array_replace($base, ['To' => 'sender@example.org']),
            'sender@example.org',
            'user@example.org',
        )->eligible);
        $this->assertFalse($inspector->inspectHeaders(
            $base + ['Reply-To' => 'mailer-daemon@example.org'],
            'other@example.org',
            'user@example.org',
        )->eligible);
        $this->assertFalse($inspector->inspectHeaders(
            $base,
            'other@example.org',
            'user@example.org',
            ['message_no_vacation_pattern' => 'user@example\.org'],
        )->eligible);
        $this->assertFalse($inspector->inspectHeaders(
            array_replace($base, ['To' => 'info@example.org']),
            'other@example.org',
            'user@example.org',
        )->eligible);
        $this->assertTrue($inspector->inspectHeaders(
            array_replace($base, ['To' => 'info@example.org']),
            'other@example.org',
            'user@example.org',
            ['message_no_vacation_pattern' => ''],
        )->eligible);
        $missingId = $base;
        unset($missingId['Message-ID']);
        $this->assertFalse($inspector->inspectHeaders(
            $missingId,
            'other@example.org',
            'user@example.org',
        )->eligible);
    }

    public function testMessageInspectionStopsAfterHeaders(): void
    {
        if (!extension_loaded('mailparse')) {
            $this->markTestSkipped('The mailparse extension is required');
        }
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, implode("\r\n", [
            'From: sender@example.org',
            'To: user@example.org',
            'Message-ID: <message@example.org>',
            '',
            str_repeat('body attachment data', 2000),
        ]));
        rewind($stream);
        $result = (new VacationMessageInspector())->inspectStream(
            $stream,
            'sender@example.org',
            'user@example.org',
            ['message_no_vacation_pattern' => ''],
        );
        $this->assertTrue($result->eligible);
        $this->assertFalse(feof($stream));
        fclose($stream);
    }

    public function testHistoricalMessageFixtures(): void
    {
        $fixtureDirectory = dirname(__DIR__) . '/VIRTUAL_VACATION/tests';
        $inspector = new VacationMessageInspector();
        $configuration = ['vacation_domain' => 'autoreply.example.org'];
        $cases = [
            ['test-email.txt', 'david1@example.org', true],
            ['asterisk-email.txt', 'www-data@palepurple.net', true],
            ['spam.txt', 'mary@ccr.org', false],
            ['facebook.txt', 'notification+meynbxsa@facebookmail.com', false],
            ['mailing-list.txt', 'fw-general-return@example.org', false],
            ['mail-myself.txt', 'david@example.org', false],
            ['teodor-smtp-envelope-headers.txt', 'david@example.org', false],
        ];
        foreach ($cases as [$fixture, $sender, $expected]) {
            $result = $inspector->inspectHeaders(
                $this->headersFromMessage($fixtureDirectory . '/' . $fixture),
                $sender,
                'david#example.org@autoreply.example.org',
                $configuration,
            );
            $this->assertSame($expected, $result->eligible);
        }
    }

    public function testReplyComposition(): void
    {
        $inspection = (new VacationMessageInspector())->inspectHeaders([
            'From' => 'Sender <sender@example.org>',
            'To' => 'user@example.org',
            'Subject' => 'Original subject',
            'Message-ID' => '<message@example.org>',
        ], 'sender@example.org', 'user@example.org');
        $message = (new VacationReplyComposer())->compose([
            'email' => 'user@example.org',
            'subject' => 'Away: $SUBJECT',
            'body' => 'Away from <%From_Date> until <%Until_Date>.',
            'activefrom' => '2026-08-01 00:00:00',
            'activeuntil' => '2026-08-31 23:59:59',
        ], $inspection, [
            'reply_account_name' => true,
            'reply_date_format' => 'd-m-Y',
        ], 'Example User');
        $this->assertTrue(str_contains($message, 'To: sender@example.org'));
        $this->assertTrue(str_contains($message, 'From: Example User <user@example.org>'));
        $this->assertTrue(str_contains($message, 'Subject: Away: Original subject'));
        $this->assertTrue(str_contains($message, 'X-Loop: Postfix Admin Virtual Vacation'));
        $this->assertTrue(str_contains($message, 'Auto-Submitted: auto-replied'));
        $this->assertTrue(str_contains($message, 'Away from 01-08-2026 until 31-08-2026.'));
    }

    public function testVacationRepository(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The pdo_sqlite extension is required');
        }
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE vacation ('
            . 'email TEXT PRIMARY KEY, subject TEXT, body TEXT, activefrom TEXT, activeuntil TEXT, '
            . 'interval_time INTEGER, active INTEGER)');
        $database->exec('CREATE TABLE vacation_notification ('
            . 'on_vacation TEXT, notified TEXT, notified_at TEXT DEFAULT CURRENT_TIMESTAMP, '
            . 'PRIMARY KEY (on_vacation, notified))');
        $database->exec('CREATE TABLE alias (address TEXT, goto TEXT)');
        $database->exec('CREATE TABLE alias_domain (alias_domain TEXT, target_domain TEXT)');
        $database->exec('CREATE TABLE mailbox (username TEXT, name TEXT)');
        $insert = $database->prepare(
            'INSERT INTO vacation VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $activeFrom = date('Y-m-d H:i:s', time() - 86400);
        $activeUntil = date('Y-m-d H:i:s', time() + 86400);
        foreach (['user@example.org', 'bob@example.org', 'alice@example.org'] as $email) {
            $insert->execute([
                $email,
                'Away',
                'Body',
                $activeFrom,
                $activeUntil,
                0,
                1,
            ]);
        }
        $insert->execute([
            'expired@example.org',
            'Away',
            'Body',
            '2025-01-01 00:00:00',
            '2025-12-31 23:59:59',
            0,
            1,
        ]);
        $insert->execute(['invalid-date@example.org', 'Away', 'Body', null, null, 0, 1]);
        $database->exec("INSERT INTO alias VALUES "
            . "('team@example.org', 'user@example.org,team#example.org@autoreply.example.org'), "
            . "('@catch.example', '@example.org')");
        $database->exec("INSERT INTO alias_domain VALUES ('alias.example', 'example.org')");
        $database->exec("INSERT INTO mailbox VALUES ('user@example.org', 'Example User')");
        $repository = new VacationRepository($database, [], 'sqlite');
        $this->assertSame('user@example.org', $repository->findActiveVacation(
            'user@example.org',
            'autoreply.example.org',
        )['email']);
        $this->assertSame('user@example.org', $repository->findActiveVacation(
            'team@example.org',
            'autoreply.example.org',
        )['email']);
        $this->assertSame('bob@example.org', $repository->findActiveVacation(
            'bob@alias.example',
            'autoreply.example.org',
        )['email']);
        $this->assertSame('alice@example.org', $repository->findActiveVacation(
            'alice@catch.example',
            'autoreply.example.org',
        )['email']);
        $this->assertSame(null, $repository->findActiveVacation(
            'expired@example.org',
            'autoreply.example.org',
        ));
        $this->assertNull($repository->findActiveVacation(
            'invalid-date@example.org',
            'autoreply.example.org',
        ));
        $vacation = $repository->findActiveVacation('user@example.org', 'autoreply.example.org');
        $this->assertTrue($repository->claimNotification($vacation, 'sender@example.net'));
        $this->assertFalse($repository->claimNotification($vacation, 'sender@example.net'));
        $database->exec("UPDATE vacation SET interval_time = 60 WHERE email = 'user@example.org'");
        $database->exec("UPDATE vacation_notification SET notified_at = '2026-01-01 00:00:00'");
        $vacation = $repository->findActiveVacation('user@example.org', 'autoreply.example.org');
        $this->assertTrue($repository->claimNotification($vacation, 'sender@example.net'));
        $this->assertFalse($repository->claimNotification($vacation, 'sender@example.net'));
        $newActiveFrom = date('Y-m-d H:i:s', time() - 3600);
        $oldNotification = date('Y-m-d H:i:s', time() - 7200);
        $statement = $database->prepare(
            "UPDATE vacation SET activefrom = ?, interval_time = 0 WHERE email = 'user@example.org'"
        );
        $statement->execute([$newActiveFrom]);
        $statement = $database->prepare('UPDATE vacation_notification SET notified_at = ?');
        $statement->execute([$oldNotification]);
        $vacation = $repository->findActiveVacation('user@example.org', 'autoreply.example.org');
        $this->assertTrue($repository->claimNotification($vacation, 'sender@example.net'));
        $this->assertSame('Example User', $repository->accountName('user@example.org'));
        $repository->forgetNotification('user@example.org', 'sender@example.net');
        $this->assertSame(0, (int)$database->query('SELECT COUNT(*) FROM vacation_notification')->fetchColumn());
    }

    public function testTransportExitStatuses(): void
    {
        $output = fopen('php://memory', 'r+');
        $error = fopen('php://memory', 'r+');
        $cli = new VacationCli(null, $output, $error);
        $this->assertSame(64, $cli->run(['vacation.php', '--unknown-option']));
        $this->assertSame(78, $cli->run([
            'vacation.php',
            '--config',
            '/path/that/does/not/exist/vacation.ini',
            '-f',
            'sender@example.net',
            '--',
            'user@example.org',
        ]));
    }

    public function testCompleteTransport(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('The process-based SMTP transport test requires a Unix-like platform');
        }
        if (!extension_loaded('mailparse')) {
            $this->markTestSkipped('The mailparse extension is required');
        }
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The pdo_sqlite extension is required');
        }

        $directory = $this->temporaryDirectory();
        try {
            $databasePath = $directory . '/vacation.sqlite';
            $deliveredPath = $directory . '/delivered.eml';

            file_put_contents($directory . '/config.inc.php', '<?php' . PHP_EOL . '$CONF = [' . PHP_EOL
                . "    'database_type' => 'sqlite'," . PHP_EOL
                . "    'database_name' => " . var_export($databasePath, true) . ',' . PHP_EOL
                . "    'vacation_domain' => 'autoreply.example.org'," . PHP_EOL
                . '];' . PHP_EOL
                . "require __DIR__ . '/config.local.php';" . PHP_EOL);
            file_put_contents($directory . '/config.local.php', "<?php\n");

            $database = new PDO('sqlite:' . $databasePath);
            $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $database->exec('CREATE TABLE vacation ('
                . 'email TEXT PRIMARY KEY, subject TEXT, body TEXT, activefrom TEXT, activeuntil TEXT, '
                . 'interval_time INTEGER, active INTEGER)');
            $database->exec('CREATE TABLE vacation_notification ('
                . 'on_vacation TEXT, notified TEXT, notified_at TEXT DEFAULT CURRENT_TIMESTAMP, '
                . 'PRIMARY KEY (on_vacation, notified))');
            $database->exec('CREATE TABLE alias (address TEXT, goto TEXT)');
            $database->exec('CREATE TABLE alias_domain (alias_domain TEXT, target_domain TEXT)');
            $database->exec('CREATE TABLE mailbox (username TEXT, name TEXT)');
            $insert = $database->prepare('INSERT INTO vacation VALUES (?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([
                'user@example.org',
                'Away: $SUBJECT',
                'I am away.',
                date('Y-m-d H:i:s', time() - 3600),
                date('Y-m-d H:i:s', time() + 3600),
                3600,
                1,
            ]);
            $database->exec("INSERT INTO mailbox VALUES ('user@example.org', 'Example User')");

            $configurationPath = $directory . '/vacation.ini';
            [$smtpProcess, $smtpPort] = $this->startSmtpServer($directory, $deliveredPath);
            $this->writeTransportConfiguration($configurationPath, $directory, $smtpPort);
            $message = implode("\r\n", [
                'From: Sender <sender@example.net>',
                'To: User <user@example.org>',
                'Subject: Integration test',
                'Message-ID: <integration@example.net>',
                '',
                'Message body.',
                '',
            ]);

            $this->assertSame(0, $this->runTransport($configurationPath, $message, 'sender@example.net'));
            $this->assertSame(0, proc_close($smtpProcess));
            $delivered = file_get_contents($deliveredPath);
            $this->assertTrue(is_string($delivered));
            $this->assertTrue(str_contains((string)$delivered, 'To: sender@example.net'));
            $this->assertTrue(str_contains((string)$delivered, 'Subject: Away: Integration test'));
            $this->assertSame(1, (int)$database->query('SELECT COUNT(*) FROM vacation_notification')->fetchColumn());

            $deliveredSize = filesize($deliveredPath);
            $this->assertSame(0, $this->runTransport($configurationPath, $message, 'sender@example.net'));
            clearstatcache(true, $deliveredPath);
            $this->assertSame($deliveredSize, filesize($deliveredPath));

            $this->writeTransportConfiguration($configurationPath, $directory, $smtpPort);
            $this->assertSame(75, $this->runTransport($configurationPath, $message, 'failure@example.net'));
            $statement = $database->query(
                "SELECT COUNT(*) FROM vacation_notification WHERE notified = 'failure@example.net'"
            );
            $this->assertSame(0, (int)$statement->fetchColumn());
        } finally {
            $this->removeDirectory($directory);
        }
    }

    /** @return array{0: resource, 1: int} */
    private function startSmtpServer(string $directory, string $deliveredPath): array
    {
        $readyPath = $directory . '/smtp-port';
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/fixtures/VacationMockSmtp.php', $readyPath, $deliveredPath],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start the mock SMTP server');
        }
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        for ($attempt = 0; $attempt < 200 && !is_file($readyPath); ++$attempt) {
            usleep(10000);
        }
        $port = is_file($readyPath) ? filter_var(trim((string)file_get_contents($readyPath)), FILTER_VALIDATE_INT) : false;
        if ($port === false) {
            proc_terminate($process);
            proc_close($process);
            throw new RuntimeException('The mock SMTP server did not become ready');
        }
        return [$process, $port];
    }

    private function writeTransportConfiguration(string $path, string $root, int $smtpPort): void
    {
        file_put_contents($path, implode(PHP_EOL, [
            '[postfixadmin]',
            'root = "' . addcslashes($root, '\\"') . '"',
            '[smtp]',
            'server = "127.0.0.1"',
            'port = ' . $smtpPort,
            'helo = "vacation.test"',
            '[logging]',
            'syslog = false',
            '',
        ]));
    }

    private function runTransport(string $configurationPath, string $message, string $sender): int
    {
        $input = fopen('php://memory', 'r+');
        fwrite($input, $message);
        rewind($input);
        return (new VacationCli($input, fopen('php://memory', 'r+'), fopen('php://memory', 'r+')))->run([
            'vacation.php',
            '--config',
            $configurationPath,
            '-f',
            $sender,
            '--',
            'user#example.org@autoreply.example.org',
        ]);
    }

    /** @return array<string, list<string>> */
    private function headersFromMessage(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException("Could not read fixture: {$path}");
        }
        $headers = [];
        $current = null;
        foreach ($lines as $line) {
            if ($line === '' || $line === "\r") {
                break;
            }
            if (preg_match('/^[ \t]+(.*)$/', $line, $matches) && $current !== null) {
                $last = count($headers[$current]) - 1;
                $headers[$current][$last] .= ' ' . trim($matches[1]);
                continue;
            }
            if (!str_contains($line, ':')) {
                $current = null;
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $current = strtolower(trim($name));
            $headers[$current][] = trim($value);
        }
        return $headers;
    }

    private function temporaryDirectory(): string
    {
        $path = sys_get_temp_dir() . '/postfixadmin-vacation-php-' . bin2hex(random_bytes(8));
        if (!mkdir($path, 0700, true)) {
            throw new RuntimeException("Could not create test directory: {$path}");
        }
        return $path;
    }

    private function removeDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($directory);
    }
}
