<?php

declare(strict_types=1);

use PostfixAdmin\VirtualVacation\VacationCli;

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
            '--config=/etc/postfixadmin/vacation-php.conf',
            '--postfixadmin-root',
            '/var/www/postfixadmin',
        ]);
        $this->true($arguments['check']);
        $this->same('/etc/postfixadmin/vacation-php.conf', $arguments['config']);
        $this->same('/var/www/postfixadmin', $arguments['postfixadmin_root']);
    }

    private function testGeneratedConfiguration(): void
    {
        $cli = new VacationCli();
        $directory = $this->temporaryDirectory();
        try {
            $path = $directory . '/vacation-php.conf';
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
            ]);
            file_put_contents($directory . '/vacation-php.conf', $rendered);
            $phpConfiguration = $cli->loadVacationConfig($directory . '/vacation-php.conf');
            $this->same('starttls', $phpConfiguration['values']['smtp_security']);
            $this->same(30, $phpConfiguration['values']['smtp_timeout']);
            $this->same('192.0.2.10', $phpConfiguration['values']['smtp_local_address']);
            $this->same('vacation', $phpConfiguration['values']['smtp_username']);
            $this->same('secret', $phpConfiguration['values']['smtp_password']);
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
