<?php

declare(strict_types=1);

use PostfixAdmin\VirtualVacation\VacationCli;
use PostfixAdmin\VirtualVacation\VacationMessageInspector;
use PostfixAdmin\VirtualVacation\VacationReplyComposer;
use PostfixAdmin\VirtualVacation\VacationRepository;

require_once dirname(__DIR__) . '/vacation.php';

final class VacationPhpTest
{
    private int $assertions = 0;

    public function run(): void
    {
        $this->testNoActionPrintsOneLine();
        $this->testArguments();
        $this->testGeneratedConfiguration();
        $this->testLegacyImport();
        $this->testPostfixAdminConfigurationLoading();
        $this->testAddressAndDomainHelpers();
        $this->testConfiguredHeloNeedsNoPrompt();
        $this->testDependencyResultsAreExplicit();
        $this->testMessageInspectionRules();
        $this->testMessageAddressSafety();
        $this->testHistoricalMessageFixtures();
        $this->testReplyComposition();
        $this->testVacationRepository();

        fwrite(STDOUT, "OK ({$this->assertions} assertions)" . PHP_EOL);
    }

    private function testNoActionPrintsOneLine(): void
    {
        $input = fopen('php://memory', 'r+');
        $output = fopen('php://memory', 'r+');
        $error = fopen('php://memory', 'r+');
        $cli = new VacationCli($input, $output, $error);
        $this->same(69, $cli->run(['vacation.php']));
        rewind($error);
        $lines = preg_split('/\R/', trim((string)stream_get_contents($error)));
        $this->same(1, count($lines));
    }

    private function testArguments(): void
    {
        $cli = new VacationCli();
        $arguments = $cli->parseArguments([
            'vacation.php',
            '--check',
            '--config=/etc/postfixadmin/vacation.ini',
            '--postfixadmin-root',
            '/var/www/postfixadmin',
        ]);
        $this->true($arguments['check']);
        $this->same('/etc/postfixadmin/vacation.ini', $arguments['config']);
        $this->same('/var/www/postfixadmin', $arguments['postfixadmin_root']);
        $inspection = $cli->parseArguments([
            'vacation.php',
            '--inspect-message=message.eml',
            '-f',
            'sender@example.org',
            '--',
            'user#example.org@autoreply.example.org',
        ]);
        $this->same('message.eml', $inspection['inspect_message']);
        $this->same('sender@example.org', $inspection['envelope_sender']);
        $this->same('user#example.org@autoreply.example.org', $inspection['recipient']);
        $transport = $cli->parseArguments([
            'vacation.php',
            '-t',
            'yes',
            '-f',
            'sender@example.org',
            '--',
            'user#example.org@autoreply.example.org',
        ]);
        $this->same('yes', $transport['transport_test']);
        $this->same('sender@example.org', $transport['envelope_sender']);
    }

    private function testGeneratedConfiguration(): void
    {
        $cli = new VacationCli();
        $directory = $this->temporaryDirectory();
        try {
            $path = $directory . '/vacation.ini';
            file_put_contents($path, $cli->renderConfig('/var/www/html/postfixadmin', 'localhost', 25, 'mail.example.org'));
            $loaded = $cli->loadVacationConfig($path);
            $this->same([], $loaded['warnings']);
            $this->same('/var/www/html/postfixadmin', $loaded['values']['postfixadmin_root']);
            $this->same('localhost', $loaded['values']['smtp_server']);
            $this->same(25, $loaded['values']['smtp_server_port']);
            $this->same('mail.example.org', $loaded['values']['smtp_helo']);
            $this->same('none', $loaded['values']['smtp_security']);
            $this->same(120, $loaded['values']['smtp_timeout']);
            $this->false($cli->isLegacyConfig($path));
            $this->same(realpath($path), $cli->findVacationConfig($path));
            putenv('VACATION_SMTP_PASSWORD=environment-secret');
            try {
                $loaded = $cli->loadVacationConfig($path);
                $this->same('environment-secret', $loaded['values']['smtp_password']);
            } finally {
                putenv('VACATION_SMTP_PASSWORD');
            }
        } finally {
            $this->removeDirectory($directory);
        }
    }

    private function testLegacyImport(): void
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
            $this->same('localhost', $loaded['values']['smtp_server']);
            $this->same(25, $loaded['values']['smtp_server_port']);
            $this->same('mail.example.org', $loaded['values']['smtp_helo']);
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
            $this->same('starttls', $phpConfiguration['values']['smtp_security']);
            $this->same(30, $phpConfiguration['values']['smtp_timeout']);
            $this->same('192.0.2.10', $phpConfiguration['values']['smtp_local_address']);
            $this->same('vacation', $phpConfiguration['values']['smtp_username']);
            $this->same('secret', $phpConfiguration['values']['smtp_password']);
            $this->same('/usr/sbin/sendmail', $phpConfiguration['values']['sendmail_path']);
            $this->same('+', $phpConfiguration['values']['recipient_delimiter']);
            $this->true($phpConfiguration['values']['message_custom_noreply_pattern']);
            $this->same('social|notification', $phpConfiguration['values']['message_noreply_pattern']);
            $this->same('Away', $phpConfiguration['values']['reply_friendly_from']);
            $this->true($phpConfiguration['values']['reply_account_name']);
            $this->same('d-m-Y', $phpConfiguration['values']['reply_date_format']);
            $this->false($phpConfiguration['values']['log_syslog']);
            $this->same('debug', $phpConfiguration['values']['log_level']);
            $this->true($phpConfiguration['values']['log_file_enabled']);
            $this->same('/var/log/custom-vacation.log', $phpConfiguration['values']['log_file']);
            $this->same(1, count($loaded['warnings']));
            $this->true($cli->isLegacyConfig($path));
        } finally {
            $this->removeDirectory($directory);
        }
    }

    private function testPostfixAdminConfigurationLoading(): void
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
            $this->same('local-secret', $configuration['database_password']);
            $this->same('autoreply.example.org', $configuration['vacation_domain']);
            $this->same('pfa_away', $configuration['resolved_tables']['vacation']);
            $this->same('pfa_alias', $configuration['resolved_tables']['alias']);
            $this->same([realpath($directory)], $cli->discoverPostfixAdminRoots($directory));
        } finally {
            $this->removeDirectory($directory);
        }
    }

    private function testAddressAndDomainHelpers(): void
    {
        $cli = new VacationCli();
        $this->same('example.org', $cli->baseDomain('mail.example.org'));
        $this->same('noreply@example.org', $cli->defaultTestSender('mail.example.org'));
        $this->true($cli->validEmailAddress('admin@example.org'));
        $this->false($cli->validEmailAddress("admin@example.org\nBcc: victim@example.org"));
        $this->false($cli->validEmailAddress('not-an-address'));
    }

    private function testConfiguredHeloNeedsNoPrompt(): void
    {
        $input = fopen('php://memory', 'r+');
        $output = fopen('php://memory', 'r+');
        $error = fopen('php://memory', 'r+');
        $cli = new VacationCli($input, $output, $error);
        $this->same('configured.example.org', $cli->resolveSmtpHelo(['smtp_helo' => 'configured.example.org']));
        rewind($output);
        $this->same('', stream_get_contents($output));
    }

    private function testDependencyResultsAreExplicit(): void
    {
        $cli = new VacationCli();
        $results = [];
        $cli->checkDependencies(['database_type' => 'sqlite'], $results);
        $names = array_map(static fn ($result) => $result->name, $results);
        $this->true(in_array('mbstring', $names, true));
        $this->true(in_array('mailparse', $names, true));
        $this->true(in_array('PDO', $names, true));
        $this->true(in_array('pdo_sqlite', $names, true));
    }

    private function testMessageInspectionRules(): void
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
        $this->true($eligible->eligible);
        $this->same('user@example.org', $eligible->envelopeRecipient);
        $this->same('sender@example.org', $eligible->from);
        $this->same('user@example.org, another@example.org', $eligible->to);

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
            $this->false($result->eligible);
        }
    }

    private function testMessageAddressSafety(): void
    {
        $inspector = new VacationMessageInspector();
        $base = [
            'From' => 'sender@example.org',
            'To' => 'user@example.org',
            'Message-ID' => '<message@example.org>',
        ];
        $this->false($inspector->inspectHeaders(
            $base,
            'user@example.org',
            'user@example.org',
        )->eligible);
        $this->false($inspector->inspectHeaders(
            array_replace($base, ['To' => 'sender@example.org']),
            'sender@example.org',
            'user@example.org',
        )->eligible);
        $this->false($inspector->inspectHeaders(
            $base + ['Reply-To' => 'mailer-daemon@example.org'],
            'other@example.org',
            'user@example.org',
        )->eligible);
        $this->false($inspector->inspectHeaders(
            $base,
            'other@example.org',
            'user@example.org',
            ['message_no_vacation_pattern' => 'user@example\.org'],
        )->eligible);
        $missingId = $base;
        unset($missingId['Message-ID']);
        $this->false($inspector->inspectHeaders(
            $missingId,
            'other@example.org',
            'user@example.org',
        )->eligible);
    }

    private function testHistoricalMessageFixtures(): void
    {
        if (!is_file(__DIR__ . '/test-email.txt')) {
            return;
        }
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
                $this->headersFromMessage(__DIR__ . '/' . $fixture),
                $sender,
                'david#example.org@autoreply.example.org',
                $configuration,
            );
            $this->same($expected, $result->eligible);
        }
    }

    private function testReplyComposition(): void
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
        $this->true(str_contains($message, 'To: sender@example.org'));
        $this->true(str_contains($message, 'From: Example User <user@example.org>'));
        $this->true(str_contains($message, 'Subject: Away: Original subject'));
        $this->true(str_contains($message, 'X-Loop: Postfix Admin Virtual Vacation'));
        $this->true(str_contains($message, 'Auto-Submitted: auto-replied'));
        $this->true(str_contains($message, 'Away from 01-08-2026 until 31-08-2026.'));
    }

    private function testVacationRepository(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            return;
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
        foreach (['user@example.org', 'bob@example.org', 'alice@example.org'] as $email) {
            $insert->execute([
                $email,
                'Away',
                'Body',
                '2026-01-01 00:00:00',
                '2026-12-31 23:59:59',
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
        $database->exec("INSERT INTO alias VALUES "
            . "('team@example.org', 'user@example.org,team#example.org@autoreply.example.org'), "
            . "('@catch.example', '@example.org')");
        $database->exec("INSERT INTO alias_domain VALUES ('alias.example', 'example.org')");
        $database->exec("INSERT INTO mailbox VALUES ('user@example.org', 'Example User')");
        $repository = new VacationRepository($database, [], 'sqlite');
        $this->same('user@example.org', $repository->findActiveVacation(
            'user@example.org',
            'autoreply.example.org',
        )['email']);
        $this->same('user@example.org', $repository->findActiveVacation(
            'team@example.org',
            'autoreply.example.org',
        )['email']);
        $this->same('bob@example.org', $repository->findActiveVacation(
            'bob@alias.example',
            'autoreply.example.org',
        )['email']);
        $this->same('alice@example.org', $repository->findActiveVacation(
            'alice@catch.example',
            'autoreply.example.org',
        )['email']);
        $this->same(null, $repository->findActiveVacation(
            'expired@example.org',
            'autoreply.example.org',
        ));
        $vacation = $repository->findActiveVacation('user@example.org', 'autoreply.example.org');
        $this->true($repository->claimNotification($vacation, 'sender@example.net'));
        $this->false($repository->claimNotification($vacation, 'sender@example.net'));
        $database->exec("UPDATE vacation SET interval_time = 60 WHERE email = 'user@example.org'");
        $database->exec("UPDATE vacation_notification SET notified_at = '2026-01-01 00:00:00'");
        $vacation = $repository->findActiveVacation('user@example.org', 'autoreply.example.org');
        $this->true($repository->claimNotification($vacation, 'sender@example.net'));
        $this->same('Example User', $repository->accountName('user@example.org'));
        $repository->forgetNotification('user@example.org', 'sender@example.net');
        $this->same(0, (int)$database->query('SELECT COUNT(*) FROM vacation_notification')->fetchColumn());
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

    private function same(mixed $expected, mixed $actual): void
    {
        ++$this->assertions;
        if ($expected !== $actual) {
            throw new RuntimeException(
                'Assertion failed: expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
            );
        }
    }

    private function true(mixed $value): void
    {
        $this->same(true, $value);
    }

    private function false(mixed $value): void
    {
        $this->same(false, $value);
    }
}

(new VacationPhpTest())->run();
