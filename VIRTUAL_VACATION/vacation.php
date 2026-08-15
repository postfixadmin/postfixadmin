#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * PostfixAdmin Virtual Vacation modernization prototype for PHP CLI.
 *
 * This initial version provides configuration discovery, generation,
 * diagnostics, and a simple interactive SMTP test. It deliberately does not
 * process vacation mail yet and must not replace vacation.pl in Postfix
 * master.cf.
 *
 * See VIRTUAL_VACATION/Contributions.txt for contributor credits.
 */

namespace PostfixAdmin\VirtualVacation;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

final class CheckResult
{
    public function __construct(
        public readonly string $section,
        public readonly string $name,
        public readonly string $status,
        public readonly string $detail = '',
    ) {
    }
}

final class VacationCli
{
    public const VERSION = '0.1.0';

    /** @var list<string> */
    private const TABLE_KEYS = ['vacation', 'vacation_notification', 'alias', 'alias_domain', 'mailbox'];

    /** @var resource|null */
    private $input;

    /** @var resource|null */
    private $output;

    /** @var resource|null */
    private $errorOutput;

    /**
     * @param resource|null $input
     * @param resource|null $output
     * @param resource|null $errorOutput
     */
    public function __construct($input = null, $output = null, $errorOutput = null)
    {
        $this->input = $input ?? STDIN;
        $this->output = $output ?? STDOUT;
        $this->errorOutput = $errorOutput ?? STDERR;
    }

    /** @param list<string> $argv */
    public function run(array $argv): int
    {
        try {
            $arguments = $this->parseArguments($argv);

            if ($arguments['version']) {
                $this->write(self::VERSION . PHP_EOL);
                return 0;
            }
            if ($arguments['init_config']) {
                return $this->initConfig($arguments);
            }
            if ($arguments['check_dependencies']) {
                return $this->runCheck($arguments, true);
            }
            if ($arguments['test']) {
                return $this->runTest($arguments);
            }
            if ($arguments['show_config_path']) {
                return $this->showConfigPath($arguments);
            }
            if ($arguments['check']) {
                return $this->runCheck($arguments, false);
            }

            $this->writeError(
                'This initial version provides setup and diagnostics only; it does not send vacation replies. '
                . 'Use --init-config, --check, or --test; do not install it in Postfix master.cf yet.' . PHP_EOL
            );
            return 69;
        } catch (Throwable $exception) {
            $this->writeError('ERROR: ' . $exception->getMessage() . PHP_EOL);
            return 1;
        }
    }

    /**
     * @param list<string> $argv
     * @return array<string, bool|string|null>
     */
    public function parseArguments(array $argv): array
    {
        $arguments = [
            'check' => false,
            'check_dependencies' => false,
            'init_config' => false,
            'test' => false,
            'show_config_path' => false,
            'version' => false,
            'config' => null,
            'import_legacy' => null,
            'postfixadmin_root' => null,
            'non_interactive' => false,
            'force' => false,
            'envelope_sender' => null,
            'recipient' => null,
        ];
        $actions = [
            '--check' => 'check',
            '--check-dependencies' => 'check_dependencies',
            '--init-config' => 'init_config',
            '--test' => 'test',
            '--show-config-path' => 'show_config_path',
        ];
        $values = [
            '--config' => 'config',
            '--import-legacy' => 'import_legacy',
            '--postfixadmin-root' => 'postfixadmin_root',
            '-f' => 'envelope_sender',
        ];
        $selectedActions = 0;
        $positional = false;

        for ($index = 1, $count = count($argv); $index < $count; ++$index) {
            $argument = $argv[$index];
            if ($argument === '--') {
                $positional = true;
                continue;
            }
            if (!$positional && isset($actions[$argument])) {
                $arguments[$actions[$argument]] = true;
                ++$selectedActions;
                continue;
            }
            if (!$positional && $argument === '--non-interactive') {
                $arguments['non_interactive'] = true;
                continue;
            }
            if (!$positional && $argument === '--force') {
                $arguments['force'] = true;
                continue;
            }
            if (!$positional && $argument === '--version') {
                $arguments['version'] = true;
                continue;
            }
            if (!$positional && isset($values[$argument])) {
                if (!isset($argv[$index + 1])) {
                    throw new RuntimeException("Missing value for {$argument}");
                }
                $arguments[$values[$argument]] = $argv[++$index];
                continue;
            }
            if (!$positional && str_starts_with($argument, '--') && str_contains($argument, '=')) {
                [$name, $value] = explode('=', $argument, 2);
                if (isset($values[$name])) {
                    $arguments[$values[$name]] = $value;
                    continue;
                }
            }
            if (!$positional && str_starts_with($argument, '-')) {
                throw new RuntimeException("Unknown option: {$argument}");
            }
            if ($arguments['recipient'] !== null) {
                throw new RuntimeException("Unexpected argument: {$argument}");
            }
            $arguments['recipient'] = $argument;
        }

        if ($selectedActions > 1) {
            throw new RuntimeException('Select only one action');
        }
        return $arguments;
    }

    /** @return list<string> */
    public function defaultConfigPaths(): array
    {
        return [
            '/etc/mail/postfixadmin/vacation-php.conf',
            '/etc/postfixadmin/vacation-php.conf',
            getcwd() . DIRECTORY_SEPARATOR . 'vacation-php.conf',
        ];
    }

    public function findVacationConfig(?string $explicit): ?string
    {
        $candidates = $explicit !== null ? [$explicit] : $this->defaultConfigPaths();
        foreach ($candidates as $candidate) {
            $expanded = $this->expandHome($candidate);
            if (is_file($expanded)) {
                return realpath($expanded) ?: $expanded;
            }
        }
        return null;
    }

    /** @return list<string> */
    public function discoverPostfixAdminRoots(?string $explicit = null): array
    {
        $candidates = [];
        $environment = getenv('POSTFIXADMIN_ROOT');
        if ($explicit !== null && $explicit !== '') {
            $candidates[] = $explicit;
        } elseif (is_string($environment) && $environment !== '') {
            $candidates[] = $environment;
        } else {
            foreach ([getcwd(), __DIR__] as $base) {
                if ($base === false) {
                    continue;
                }
                do {
                    $candidates[] = $base;
                    $parent = dirname($base);
                    if ($parent === $base) {
                        break;
                    }
                    $base = $parent;
                } while (true);
            }
            array_push(
                $candidates,
                '/var/www/html/postfixadmin',
                '/var/www/postfixadmin',
                '/usr/share/postfixadmin',
                '/opt/postfixadmin',
            );
        }

        $found = [];
        foreach ($candidates as $candidate) {
            $candidate = $this->expandHome($candidate);
            $resolved = realpath($candidate);
            if ($resolved === false) {
                continue;
            }
            $key = strtolower(str_replace('\\', '/', $resolved));
            if (isset($found[$key])) {
                continue;
            }
            if (is_file($resolved . '/config.inc.php') && is_file($resolved . '/config.local.php')) {
                $found[$key] = $resolved;
            }
        }
        return array_values($found);
    }

    /** @param list<string> $roots */
    public function choosePostfixAdminRoot(array $roots, bool $nonInteractive): string
    {
        if ($roots === []) {
            throw new RuntimeException('No PostfixAdmin installation with config.local.php was found');
        }
        if (count($roots) === 1) {
            return $roots[0];
        }
        if ($nonInteractive) {
            throw new RuntimeException(
                'Multiple PostfixAdmin installations found: ' . implode(', ', $roots)
                . '; use --postfixadmin-root'
            );
        }

        $this->write('PostfixAdmin installations found:' . PHP_EOL);
        foreach ($roots as $index => $root) {
            $this->write(sprintf('  %d. %s%s', $index + 1, $root, PHP_EOL));
        }
        do {
            $selected = $this->prompt('Select installation', '1');
            if (ctype_digit($selected) && (int)$selected >= 1 && (int)$selected <= count($roots)) {
                return $roots[(int)$selected - 1];
            }
            $this->write('Invalid selection.' . PHP_EOL);
        } while (true);
    }

    /** @return array<string, mixed> */
    public function loadPostfixAdminConfig(string $root): array
    {
        $configFile = $root . DIRECTORY_SEPARATOR . 'config.inc.php';
        $localFile = $root . DIRECTORY_SEPARATOR . 'config.local.php';
        if (!is_file($configFile) || !is_file($localFile)) {
            throw new RuntimeException("Invalid PostfixAdmin root: {$root}");
        }

        $previous = $GLOBALS['CONF'] ?? null;
        $hadPrevious = array_key_exists('CONF', $GLOBALS);
        $GLOBALS['CONF'] = [];
        try {
            require $configFile;
            $configuration = $GLOBALS['CONF'];
        } finally {
            if ($hadPrevious) {
                $GLOBALS['CONF'] = $previous;
            } else {
                unset($GLOBALS['CONF']);
            }
        }
        if (!is_array($configuration)) {
            throw new RuntimeException('PostfixAdmin configuration did not produce $CONF');
        }

        $prefix = (string)($configuration['database_prefix'] ?? '');
        $mapping = is_array($configuration['database_tables'] ?? null)
            ? $configuration['database_tables']
            : [];
        $configuration['resolved_tables'] = [];
        foreach (self::TABLE_KEYS as $key) {
            $configuration['resolved_tables'][$key] = $prefix . (string)($mapping[$key] ?? $key);
        }
        return $configuration;
    }

    /** @return array{values: array<string, mixed>, warnings: list<string>} */
    public function loadVacationConfig(string $path): array
    {
        $parsed = parse_ini_file($path, true, INI_SCANNER_RAW);
        if ($parsed === false) {
            throw new RuntimeException("Invalid INI configuration: {$path}");
        }
        $warnings = [];
        $values = [];
        if (isset($parsed['postfixadmin']) && is_array($parsed['postfixadmin'])) {
            $values['postfixadmin_root'] = trim((string)($parsed['postfixadmin']['root'] ?? ''));
        } else {
            $warnings[] = 'missing [postfixadmin] section';
        }
        if (isset($parsed['database']) && is_array($parsed['database'])) {
            $mapping = [
                'type' => 'database_type',
                'host' => 'database_host',
                'port' => 'database_port',
                'socket' => 'database_socket',
                'name' => 'database_name',
                'user' => 'database_user',
                'password' => 'database_password',
            ];
            foreach ($mapping as $iniKey => $internalKey) {
                if (array_key_exists($iniKey, $parsed['database'])) {
                    $values[$internalKey] = (string)$parsed['database'][$iniKey];
                }
            }
        }
        if (isset($parsed['vacation'])
            && is_array($parsed['vacation'])
            && array_key_exists('domain', $parsed['vacation'])
        ) {
            $values['vacation_domain'] = (string)$parsed['vacation']['domain'];
        }
        if (isset($parsed['smtp']) && is_array($parsed['smtp'])) {
            $values['smtp_server'] = trim((string)($parsed['smtp']['server'] ?? 'localhost'));
            $values['smtp_server_port'] = $this->validPort($parsed['smtp']['port'] ?? 25);
            $values['smtp_helo'] = trim((string)($parsed['smtp']['helo'] ?? ''));
            $values['smtp_security'] = $this->normalizeSmtpSecurity($parsed['smtp']['security'] ?? 'none');
            $values['smtp_timeout'] = $this->validTimeout($parsed['smtp']['timeout'] ?? 120);
            $values['smtp_local_address'] = trim((string)($parsed['smtp']['local_address'] ?? ''));
            $values['smtp_username'] = (string)($parsed['smtp']['username'] ?? '');
            $values['smtp_password'] = (string)($parsed['smtp']['password'] ?? '');
            $environmentPassword = getenv('VACATION_SMTP_PASSWORD');
            if (is_string($environmentPassword) && $environmentPassword !== '') {
                $values['smtp_password'] = $environmentPassword;
            }
        } else {
            $warnings[] = 'missing [smtp] section; localhost:25 defaults will be used';
            $values['smtp_server'] = 'localhost';
            $values['smtp_server_port'] = 25;
            $values['smtp_helo'] = '';
            $values['smtp_security'] = 'none';
            $values['smtp_timeout'] = 120;
            $values['smtp_local_address'] = '';
            $values['smtp_username'] = '';
            $values['smtp_password'] = '';
        }
        if (isset($parsed['logging']) && is_array($parsed['logging'])) {
            $values['log_syslog'] = filter_var(
                $parsed['logging']['syslog'] ?? true,
                FILTER_VALIDATE_BOOL,
                FILTER_NULL_ON_FAILURE,
            ) ?? true;
            $values['log_level'] = strtolower(trim((string)($parsed['logging']['level'] ?? 'info')));
        }
        return ['values' => $values, 'warnings' => $warnings];
    }

    /** @return array{values: array<string, bool|int|string>, warnings: list<string>} */
    public function loadLegacyConfig(string $path): array
    {
        $contents = file($path, FILE_IGNORE_NEW_LINES);
        if ($contents === false) {
            throw new RuntimeException("Could not read legacy configuration: {$path}");
        }
        $values = [];
        $warnings = [];
        foreach ($contents as $index => $line) {
            if (!preg_match('/^\s*\$([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*?)\s*;\s*(?:#.*)?$/', $line, $matches)) {
                continue;
            }
            try {
                $values[$matches[1]] = $this->parsePerlValue($matches[2]);
            } catch (RuntimeException $exception) {
                $warnings[] = 'line ' . ($index + 1) . ': ' . $exception->getMessage();
            }
        }
        return ['values' => $values, 'warnings' => $warnings];
    }

    public function isLegacyConfig(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        try {
            while (($line = fgets($handle)) !== false) {
                if (preg_match('/^\s*\$[A-Za-z_][A-Za-z0-9_]*\s*=.*;/', $line)) {
                    return true;
                }
            }
        } finally {
            fclose($handle);
        }
        return false;
    }

    /** @param array<string, mixed> $smtpOptions */
    public function renderConfig(string $root, string $server, int $port, string $helo, array $smtpOptions = []): string
    {
        $lines = [
            '# PostfixAdmin Virtual Vacation PHP configuration',
            '# Database settings and vacation_domain are inherited from PostfixAdmin.',
            '',
            '[postfixadmin]',
            'root = ' . $this->iniValue(str_replace('\\', '/', $root)),
            '',
            '[smtp]',
            'server = ' . $this->iniValue($server),
            "port = {$port}",
            'helo = ' . $this->iniValue($helo),
        ];
        $optional = [
            'security' => $this->normalizeSmtpSecurity($smtpOptions['security'] ?? 'none'),
            'timeout' => $this->validTimeout($smtpOptions['timeout'] ?? 120),
            'local_address' => trim((string)($smtpOptions['local_address'] ?? '')),
            'username' => (string)($smtpOptions['username'] ?? ''),
            'password' => (string)($smtpOptions['password'] ?? ''),
        ];
        if ($optional['security'] !== 'none') {
            $lines[] = 'security = ' . $this->iniValue($optional['security']);
        }
        if ($optional['timeout'] !== 120) {
            $lines[] = 'timeout = ' . $optional['timeout'];
        }
        foreach (['local_address', 'username', 'password'] as $key) {
            if ($optional[$key] !== '') {
                $lines[] = $key . ' = ' . $this->iniValue((string)$optional[$key]);
            }
        }
        array_push(
            $lines,
            '',
            '[logging]',
            'syslog = true',
            'level = info',
            '',
        );
        return implode(PHP_EOL, $lines);
    }

    /** @param array<string, bool|string|null> $arguments */
    private function initConfig(array $arguments): int
    {
        $roots = $this->discoverPostfixAdminRoots($this->nullableString($arguments['postfixadmin_root']));
        $root = $this->choosePostfixAdminRoot($roots, (bool)$arguments['non_interactive']);
        $source = $this->loadPostfixAdminConfig($root);

        $this->write("PostfixAdmin configuration: {$root}/config.local.php" . PHP_EOL);
        $this->write('Database: ' . $this->normalizeDatabaseType($source['database_type'] ?? '')
            . ' / ' . ($source['database_name'] ?? '') . PHP_EOL);
        $this->write('Database host: ' . (($source['database_host'] ?? '') ?: 'local socket/default') . PHP_EOL);
        $this->write('Database user: ' . ($source['database_user'] ?? '') . PHP_EOL);
        $this->write('Database password: '
            . (($source['database_password'] ?? '') !== '' ? 'configured (hidden)' : 'empty') . PHP_EOL);
        $this->write('Vacation domain: ' . ($source['vacation_domain'] ?? '') . PHP_EOL);

        $legacy = [];
        $legacyPath = $this->nullableString($arguments['import_legacy']);
        if ($legacyPath !== null) {
            $legacyPath = $this->expandHome($legacyPath);
            if (!is_file($legacyPath)) {
                throw new RuntimeException("Legacy configuration not found: {$legacyPath}");
            }
            $loaded = $this->loadLegacyConfig($legacyPath);
            $legacy = $loaded['values'];
            $this->write("Legacy configuration: {$legacyPath}" . PHP_EOL);
            foreach ($loaded['warnings'] as $warning) {
                $this->writeError("WARNING: {$warning}" . PHP_EOL);
            }
        }

        $nonInteractive = (bool)$arguments['non_interactive'];
        $server = $this->promptValue('SMTP server', (string)($legacy['smtp_server'] ?? 'localhost'), $nonInteractive);
        $port = $this->validPort(
            $this->promptValue('SMTP port', (string)($legacy['smtp_server_port'] ?? 25), $nonInteractive)
        );
        $defaultHelo = (string)($legacy['smtp_helo'] ?? $this->detectedFqdn() ?? 'localhost.localdomain');
        $helo = $this->promptValue('SMTP HELO', $defaultHelo, $nonInteractive);
        $smtpOptions = [
            'security' => $legacy['smtp_ssl'] ?? 'none',
            'timeout' => $legacy['smtp_timeout'] ?? 120,
            'local_address' => $legacy['smtp_client'] ?? '',
            'username' => $legacy['smtp_authid'] ?? '',
            'password' => $legacy['smtp_authpwd'] ?? '',
        ];
        $destination = $this->expandHome(
            $this->nullableString($arguments['config']) ?? '/etc/postfixadmin/vacation-php.conf'
        );
        if ($legacyPath !== null && $this->samePath($destination, $legacyPath)) {
            throw new RuntimeException('The PHP configuration destination cannot be the legacy Perl configuration');
        }
        if (is_file($destination) && $this->isLegacyConfig($destination)) {
            throw new RuntimeException(
                'Refusing to overwrite a Perl vacation.conf; use a separate vacation-php.conf path'
            );
        }
        if (file_exists($destination) && !(bool)$arguments['force']) {
            throw new RuntimeException("Configuration already exists: {$destination}; use --force to replace it");
        }
        $directory = dirname($destination);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException("Could not create configuration directory: {$directory}");
        }
        if (file_put_contents(
            $destination,
            $this->renderConfig($root, $server, $port, $helo, $smtpOptions),
            LOCK_EX,
        ) === false) {
            throw new RuntimeException("Could not write configuration: {$destination}");
        }
        if (!chmod($destination, 0640)) {
            $this->writeError("WARNING: could not set mode 0640 on {$destination}" . PHP_EOL);
        }
        $this->write("Created: {$destination}" . PHP_EOL);
        $this->write('Review the file owner and group for the service user configured in Postfix master.cf.' . PHP_EOL);
        $this->write("Next: run vacation.php --check --config {$destination}" . PHP_EOL);
        return 0;
    }

    /**
     * @param array<string, mixed> $configuration
     * @param list<CheckResult> $results
     */
    public function checkDependencies(array $configuration, array &$results): bool
    {
        $ok = true;
        $runtimeVersion = phpversion();
        if (version_compare($runtimeVersion, '8.2.0', '<')) {
            $detail = $runtimeVersion . '; PHP 8.2 or newer is required';
            $results[] = new CheckResult('PHP', 'Version', 'FAILED', $detail);
            $ok = false;
        } else {
            $results[] = new CheckResult('PHP', 'Version', 'OK', $runtimeVersion);
        }
        foreach (['mbstring' => 'required by mailparse', 'mailparse' => 'MIME message parser'] as $extension => $reason) {
            if (extension_loaded($extension)) {
                $results[] = new CheckResult('Dependencies', $extension, 'OK', $reason);
            } else {
                $results[] = new CheckResult('Dependencies', $extension, 'MISSING', $reason);
                $ok = false;
            }
        }
        if (($configuration['smtp_security'] ?? 'none') !== 'none') {
            if (extension_loaded('openssl')) {
                $results[] = new CheckResult('Dependencies', 'openssl', 'OK', 'SMTP TLS support');
            } else {
                $results[] = new CheckResult('Dependencies', 'openssl', 'MISSING', 'SMTP TLS support');
                $ok = false;
            }
        }
        if (!extension_loaded('pdo')) {
            $results[] = new CheckResult('Dependencies', 'PDO', 'MISSING', 'database abstraction');
            return false;
        }
        $results[] = new CheckResult('Dependencies', 'PDO', 'OK', 'database abstraction');

        $type = $this->normalizeDatabaseType($configuration['database_type'] ?? '');
        if ($type === '') {
            $results[] = new CheckResult(
                'Dependencies',
                'Database driver',
                'NOT TESTED',
                'database type is unavailable because PostfixAdmin configuration was not loaded',
            );
            return false;
        }
        $driver = ['mysql' => 'mysql', 'postgresql' => 'pgsql', 'sqlite' => 'sqlite'][$type] ?? null;
        if ($driver === null) {
            $results[] = new CheckResult('Dependencies', 'PDO driver', 'MISSING', "unsupported database type: {$type}");
            return false;
        }
        if (in_array($driver, PDO::getAvailableDrivers(), true)) {
            $results[] = new CheckResult('Dependencies', "pdo_{$driver}", 'OK', "{$type} database driver");
        } else {
            $results[] = new CheckResult('Dependencies', "pdo_{$driver}", 'MISSING', "{$type} database driver");
            $ok = false;
        }
        return $ok;
    }

    /** @param array<string, mixed> $configuration */
    public function connectDatabase(array $configuration): PDO
    {
        $type = $this->normalizeDatabaseType($configuration['database_type'] ?? '');
        $host = (string)($configuration['database_host'] ?? '');
        $port = (string)($configuration['database_port'] ?? '');
        $socket = (string)($configuration['database_socket'] ?? '');
        $name = (string)($configuration['database_name'] ?? '');
        $user = (string)($configuration['database_user'] ?? '');
        $password = (string)($configuration['database_password'] ?? '');
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ];

        if ($type === 'mysql') {
            $dsn = $socket !== '' ? "mysql:unix_socket={$socket}" : 'mysql:host=' . ($host ?: 'localhost');
            if ($port !== '') {
                $dsn .= ";port={$port}";
            }
            $dsn .= ";dbname={$name};charset=utf8mb4";
            if (filter_var($configuration['database_use_ssl'] ?? false, FILTER_VALIDATE_BOOL)) {
                $options[PDO::MYSQL_ATTR_SSL_KEY] = (string)($configuration['database_ssl_key'] ?? '');
                $options[PDO::MYSQL_ATTR_SSL_CA] = (string)($configuration['database_ssl_ca'] ?? '');
                $options[PDO::MYSQL_ATTR_SSL_CAPATH] = (string)($configuration['database_ssl_ca_path'] ?? '');
                $options[PDO::MYSQL_ATTR_SSL_CERT] = (string)($configuration['database_ssl_cert'] ?? '');
                $options[PDO::MYSQL_ATTR_SSL_CIPHER] = (string)($configuration['database_ssl_cipher'] ?? '');
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = filter_var(
                    $configuration['database_ssl_verify_server_cert'] ?? true,
                    FILTER_VALIDATE_BOOL,
                );
            }
        } elseif ($type === 'postgresql') {
            $dsn = "pgsql:dbname={$name}";
            if ($host !== '') {
                $dsn .= ";host={$host}";
            }
            if ($port !== '') {
                $dsn .= ";port={$port}";
            }
        } elseif ($type === 'sqlite') {
            $dsn = "sqlite:{$name}";
            $user = '';
            $password = '';
        } else {
            throw new RuntimeException('Unsupported database type: ' . ($type ?: '(empty)'));
        }

        return new PDO($dsn, $user, $password, $options);
    }

    /** @param array<string, mixed> $configuration */
    private function databaseDependenciesAvailable(array $configuration): bool
    {
        if (!extension_loaded('pdo')) {
            return false;
        }
        $type = $this->normalizeDatabaseType($configuration['database_type'] ?? '');
        $driver = ['mysql' => 'mysql', 'postgresql' => 'pgsql', 'sqlite' => 'sqlite'][$type] ?? null;
        return $driver !== null && in_array($driver, PDO::getAvailableDrivers(), true);
    }

    /**
     * @param array<string, mixed> $configuration
     * @param list<CheckResult> $results
     */
    private function checkDatabase(array $configuration, bool $dependenciesOk, array &$results): bool
    {
        if ($this->normalizeDatabaseType($configuration['database_type'] ?? '') === '') {
            $results[] = new CheckResult(
                'Database',
                'Connection',
                'NOT TESTED',
                'PostfixAdmin database configuration was not loaded',
            );
            return false;
        }
        if (!$dependenciesOk) {
            $results[] = new CheckResult('Database', 'Connection', 'NOT TESTED', 'the required PDO driver is missing');
            return false;
        }
        try {
            $database = $this->connectDatabase($configuration);
            $results[] = new CheckResult('Database', 'Connection', 'OK');
            $allTablesOk = true;
            $tables = is_array($configuration['resolved_tables'] ?? null)
                ? $configuration['resolved_tables']
                : array_combine(self::TABLE_KEYS, self::TABLE_KEYS);
            foreach (self::TABLE_KEYS as $key) {
                $table = (string)($tables[$key] ?? $key);
                if (!preg_match('/^[A-Za-z_][A-Za-z0-9_$]*$/', $table)) {
                    $results[] = new CheckResult('Database', "Table {$key}", 'FAILED', "unsafe identifier: {$table}");
                    $allTablesOk = false;
                    continue;
                }
                $quoted = $this->quoteIdentifier($table, $this->normalizeDatabaseType($configuration['database_type'] ?? ''));
                try {
                    $database->query("SELECT 1 FROM {$quoted} LIMIT 1");
                    $results[] = new CheckResult('Database', "Table {$key}", 'OK', $table);
                } catch (Throwable $exception) {
                    $results[] = new CheckResult('Database', "Table {$key}", 'FAILED', $exception->getMessage());
                    $allTablesOk = false;
                }
            }
            return $allTablesOk;
        } catch (Throwable $exception) {
            $results[] = new CheckResult('Database', 'Connection', 'FAILED', $exception->getMessage());
            return false;
        }
    }

    /** @param list<CheckResult> $results */
    private function checkSmtp(array $configuration, array &$results): bool
    {
        $server = (string)($configuration['smtp_server'] ?? 'localhost');
        $port = $this->validPort($configuration['smtp_server_port'] ?? 25);
        $security = $this->normalizeSmtpSecurity($configuration['smtp_security'] ?? 'none');
        $helo = trim((string)($configuration['smtp_helo'] ?? '')) ?: $this->detectedFqdn();
        if ($helo === null || !$this->validHelo($helo)) {
            $results[] = new CheckResult('Delivery', 'SMTP', 'FAILED', 'a valid configured or detected HELO is required');
            return false;
        }
        try {
            $socket = $this->smtpConnect($configuration, $helo);
            try {
                [$code, $response] = $this->smtpCommand($socket, 'NOOP');
            } finally {
                $this->smtpQuit($socket);
            }
            if ($code >= 200 && $code < 400) {
                $results[] = new CheckResult('Delivery', 'SMTP', 'OK', "{$server}:{$port} ({$security})");
                return true;
            }
            $results[] = new CheckResult('Delivery', 'SMTP', 'FAILED', "NOOP returned {$code}: {$response}");
            return false;
        } catch (Throwable $exception) {
            $results[] = new CheckResult('Delivery', 'SMTP', 'FAILED', "{$server}:{$port}: {$exception->getMessage()}");
            return false;
        }
    }

    /** @param array<string, bool|string|null> $arguments */
    private function runCheck(array $arguments, bool $dependenciesOnly): int
    {
        $results = [];
        $vacationPath = $this->findVacationConfig($this->nullableString($arguments['config']));
        $configuration = [];
        if ($vacationPath !== null) {
            $loaded = $this->loadVacationConfig($vacationPath);
            $configuration = $loaded['values'];
            $results[] = new CheckResult('Configuration', 'Vacation config', 'OK', $vacationPath);
            foreach ($loaded['warnings'] as $warning) {
                $results[] = new CheckResult('Configuration', 'Parse warning', 'WARNING', $warning);
            }
        } elseif (!$dependenciesOnly) {
            $results[] = new CheckResult('Configuration', 'Vacation config', 'MISSING', 'use --config or run --init-config');
        }

        $rootArgument = $this->nullableString($arguments['postfixadmin_root'])
            ?? ($configuration['postfixadmin_root'] ?? null);
        $roots = $this->discoverPostfixAdminRoots(is_string($rootArgument) ? $rootArgument : null);
        if ($roots !== []) {
            try {
                $root = $this->choosePostfixAdminRoot($roots, true);
                $postfixAdmin = $this->loadPostfixAdminConfig($root);
                $results[] = new CheckResult('Configuration', 'PostfixAdmin config', 'OK', "{$root}/config.local.php");
                $configuration = array_replace($postfixAdmin, $configuration);
            } catch (Throwable $exception) {
                $results[] = new CheckResult('Configuration', 'PostfixAdmin config', 'FAILED', $exception->getMessage());
            }
        } elseif ($rootArgument !== null) {
            $results[] = new CheckResult('Configuration', 'PostfixAdmin config', 'MISSING', (string)$rootArgument);
        } else {
            $results[] = new CheckResult(
                'Configuration',
                'PostfixAdmin config',
                'MISSING',
                'no installation found; use --postfixadmin-root /path/to/postfixadmin',
            );
        }

        $this->checkDependencies($configuration, $results);
        if (!$dependenciesOnly && $vacationPath !== null) {
            $this->checkDatabase($configuration, $this->databaseDependenciesAvailable($configuration), $results);
            $this->checkSmtp($configuration, $results);
        }

        $this->write('PostfixAdmin Virtual Vacation - system check' . PHP_EOL . PHP_EOL);
        $this->printResults($results);
        $failed = false;
        foreach ($results as $result) {
            if (in_array($result->status, ['FAILED', 'MISSING'], true)) {
                $failed = true;
                break;
            }
        }
        $this->write(PHP_EOL . 'Result: ' . ($failed ? 'FAILED' : 'OK') . PHP_EOL);
        return $failed ? 1 : 0;
    }

    /** @param array<string, bool|string|null> $arguments */
    private function runTest(array $arguments): int
    {
        $path = $this->findVacationConfig($this->nullableString($arguments['config']));
        if ($path === null) {
            throw new RuntimeException('No vacation-php.conf found; run --init-config first or use --config');
        }
        $loaded = $this->loadVacationConfig($path);
        foreach ($loaded['warnings'] as $warning) {
            $this->writeError("WARNING: {$warning}" . PHP_EOL);
        }
        $configuration = $loaded['values'];
        $server = (string)($configuration['smtp_server'] ?? 'localhost');
        $port = $this->validPort($configuration['smtp_server_port'] ?? 25);
        $helo = $this->resolveSmtpHelo($configuration);
        $sender = $this->prompt('MAIL FROM', $this->defaultTestSender($helo));
        if (!$this->validEmailAddress($sender)) {
            throw new RuntimeException('Invalid test sender address');
        }
        $recipient = $this->prompt('RCPT TO');
        if (!$this->validEmailAddress($recipient)) {
            throw new RuntimeException('Invalid test recipient address');
        }

        $this->write("Sending test message from {$sender} to {$recipient} using {$server}:{$port}..." . PHP_EOL);
        $socket = $this->smtpConnect($configuration, $helo);
        try {
            $this->smtpExpect($this->smtpCommand($socket, "MAIL FROM:<{$sender}>"), [250], 'MAIL FROM');
            $this->smtpExpect($this->smtpCommand($socket, "RCPT TO:<{$recipient}>"), [250, 251], 'RCPT TO');
            $this->smtpExpect($this->smtpCommand($socket, 'DATA'), [354], 'DATA');
            $message = $this->testMessage($sender, $recipient, $helo, $server, $port);
            $message = preg_replace('/(?m)^\./', '..', $message) ?? $message;
            fwrite($socket, str_replace("\n", "\r\n", str_replace("\r\n", "\n", $message)) . "\r\n.\r\n");
            $this->smtpExpect($this->smtpReadResponse($socket), [250], 'message body');
        } finally {
            $this->smtpQuit($socket);
        }
        $this->write('Test message sent successfully.' . PHP_EOL);
        return 0;
    }

    /** @param array<string, bool|string|null> $arguments */
    private function showConfigPath(array $arguments): int
    {
        $path = $this->findVacationConfig($this->nullableString($arguments['config']));
        if ($path === null) {
            $this->writeError('No vacation-php.conf found' . PHP_EOL);
            return 1;
        }
        $this->write($path . PHP_EOL);
        return 0;
    }

    /** @param list<CheckResult> $results */
    private function printResults(array $results): void
    {
        $section = null;
        foreach ($results as $result) {
            if ($result->section !== $section) {
                if ($section !== null) {
                    $this->write(PHP_EOL);
                }
                $this->write($result->section . ':' . PHP_EOL);
                $section = $result->section;
            }
            $detail = $result->detail !== '' ? ' - ' . $result->detail : '';
            $this->write(sprintf('  %-28s %s%s%s', $result->name, $result->status, $detail, PHP_EOL));
        }
    }

    /** @param array<string, mixed> $configuration @return resource */
    private function smtpConnect(array $configuration, string $helo)
    {
        $server = (string)($configuration['smtp_server'] ?? 'localhost');
        $port = $this->validPort($configuration['smtp_server_port'] ?? 25);
        $timeout = $this->validTimeout($configuration['smtp_timeout'] ?? 120);
        $security = $this->normalizeSmtpSecurity($configuration['smtp_security'] ?? 'none');
        if ($security !== 'none' && !extension_loaded('openssl')) {
            throw new RuntimeException('The openssl extension is required for SMTP TLS');
        }
        $socketOptions = [];
        $localAddress = trim((string)($configuration['smtp_local_address'] ?? ''));
        if ($localAddress !== '') {
            $socketOptions['bindto'] = filter_var($localAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
                ? "[{$localAddress}]:0"
                : "{$localAddress}:0";
        }
        $context = stream_context_create([
            'socket' => $socketOptions,
            'ssl' => [
                'peer_name' => $server,
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ]);
        $host = filter_var($server, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? "[{$server}]" : $server;
        $scheme = $security === 'ssl' ? 'tls' : 'tcp';
        $errorCode = 0;
        $errorMessage = '';
        $socket = @stream_socket_client(
            "{$scheme}://{$host}:{$port}",
            $errorCode,
            $errorMessage,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context,
        );
        if ($socket === false) {
            throw new RuntimeException("SMTP connection failed ({$errorCode}): {$errorMessage}");
        }
        try {
            stream_set_timeout($socket, $timeout);
            $this->smtpExpect($this->smtpReadResponse($socket), [220], 'greeting');
            $hello = $this->smtpHello($socket, $helo, $security !== 'starttls');
            if (in_array($security, ['starttls', 'maybestarttls'], true)) {
                $supportsStartTls = preg_match('/^250[ -]STARTTLS\b/im', $hello[1]) === 1;
                if (!$supportsStartTls && $security === 'starttls') {
                    throw new RuntimeException('SMTP server does not advertise STARTTLS');
                }
                if ($supportsStartTls) {
                    $this->smtpExpect($this->smtpCommand($socket, 'STARTTLS'), [220], 'STARTTLS');
                    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                        throw new RuntimeException('SMTP TLS negotiation failed');
                    }
                    $hello = $this->smtpHello($socket, $helo);
                }
            }
            $username = (string)($configuration['smtp_username'] ?? '');
            if ($username !== '') {
                $password = (string)($configuration['smtp_password'] ?? '');
                if ($password === '') {
                    throw new RuntimeException('SMTP password is required when username is configured');
                }
                $this->smtpAuthenticate($socket, $hello[1], $username, $password);
            }
            return $socket;
        } catch (Throwable $exception) {
            @fclose($socket);
            throw $exception;
        }
    }

    /** @param resource $socket */
    private function smtpAuthenticate($socket, string $capabilities, string $username, string $password): void
    {
        if (preg_match('/^250[ -]AUTH\b[^\r\n]*\bPLAIN\b/im', $capabilities)) {
            $credentials = base64_encode("\0{$username}\0{$password}");
            $this->smtpExpect(
                $this->smtpCommand($socket, "AUTH PLAIN {$credentials}", 'AUTH PLAIN [redacted]'),
                [235],
                'authentication',
            );
            return;
        }
        if (preg_match('/^250[ -]AUTH\b[^\r\n]*\bLOGIN\b/im', $capabilities)) {
            $this->smtpExpect($this->smtpCommand($socket, 'AUTH LOGIN'), [334], 'AUTH LOGIN');
            $this->smtpExpect(
                $this->smtpCommand($socket, base64_encode($username), 'AUTH username [redacted]'),
                [334],
                'AUTH username',
            );
            $this->smtpExpect(
                $this->smtpCommand($socket, base64_encode($password), 'AUTH password [redacted]'),
                [235],
                'AUTH password',
            );
            return;
        }
        throw new RuntimeException('SMTP authentication is configured but the server offers neither PLAIN nor LOGIN');
    }

    /** @param resource $socket @return array{0: int, 1: string} */
    private function smtpHello($socket, string $helo, bool $allowHeloFallback = true): array
    {
        $response = $this->smtpCommand($socket, "EHLO {$helo}");
        if ($response[0] >= 400 && $allowHeloFallback) {
            $response = $this->smtpCommand($socket, "HELO {$helo}");
        }
        $this->smtpExpect($response, [250], 'HELO');
        return $response;
    }

    /** @param resource $socket @return array{0: int, 1: string} */
    private function smtpCommand($socket, string $command, ?string $displayCommand = null): array
    {
        if (fwrite($socket, $command . "\r\n") === false) {
            throw new RuntimeException('Could not write SMTP command: ' . ($displayCommand ?? $command));
        }
        return $this->smtpReadResponse($socket);
    }

    /** @param resource $socket @return array{0: int, 1: string} */
    private function smtpReadResponse($socket): array
    {
        $lines = [];
        $code = 0;
        do {
            $line = fgets($socket, 4096);
            if ($line === false) {
                $metadata = stream_get_meta_data($socket);
                throw new RuntimeException(!empty($metadata['timed_out']) ? 'SMTP response timed out' : 'SMTP connection closed');
            }
            $lines[] = rtrim($line, "\r\n");
            if (preg_match('/^(\d{3})([ -])/', $line, $matches)) {
                $code = (int)$matches[1];
                $continued = $matches[2] === '-';
            } else {
                $continued = false;
            }
        } while ($continued);
        return [$code, implode("\n", $lines)];
    }

    /** @param array{0: int, 1: string} $response @param list<int> $expected */
    private function smtpExpect(array $response, array $expected, string $operation): void
    {
        if (!in_array($response[0], $expected, true)) {
            throw new RuntimeException("SMTP {$operation} failed: {$response[1]}");
        }
    }

    /** @param resource $socket */
    private function smtpQuit($socket): void
    {
        if (!is_resource($socket)) {
            return;
        }
        @fwrite($socket, "QUIT\r\n");
        @fclose($socket);
    }

    private function testMessage(string $sender, string $recipient, string $helo, string $server, int $port): string
    {
        $date = new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get()));
        $messageId = sprintf('<%s@%s>', bin2hex(random_bytes(16)), $helo);
        return implode("\n", [
            "From: {$sender}",
            "To: {$recipient}",
            'Subject: PostfixAdmin vacation.php test',
            'Date: ' . $date->format(DATE_RFC2822),
            "Message-ID: {$messageId}",
            'Auto-Submitted: auto-generated',
            'X-PostfixAdmin-Vacation-Test: yes',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            'This is a test message sent by PostfixAdmin vacation.php.',
            '',
            "SMTP server: {$server}:{$port}",
            "SMTP HELO: {$helo}",
        ]);
    }

    /** @param array<string, mixed> $configuration */
    public function resolveSmtpHelo(array $configuration): string
    {
        $configured = trim((string)($configuration['smtp_helo'] ?? ''));
        $helo = $configured !== '' ? $configured : ($this->detectedFqdn() ?? $this->prompt('SMTP HELO'));
        if (!$this->validHelo($helo)) {
            throw new RuntimeException('A valid SMTP HELO name is required for the test');
        }
        return $helo;
    }

    public function detectedFqdn(): ?string
    {
        $hostname = gethostname();
        if (!is_string($hostname)) {
            return null;
        }
        $candidates = [$hostname];
        $address = gethostbyname($hostname);
        if ($address !== $hostname) {
            $reverse = gethostbyaddr($address);
            if (is_string($reverse)) {
                array_unshift($candidates, $reverse);
            }
        }
        foreach ($candidates as $candidate) {
            $candidate = strtolower(trim($candidate, ". \t\n\r\0\x0B"));
            if ($this->validHelo($candidate) && !in_array($candidate, ['localhost', 'localhost.localdomain'], true)) {
                return $candidate;
            }
        }
        return null;
    }

    public function baseDomain(string $hostname): string
    {
        $labels = array_values(array_filter(explode('.', strtolower(trim($hostname, '.'))), static fn ($label) => $label !== ''));
        if (count($labels) >= 3) {
            array_shift($labels);
        }
        return $labels !== [] ? implode('.', $labels) : 'localhost';
    }

    public function defaultTestSender(string $helo): string
    {
        return 'noreply@' . $this->baseDomain($helo);
    }

    public function validEmailAddress(string $address): bool
    {
        return !str_contains($address, "\r")
            && !str_contains($address, "\n")
            && filter_var($address, FILTER_VALIDATE_EMAIL) === $address;
    }

    private function validHelo(string $helo): bool
    {
        return str_contains($helo, '.') && !preg_match('/\s/', $helo);
    }

    private function validPort(mixed $value): int
    {
        $port = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if ($port === false) {
            throw new RuntimeException('SMTP port must be between 1 and 65535');
        }
        return $port;
    }

    private function validTimeout(mixed $value): int
    {
        $timeout = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3600]]);
        if ($timeout === false) {
            throw new RuntimeException('SMTP timeout must be between 1 and 3600 seconds');
        }
        return $timeout;
    }

    private function normalizeSmtpSecurity(mixed $value): string
    {
        $value = strtolower(trim((string)$value));
        return match ($value) {
            '', '0', 'false', 'none', 'plain' => 'none',
            '1', 'true', 'ssl', 'tls' => 'ssl',
            'starttls' => 'starttls',
            'maybestarttls' => 'maybestarttls',
            default => throw new RuntimeException(
                'SMTP security must be none, ssl, starttls, or maybestarttls'
            ),
        };
    }

    private function normalizeDatabaseType(mixed $value): string
    {
        return match (strtolower((string)$value)) {
            'mysql', 'mysqli', 'mariadb' => 'mysql',
            'pgsql', 'postgres', 'postgresql', 'pg' => 'postgresql',
            'sqlite', 'sqlite3' => 'sqlite',
            default => strtolower((string)$value),
        };
    }

    private function quoteIdentifier(string $identifier, string $databaseType): string
    {
        return $databaseType === 'mysql' ? "`{$identifier}`" : '"' . $identifier . '"';
    }

    private function parsePerlValue(string $value): bool|int|string
    {
        $value = trim($value);
        if (preg_match("/^'(.*)'$/s", $value, $matches)) {
            return str_replace(["\\'", "\\\\"], ["'", "\\"], $matches[1]);
        }
        if (preg_match('/^"(.*)"$/s', $value, $matches)) {
            return stripcslashes($matches[1]);
        }
        if (preg_match('/^-?\d+$/', $value)) {
            return (int)$value;
        }
        return match (strtolower($value)) {
            'true', 'yes' => true,
            'false', 'no' => false,
            default => throw new RuntimeException("unsupported Perl value: {$value}"),
        };
    }

    private function iniValue(string $value): string
    {
        return '"' . addcslashes($value, "\\\"") . '"';
    }

    private function promptValue(string $label, string $default, bool $nonInteractive): string
    {
        return $nonInteractive ? $default : $this->prompt($label, $default);
    }

    public function prompt(string $label, ?string $default = null): string
    {
        $suffix = $default !== null ? " [{$default}]" : '';
        $this->write("{$label}{$suffix}: ");
        $line = fgets($this->input);
        if ($line === false) {
            throw new RuntimeException("No input received for {$label}");
        }
        $value = trim($line);
        if ($value === '' && $default !== null) {
            return $default;
        }
        return $value;
    }

    private function expandHome(string $path): string
    {
        if (!str_starts_with($path, '~/') && !str_starts_with($path, '~\\')) {
            return $path;
        }
        $home = getenv(PHP_OS_FAMILY === 'Windows' ? 'USERPROFILE' : 'HOME');
        return is_string($home) && $home !== '' ? $home . substr($path, 1) : $path;
    }

    private function samePath(string $left, string $right): bool
    {
        $leftReal = realpath($left);
        $rightReal = realpath($right);
        if ($leftReal !== false && $rightReal !== false) {
            return strcasecmp($leftReal, $rightReal) === 0;
        }
        return strcasecmp(str_replace('\\', '/', $left), str_replace('\\', '/', $right)) === 0;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function write(string $message): void
    {
        fwrite($this->output, $message);
    }

    private function writeError(string $message): void
    {
        fwrite($this->errorOutput, $message);
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit((new VacationCli())->run($argv));
}
