#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * PostfixAdmin Virtual Vacation transport and administration utility for PHP CLI.
 *
 * It preserves the vacation.pl pipe contract and adds configuration discovery,
 * diagnostics, an interactive SMTP test, and read-only message inspection.
 *
 * See VIRTUAL_VACATION/Contributions.txt for contributor credits.
 */

namespace PostfixAdmin\VirtualVacation;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
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

final class MessageInspectionResult
{
    public function __construct(
        public readonly bool $eligible,
        public readonly string $reason,
        public readonly string $from = '',
        public readonly string $to = '',
        public readonly string $cc = '',
        public readonly string $replyTo = '',
        public readonly string $subject = '',
        public readonly string $messageId = '',
        public readonly string $envelopeSender = '',
        public readonly string $envelopeRecipient = '',
    ) {
    }
}

final class VacationMessageInspector
{
    private const DEFAULT_NOREPLY_PATTERN =
        '^(?:noreply|no-reply|do_not_reply|no_reply|postmaster|mailer-daemon|listserv|majordomo|owner-|request-|bounces-)'
        . '|(?:-(?:owner|request|bounces)@)';

    /**
     * @param resource $stream
     * @param array<string, mixed> $configuration
     */
    public function inspectStream(
        $stream,
        string $envelopeSender,
        string $envelopeRecipient,
        array $configuration,
    ): MessageInspectionResult {
        if (!extension_loaded('mailparse')) {
            throw new RuntimeException('The mailparse extension is required to inspect a message stream');
        }
        return $this->inspectHeaders(
            $this->parseHeaders($stream),
            $envelopeSender,
            $envelopeRecipient,
            $configuration,
        );
    }

    /**
     * @param array<string, string|list<string>> $headers
     * @param array<string, mixed> $configuration
     */
    public function inspectHeaders(
        array $headers,
        string $envelopeSender,
        string $envelopeRecipient,
        array $configuration = [],
    ): MessageInspectionResult {
        $headers = $this->normalizeHeaders($headers);
        $rejection = $this->headerRejectionReason($headers);
        if ($rejection !== null) {
            return new MessageInspectionResult(false, $rejection);
        }

        $fromRaw = $this->firstHeader($headers, 'from');
        $toRaw = $this->firstHeader($headers, 'to');
        $ccRaw = $this->firstHeader($headers, 'cc');
        $replyToRaw = $this->firstHeader($headers, 'reply-to');
        $subject = $this->firstHeader($headers, 'subject');
        $messageId = $this->firstHeader($headers, 'message-id');
        $vacationDomain = trim((string)($configuration['vacation_domain'] ?? ''));
        $delimiter = (string)($configuration['recipient_delimiter'] ?? '');
        $envelopeSender = $this->normalizeEnvelopeAddress($envelopeSender);
        $envelopeRecipient = $this->normalizeEnvelopeAddress(
            $this->restoreVacationRecipient($envelopeRecipient, $vacationDomain, $delimiter)
        );

        if ($fromRaw === '' || $toRaw === '' || $messageId === ''
            || $envelopeSender === '' || $envelopeRecipient === ''
        ) {
            return new MessageInspectionResult(false, 'a required message or envelope field is missing');
        }

        $noVacationPattern = trim((string)($configuration['message_no_vacation_pattern'] ?? ''));
        if ($noVacationPattern !== '' && $this->matchesPattern($toRaw, $noVacationPattern)) {
            return new MessageInspectionResult(false, 'the To header matches no_vacation_pattern');
        }

        $from = $this->extractAddresses($fromRaw);
        $to = $this->extractAddresses($toRaw);
        $cc = $this->extractAddresses($ccRaw);
        $replyTo = $this->extractAddresses($replyToRaw);
        if ($from === [] || $to === []) {
            return new MessageInspectionResult(false, 'a required header contains no valid address');
        }
        if ($replyToRaw !== '' && $replyTo === []) {
            return new MessageInspectionResult(false, 'the Reply-To header contains no valid address');
        }

        $addressesToCheck = array_merge($from, $replyTo, [$envelopeSender, $envelopeRecipient]);
        foreach ($addressesToCheck as $address) {
            if ($this->isNoReplyAddress($address, $configuration)) {
                return new MessageInspectionResult(false, "no-reply address detected: {$address}");
            }
        }
        if ($envelopeSender === $envelopeRecipient) {
            return new MessageInspectionResult(false, 'envelope sender and recipient are the same');
        }
        if (in_array($envelopeSender, array_merge($to, $cc), true)) {
            return new MessageInspectionResult(false, 'the envelope sender is also a To or Cc recipient');
        }

        return new MessageInspectionResult(
            true,
            'message is eligible for Vacation processing',
            implode(', ', $from),
            implode(', ', $to),
            implode(', ', $cc),
            implode(', ', $replyTo),
            $subject,
            $messageId,
            $envelopeSender,
            $envelopeRecipient,
        );
    }

    public function restoreVacationRecipient(string $recipient, string $vacationDomain, string $delimiter = ''): string
    {
        $recipient = trim($recipient, "<> \t\n\r\0\x0B");
        $suffix = '@' . strtolower(trim($vacationDomain, '. '));
        if ($vacationDomain === '' || !str_ends_with(strtolower($recipient), $suffix)) {
            return $recipient;
        }
        $encoded = substr($recipient, 0, -strlen($suffix));
        $restored = str_replace('#', '@', $encoded);
        $at = strrpos($restored, '@');
        if ($at === false) {
            return $restored;
        }
        $local = substr($restored, 0, $at);
        $domain = substr($restored, $at + 1);
        if ($delimiter !== '' && ($position = strpos($local, $delimiter)) !== false) {
            $local = substr($local, 0, $position);
        }
        return "{$local}@{$domain}";
    }

    /** @param resource $stream @return array<string, string|list<string>> */
    private function parseHeaders($stream): array
    {
        $message = call_user_func('mailparse_msg_create');
        try {
            while (!feof($stream)) {
                $chunk = fread($stream, 8192);
                if ($chunk === false) {
                    throw new RuntimeException('Could not read the message stream');
                }
                if ($chunk !== '' && call_user_func('mailparse_msg_parse', $message, $chunk) === false) {
                    throw new RuntimeException('mailparse could not parse the message stream');
                }
            }
            $partData = call_user_func('mailparse_msg_get_part_data', $message);
            if (!isset($partData['headers']) || !is_array($partData['headers'])) {
                throw new RuntimeException('mailparse did not return message headers');
            }
            $headers = [];
            foreach ($partData['headers'] as $name => $values) {
                if (!is_string($name)) {
                    continue;
                }
                $values = is_array($values) ? $values : [$values];
                foreach ($values as $value) {
                    if (is_scalar($value)) {
                        $headers[$name][] = (string)$value;
                    }
                }
            }
            return $headers;
        } finally {
            call_user_func('mailparse_msg_free', $message);
        }
    }

    /**
     * @param array<string, string|list<string>> $headers
     * @return array<string, list<string>>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $values) {
            $name = strtolower(trim($name));
            foreach (is_array($values) ? $values : [$values] as $value) {
                $value = preg_replace('/\r?\n[ \t]+/', ' ', (string)$value) ?? (string)$value;
                $value = preg_replace('/[\r\n]+/', ' ', $value) ?? $value;
                $normalized[$name][] = trim($value);
            }
        }
        return $normalized;
    }

    /** @param array<string, list<string>> $headers */
    private function headerRejectionReason(array $headers): ?string
    {
        $rules = [
            ['x-spam-flag', '/^\s*yes\b/i', 'X-Spam-Flag indicates spam'],
            ['x-spam-status', '/^\s*yes\b/i', 'X-Spam-Status indicates spam'],
            ['x-facebook-notify', '/.*/s', 'Facebook notification header found'],
            ['x-amazon-mail-relay-type', '/^\s*notification\b/i', 'Amazon notification header found'],
            ['precedence', '/^\s*(?:bulk|list|junk)\b/i', 'bulk or list Precedence header found'],
            ['x-loop', '/^\s*postfix admin virtual vacation\b/i', 'Vacation loop header found'],
            ['list-id', '/.*/s', 'List-Id header found'],
            ['list-post', '/.*/s', 'List-Post header found'],
            ['list-unsubscribe', '/.*/s', 'List-Unsubscribe header found'],
            ['x-barracuda-spam-status', '/^\s*yes\b/i', 'Barracuda spam status header found'],
            ['x-dspam-result', '/^\s*(?:spam|bl[ao]cklisted)\b/i', 'DSPAM rejection header found'],
            ['x-virus-status', '/^\s*infected\b/i', 'virus status header found'],
            ['x-antivirus-status', '/^\s*infected\b/i', 'antivirus status header found'],
            ['x-avas-virus-status', '/^\s*infected\b/i', 'AVAS virus status header found'],
            ['x-avas-spam-status', '/^\s*spam\b/i', 'AVAS spam status header found'],
            ['x-spamtest-status', '/^\s*spam\b/i', 'SpamTest status header found'],
            ['x-crm114-status', '/^\s*spam\b/i', 'CRM114 status header found'],
            ['x-razor-status', '/^\s*spam\b/i', 'Razor status header found'],
            ['x-pyzor-status', '/^\s*spam\b/i', 'Pyzor status header found'],
            ['x-osbf-lua-score', '/^[0-9\.\/\-+]+\s+\[[\-S]\]/i', 'OSBF-Lua spam score header found'],
            ['x-autogenerated', '/^\s*reply\b/i', 'automatic reply header found'],
            ['x-auto-response-suppress', '/^\s*(?:oof|all)\b/i', 'automatic response suppression header found'],
        ];
        foreach ($rules as [$name, $pattern, $reason]) {
            foreach ($headers[$name] ?? [] as $value) {
                if (preg_match($pattern, $value)) {
                    return $reason;
                }
            }
        }
        foreach ($headers['auto-submitted'] ?? [] as $value) {
            if (!preg_match('/^\s*no\b/i', $value)) {
                return 'Auto-Submitted indicates an automatically generated message';
            }
        }
        return null;
    }

    /** @param array<string, list<string>> $headers */
    private function firstHeader(array $headers, string $name): string
    {
        return $headers[$name][0] ?? '';
    }

    /** @return list<string> */
    private function extractAddresses(string $value): array
    {
        if ($value === '') {
            return [];
        }
        $addresses = [];
        if (extension_loaded('mailparse')) {
            $parsed = call_user_func('mailparse_rfc822_parse_addresses', $value);
            foreach ($parsed as $entry) {
                if (is_array($entry) && isset($entry['address'])) {
                    $addresses[] = (string)$entry['address'];
                }
            }
        } else {
            preg_match_all(
                "/[A-Z0-9.!#$%&'*+\\/=\?^_`{|}~-]+@[A-Z0-9.-]+/i",
                $value,
                $matches,
            );
            $addresses = $matches[0];
        }
        $valid = [];
        foreach ($addresses as $address) {
            $address = strtolower(trim($address, "<> \t\n\r\0\x0B"));
            if (filter_var($address, FILTER_VALIDATE_EMAIL) === $address) {
                $valid[$address] = $address;
            }
        }
        return array_values($valid);
    }

    private function normalizeEnvelopeAddress(string $address): string
    {
        $address = strtolower(trim($address, "<> \t\n\r\0\x0B"));
        return filter_var($address, FILTER_VALIDATE_EMAIL) === $address ? $address : '';
    }

    /** @param array<string, mixed> $configuration */
    private function isNoReplyAddress(string $address, array $configuration): bool
    {
        $defaultPattern = (string)($configuration['message_default_noreply_pattern']
            ?? self::DEFAULT_NOREPLY_PATTERN);
        if ($defaultPattern !== '' && $this->matchesPattern($address, $defaultPattern)) {
            return true;
        }
        $customEnabled = filter_var(
            $configuration['message_custom_noreply_pattern'] ?? false,
            FILTER_VALIDATE_BOOL,
        );
        $customPattern = trim((string)($configuration['message_noreply_pattern'] ?? ''));
        return $customEnabled && $customPattern !== '' && $this->matchesPattern($address, $customPattern);
    }

    private function matchesPattern(string $value, string $pattern): bool
    {
        $result = @preg_match('~' . str_replace('~', '\\~', $pattern) . '~i', $value);
        if ($result === false) {
            throw new RuntimeException("Invalid configured message pattern: {$pattern}");
        }
        return $result === 1;
    }
}

final class VacationRepository
{
    /** @var array<string, string> */
    private array $tables;

    /** @param array<string, string> $tables */
    public function __construct(private readonly PDO $database, array $tables, private readonly string $databaseType)
    {
        foreach (['vacation', 'vacation_notification', 'alias', 'alias_domain', 'mailbox'] as $key) {
            $identifier = (string)($tables[$key] ?? $key);
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_$]*$/', $identifier)) {
                throw new RuntimeException("Unsafe table identifier: {$identifier}");
            }
            $this->tables[$key] = $this->quoteIdentifier($identifier);
        }
    }

    /** @return array<string, mixed>|null */
    public function findActiveVacation(string $email, string $vacationDomain): ?array
    {
        return $this->resolveActiveVacation(strtolower($email), strtolower($vacationDomain), 0, []);
    }

    /** @return array<string, mixed>|null @param array<string, true> $visited */
    private function resolveActiveVacation(string $email, string $vacationDomain, int $depth, array $visited): ?array
    {
        if ($depth >= 20 || isset($visited[$email])) {
            throw new RuntimeException("Vacation alias resolution loop detected at {$email}");
        }
        $visited[$email] = true;
        $vacation = $this->activeVacation($email);
        if ($vacation !== null) {
            return $vacation;
        }

        $encodedVacation = strtolower(str_replace('@', '#', $email) . '@' . $vacationDomain);
        $statement = $this->database->prepare("SELECT goto FROM {$this->tables['alias']} WHERE address = ?");
        $statement->execute([$email]);
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $targets) {
            $aliases = array_values(array_filter(array_map('trim', explode(',', strtolower((string)$targets)))));
            $hasVacationAlias = false;
            foreach ($aliases as $alias) {
                if ($alias === $encodedVacation) {
                    $hasVacationAlias = true;
                    break;
                }
            }
            if (!$hasVacationAlias) {
                continue;
            }
            foreach ($aliases as $alias) {
                $vacation = $this->activeVacation($alias);
                if ($vacation !== null) {
                    return $vacation;
                }
            }
        }

        if (!str_contains($email, '@')) {
            return null;
        }
        [$user, $domain] = explode('@', $email, 2);
        $statement = $this->database->prepare(
            "SELECT target_domain FROM {$this->tables['alias_domain']} WHERE alias_domain = ?"
        );
        $statement->execute([$domain]);
        $targetDomain = $statement->fetchColumn();
        if (is_string($targetDomain) && $targetDomain !== '') {
            return $this->resolveActiveVacation(
                "{$user}@" . strtolower($targetDomain),
                $vacationDomain,
                $depth + 1,
                $visited,
            );
        }

        $statement = $this->database->prepare("SELECT goto FROM {$this->tables['alias']} WHERE address = ?");
        $statement->execute(["@{$domain}"]);
        $wildcard = $statement->fetchColumn();
        if (!is_string($wildcard) || $wildcard === '') {
            return null;
        }
        $wildcard = strtolower(trim(explode(',', $wildcard, 2)[0]));
        if (!str_contains($wildcard, '@')) {
            return null;
        }
        [$wildcardUser, $wildcardDomain] = explode('@', $wildcard, 2);
        $target = $wildcardUser !== '' ? $wildcard : "{$user}@{$wildcardDomain}";
        return $this->resolveActiveVacation($target, $vacationDomain, $depth + 1, $visited);
    }

    /** @return array<string, mixed>|null */
    private function activeVacation(string $email): ?array
    {
        $statement = $this->database->prepare(
            "SELECT email, subject, body, activefrom, activeuntil, interval_time, active "
            . "FROM {$this->tables['vacation']} WHERE email = ?"
        );
        $statement->execute([$email]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !$this->databaseBoolean($row['active'] ?? false)) {
            return null;
        }
        $now = time();
        $from = strtotime((string)($row['activefrom'] ?? ''));
        $until = strtotime((string)($row['activeuntil'] ?? ''));
        if (($from !== false && $from > $now) || ($until !== false && $until < $now)) {
            return null;
        }
        return $row;
    }

    public function claimNotification(array $vacation, string $sender): bool
    {
        $email = strtolower((string)$vacation['email']);
        $sender = strtolower($sender);
        $statement = $this->database->prepare(
            "SELECT notified_at FROM {$this->tables['vacation_notification']} "
            . 'WHERE on_vacation = ? AND notified = ?'
        );
        $statement->execute([$email, $sender]);
        $notifiedAt = $statement->fetchColumn();
        $activeFrom = strtotime((string)($vacation['activefrom'] ?? ''));
        if (is_string($notifiedAt) && $activeFrom !== false && strtotime($notifiedAt) < $activeFrom) {
            $delete = $this->database->prepare(
                "DELETE FROM {$this->tables['vacation_notification']} WHERE on_vacation = ? AND notified = ?"
            );
            $delete->execute([$email, $sender]);
            $notifiedAt = false;
        }

        if ($notifiedAt === false) {
            try {
                $insert = $this->database->prepare(
                    "INSERT INTO {$this->tables['vacation_notification']} "
                    . '(on_vacation, notified) VALUES (?, ?)'
                );
                $insert->execute([$email, $sender]);
                return true;
            } catch (PDOException $exception) {
                if (!in_array((string)$exception->getCode(), ['23000', '23505'], true)) {
                    throw $exception;
                }
                $statement->execute([$email, $sender]);
                $notifiedAt = $statement->fetchColumn();
            }
        }

        $interval = max(0, (int)($vacation['interval_time'] ?? 0));
        $previous = is_string($notifiedAt) ? strtotime($notifiedAt) : false;
        $databaseNow = strtotime((string)$this->database->query('SELECT CURRENT_TIMESTAMP')->fetchColumn());
        if ($interval === 0 || $previous === false || $databaseNow === false
            || $databaseNow - $previous <= $interval
        ) {
            return false;
        }
        $update = $this->database->prepare(
            "UPDATE {$this->tables['vacation_notification']} SET notified_at = CURRENT_TIMESTAMP "
            . 'WHERE on_vacation = ? AND notified = ?'
        );
        $update->execute([$email, $sender]);
        return true;
    }

    public function accountName(string $email): string
    {
        $statement = $this->database->prepare("SELECT name FROM {$this->tables['mailbox']} WHERE username = ?");
        $statement->execute([$email]);
        $name = $statement->fetchColumn();
        return is_string($name) ? trim($name) : '';
    }

    public function forgetNotification(string $vacationAddress, string $sender): void
    {
        $statement = $this->database->prepare(
            "DELETE FROM {$this->tables['vacation_notification']} WHERE on_vacation = ? AND notified = ?"
        );
        $statement->execute([strtolower($vacationAddress), strtolower($sender)]);
    }

    private function databaseBoolean(mixed $value): bool
    {
        return in_array(strtolower((string)$value), ['1', 't', 'true', 'y', 'yes'], true);
    }

    private function quoteIdentifier(string $identifier): string
    {
        return $this->databaseType === 'mysql' ? "`{$identifier}`" : '"' . $identifier . '"';
    }
}

final class VacationReplyComposer
{
    /** @param array<string, mixed> $vacation @param array<string, mixed> $configuration */
    public function compose(
        array $vacation,
        MessageInspectionResult $incoming,
        array $configuration,
        string $accountName = '',
    ): string {
        $email = strtolower((string)$vacation['email']);
        $subject = str_replace('$SUBJECT', $this->decodeHeader($incoming->subject), (string)$vacation['subject']);
        $body = $this->replaceDates((string)$vacation['body'], $vacation, $configuration);
        $friendly = trim((string)($configuration['reply_friendly_from'] ?? ''));
        if (!empty($configuration['reply_account_name']) && $accountName !== '') {
            $friendly = $accountName;
        }
        $from = $friendly === '' ? $email : $this->encodeHeader($friendly) . " <{$email}>";
        $headers = [
            'To: ' . $incoming->envelopeSender,
            'From: ' . $from,
            'Subject: ' . $this->encodeHeader($subject),
            'Precedence: junk',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'MIME-Version: 1.0',
            'X-Loop: Postfix Admin Virtual Vacation',
            'Auto-Submitted: auto-replied',
        ];
        return implode("\r\n", $headers) . "\r\n\r\n" . $this->normalizeBody($body) . "\r\n";
    }

    /** @param array<string, mixed> $vacation @param array<string, mixed> $configuration */
    private function replaceDates(string $body, array $vacation, array $configuration): string
    {
        $format = (string)($configuration['reply_date_format'] ?? 'Y-m-d');
        $from = new DateTimeImmutable((string)$vacation['activefrom']);
        $until = new DateTimeImmutable((string)$vacation['activeuntil']);
        return str_ireplace(
            [
                (string)($configuration['reply_replace_from'] ?? '<%From_Date>'),
                (string)($configuration['reply_replace_until'] ?? '<%Until_Date>'),
            ],
            [$from->format($format), $until->format($format)],
            $body,
        );
    }

    private function decodeHeader(string $value): string
    {
        $decoded = mb_decode_mimeheader($value);
        return $decoded !== '' ? $decoded : $value;
    }

    private function encodeHeader(string $value): string
    {
        $value = str_replace(["\r", "\n"], '', $value);
        return mb_encode_mimeheader($value, 'UTF-8', 'Q', "\r\n");
    }

    private function normalizeBody(string $body): string
    {
        return str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", $body));
    }
}

final class VacationCli
{
    public const VERSION = '1.0.0';

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
            if ($arguments['inspect_message'] !== null) {
                return $this->runInspectMessage($arguments);
            }
            if ($arguments['show_config_path']) {
                return $this->showConfigPath($arguments);
            }
            if ($arguments['check']) {
                return $this->runCheck($arguments, false);
            }

            if ($arguments['envelope_sender'] !== null || $arguments['recipient'] !== null) {
                return $this->runTransport($arguments);
            }

            $this->writeError(
                'Use -f sender -- recipient for Vacation transport, or select --init-config, --check, --test, '
                . '--inspect-message, or --show-config-path.' . PHP_EOL
            );
            return 69;
        } catch (Throwable $exception) {
            $this->writeError('ERROR: ' . $exception->getMessage() . PHP_EOL);
            return 75;
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
            'inspect_message' => null,
            'version' => false,
            'config' => null,
            'import_legacy' => null,
            'postfixadmin_root' => null,
            'non_interactive' => false,
            'force' => false,
            'envelope_sender' => null,
            'recipient' => null,
            'transport_test' => false,
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
            '-t' => 'transport_test',
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
            if (!$positional && $argument === '--inspect-message') {
                if (!isset($argv[$index + 1])) {
                    throw new RuntimeException('Missing value for --inspect-message');
                }
                $arguments['inspect_message'] = $argv[++$index];
                ++$selectedActions;
                continue;
            }
            if (!$positional && str_starts_with($argument, '--inspect-message=')) {
                $arguments['inspect_message'] = substr($argument, strlen('--inspect-message='));
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
        try {
            $configuration = $this->includePostfixAdminConfig($configFile);
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

    private function includePostfixAdminConfig(string $configFile): mixed
    {
        global $CONF;

        $CONF = [];
        require $configFile;
        return $CONF;
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
        if (isset($parsed['message']) && is_array($parsed['message'])) {
            $mapping = [
                'recipient_delimiter' => 'recipient_delimiter',
                'no_vacation_pattern' => 'message_no_vacation_pattern',
                'noreply_pattern' => 'message_noreply_pattern',
                'default_noreply_pattern' => 'message_default_noreply_pattern',
            ];
            foreach ($mapping as $iniKey => $internalKey) {
                if (array_key_exists($iniKey, $parsed['message'])) {
                    $values[$internalKey] = (string)$parsed['message'][$iniKey];
                }
            }
            if (array_key_exists('custom_noreply_pattern', $parsed['message'])) {
                $values['message_custom_noreply_pattern'] = filter_var(
                    $parsed['message']['custom_noreply_pattern'],
                    FILTER_VALIDATE_BOOL,
                    FILTER_NULL_ON_FAILURE,
                ) ?? false;
            }
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
            $values['sendmail_path'] = trim((string)($parsed['smtp']['sendmail'] ?? ''));
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
            $values['sendmail_path'] = '';
        }
        if (isset($parsed['reply']) && is_array($parsed['reply'])) {
            $values['reply_friendly_from'] = (string)($parsed['reply']['friendly_from'] ?? '');
            $values['reply_account_name'] = filter_var(
                $parsed['reply']['account_name'] ?? false,
                FILTER_VALIDATE_BOOL,
                FILTER_NULL_ON_FAILURE,
            ) ?? false;
            $values['reply_replace_from'] = (string)($parsed['reply']['replace_from'] ?? '<%From_Date>');
            $values['reply_replace_until'] = (string)($parsed['reply']['replace_until'] ?? '<%Until_Date>');
            $values['reply_date_format'] = (string)($parsed['reply']['date_format'] ?? 'Y-m-d');
        }
        if (isset($parsed['logging']) && is_array($parsed['logging'])) {
            $values['log_syslog'] = filter_var(
                $parsed['logging']['syslog'] ?? true,
                FILTER_VALIDATE_BOOL,
                FILTER_NULL_ON_FAILURE,
            ) ?? true;
            $values['log_level'] = $this->normalizeLogLevel($parsed['logging']['level'] ?? 'info');
            $values['log_file'] = trim((string)($parsed['logging']['file'] ?? ''));
            $values['log_file_enabled'] = filter_var(
                $parsed['logging']['file_enabled'] ?? false,
                FILTER_VALIDATE_BOOL,
                FILTER_NULL_ON_FAILURE,
            ) ?? false;
        } else {
            $values['log_syslog'] = true;
            $values['log_level'] = 'info';
            $values['log_file'] = '';
            $values['log_file_enabled'] = false;
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

    /**
     * @param array<string, mixed> $smtpOptions
     * @param array<string, mixed> $messageOptions
     * @param array<string, mixed> $replyOptions
     * @param array<string, mixed> $loggingOptions
     */
    public function renderConfig(
        string $root,
        string $server,
        int $port,
        string $helo,
        array $smtpOptions = [],
        array $messageOptions = [],
        array $replyOptions = [],
        array $loggingOptions = [],
    ): string {
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
            'sendmail' => trim((string)($smtpOptions['sendmail'] ?? '')),
        ];
        if ($optional['security'] !== 'none') {
            $lines[] = 'security = ' . $this->iniValue($optional['security']);
        }
        if ($optional['timeout'] !== 120) {
            $lines[] = 'timeout = ' . $optional['timeout'];
        }
        foreach (['local_address', 'username', 'password', 'sendmail'] as $key) {
            if ($optional[$key] !== '') {
                $lines[] = $key . ' = ' . $this->iniValue((string)$optional[$key]);
            }
        }
        $messageValues = [
            'recipient_delimiter' => (string)($messageOptions['recipient_delimiter'] ?? ''),
            'no_vacation_pattern' => (string)($messageOptions['no_vacation_pattern'] ?? ''),
            'noreply_pattern' => (string)($messageOptions['noreply_pattern'] ?? ''),
            'default_noreply_pattern' => (string)($messageOptions['default_noreply_pattern'] ?? ''),
        ];
        $customNoReply = filter_var(
            $messageOptions['custom_noreply_pattern'] ?? false,
            FILTER_VALIDATE_BOOL,
        );
        if (array_filter($messageValues, static fn ($value) => $value !== '') !== [] || $customNoReply) {
            array_push($lines, '', '[message]');
            foreach ($messageValues as $key => $value) {
                if ($value !== '') {
                    $lines[] = $key . ' = ' . $this->iniValue($value);
                }
            }
            if ($customNoReply) {
                $lines[] = 'custom_noreply_pattern = true';
            }
        }
        $replyValues = [
            'friendly_from' => (string)($replyOptions['friendly_from'] ?? ''),
            'replace_from' => (string)($replyOptions['replace_from'] ?? ''),
            'replace_until' => (string)($replyOptions['replace_until'] ?? ''),
            'date_format' => (string)($replyOptions['date_format'] ?? ''),
        ];
        $accountName = filter_var($replyOptions['account_name'] ?? false, FILTER_VALIDATE_BOOL);
        if (array_filter($replyValues, static fn ($value) => $value !== '') !== [] || $accountName) {
            array_push($lines, '', '[reply]');
            foreach ($replyValues as $key => $value) {
                if ($value !== '') {
                    $lines[] = $key . ' = ' . $this->iniValue($value);
                }
            }
            if ($accountName) {
                $lines[] = 'account_name = true';
            }
        }
        $logSyslog = filter_var($loggingOptions['syslog'] ?? true, FILTER_VALIDATE_BOOL);
        $logFileEnabled = filter_var($loggingOptions['file_enabled'] ?? false, FILTER_VALIDATE_BOOL);
        array_push($lines, '', '[logging]');
        $lines[] = 'syslog = ' . ($logSyslog ? 'true' : 'false');
        $lines[] = 'level = ' . $this->iniValue($this->normalizeLogLevel($loggingOptions['level'] ?? 'info'));
        if ($logFileEnabled) {
            $lines[] = 'file_enabled = true';
            $lines[] = 'file = ' . $this->iniValue((string)($loggingOptions['file'] ?? '/var/log/vacation.log'));
        }
        $lines[] = '';
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
            'sendmail' => $legacy['sendmail_bin'] ?? '',
        ];
        $messageOptions = [
            'recipient_delimiter' => $legacy['recipient_delimiter'] ?? '',
            'no_vacation_pattern' => $legacy['no_vacation_pattern'] ?? '',
            'custom_noreply_pattern' => $legacy['custom_noreply_pattern'] ?? false,
            'noreply_pattern' => $legacy['noreply_pattern'] ?? '',
            'default_noreply_pattern' => $legacy['default_noreply_pattern'] ?? '',
        ];
        $replyOptions = [
            'friendly_from' => $legacy['friendly_from'] ?? '',
            'account_name' => $legacy['accountname_check'] ?? false,
            'replace_from' => $legacy['replace_from'] ?? '',
            'replace_until' => $legacy['replace_until'] ?? '',
            'date_format' => isset($legacy['date_format'])
                ? $this->perlDateFormatToPhp((string)$legacy['date_format'])
                : '',
        ];
        $legacyLogLevel = (int)($legacy['log_level'] ?? 1);
        $loggingOptions = [
            'syslog' => $legacy['syslog'] ?? true,
            'level' => $legacyLogLevel >= 2 ? 'debug' : ($legacyLogLevel === 1 ? 'info' : 'error'),
            'file_enabled' => $legacy['log_to_file'] ?? false,
            'file' => $legacy['logfile'] ?? '/var/log/vacation.log',
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
            $this->renderConfig(
                $root,
                $server,
                $port,
                $helo,
                $smtpOptions,
                $messageOptions,
                $replyOptions,
                $loggingOptions,
            ),
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
        if (trim((string)($configuration['sendmail_path'] ?? '')) !== '') {
            if (function_exists('proc_open')) {
                $results[] = new CheckResult('Dependencies', 'proc_open', 'OK', 'sendmail transport');
            } else {
                $results[] = new CheckResult('Dependencies', 'proc_open', 'MISSING', 'sendmail transport');
                $ok = false;
            }
        } elseif (trim((string)($configuration['smtp_server'] ?? 'localhost')) === '') {
            if (function_exists('dns_get_record')) {
                $results[] = new CheckResult('Dependencies', 'dns_get_record', 'OK', 'dynamic MX delivery');
            } else {
                $results[] = new CheckResult('Dependencies', 'dns_get_record', 'MISSING', 'dynamic MX delivery');
                $ok = false;
            }
        }
        if (trim((string)($configuration['sendmail_path'] ?? '')) === ''
            && ($configuration['smtp_security'] ?? 'none') !== 'none'
        ) {
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
    private function checkDatabase(array $configuration, bool $dependenciesOk, array &$results): void
    {
        if ($this->normalizeDatabaseType($configuration['database_type'] ?? '') === '') {
            $results[] = new CheckResult(
                'Database',
                'Connection',
                'NOT TESTED',
                'PostfixAdmin database configuration was not loaded',
            );
            return;
        }
        if (!$dependenciesOk) {
            $results[] = new CheckResult('Database', 'Connection', 'NOT TESTED', 'the required PDO driver is missing');
            return;
        }
        try {
            $database = $this->connectDatabase($configuration);
            $results[] = new CheckResult('Database', 'Connection', 'OK');
            $tables = is_array($configuration['resolved_tables'] ?? null)
                ? $configuration['resolved_tables']
                : array_combine(self::TABLE_KEYS, self::TABLE_KEYS);
            foreach (self::TABLE_KEYS as $key) {
                $table = (string)($tables[$key] ?? $key);
                if (!preg_match('/^[A-Za-z_][A-Za-z0-9_$]*$/', $table)) {
                    $results[] = new CheckResult('Database', "Table {$key}", 'FAILED', "unsafe identifier: {$table}");
                    continue;
                }
                $quoted = $this->quoteIdentifier($table, $this->normalizeDatabaseType($configuration['database_type'] ?? ''));
                try {
                    $database->query("SELECT 1 FROM {$quoted} LIMIT 1");
                    $results[] = new CheckResult('Database', "Table {$key}", 'OK', $table);
                } catch (Throwable $exception) {
                    $results[] = new CheckResult('Database', "Table {$key}", 'FAILED', $exception->getMessage());
                }
            }
        } catch (Throwable $exception) {
            $results[] = new CheckResult('Database', 'Connection', 'FAILED', $exception->getMessage());
        }
    }

    /** @param list<CheckResult> $results */
    private function checkSmtp(array $configuration, array &$results): void
    {
        $sendmail = trim((string)($configuration['sendmail_path'] ?? ''));
        if ($sendmail !== '') {
            $results[] = str_starts_with($sendmail, '/') && is_executable($sendmail)
                ? new CheckResult('Delivery', 'sendmail', 'OK', $sendmail)
                : new CheckResult('Delivery', 'sendmail', 'FAILED', 'an absolute executable path is required');
            return;
        }
        $server = (string)($configuration['smtp_server'] ?? 'localhost');
        if ($server === '') {
            $results[] = new CheckResult('Delivery', 'SMTP', 'OK', 'MX lookup selected at delivery time');
            return;
        }
        $port = $this->validPort($configuration['smtp_server_port'] ?? 25);
        $security = $this->normalizeSmtpSecurity($configuration['smtp_security'] ?? 'none');
        $helo = trim((string)($configuration['smtp_helo'] ?? '')) ?: $this->detectedFqdn();
        if ($helo === null || !$this->validHelo($helo)) {
            $results[] = new CheckResult('Delivery', 'SMTP', 'FAILED', 'a valid configured or detected HELO is required');
            return;
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
                return;
            }
            $results[] = new CheckResult('Delivery', 'SMTP', 'FAILED', "NOOP returned {$code}: {$response}");
        } catch (Throwable $exception) {
            $results[] = new CheckResult('Delivery', 'SMTP', 'FAILED', "{$server}:{$port}: {$exception->getMessage()}");
        }
    }

    /**
     * @param array<string, mixed> $configuration
     * @param list<CheckResult> $results
     */
    private function checkLogging(array $configuration, array &$results): void
    {
        if (empty($configuration['log_file_enabled'])) {
            return;
        }
        $path = trim((string)($configuration['log_file'] ?? ''));
        $writable = $path !== '' && (is_file($path) ? is_writable($path) : is_writable(dirname($path)));
        $results[] = $writable
            ? new CheckResult('Logging', 'File', 'OK', $path)
            : new CheckResult('Logging', 'File', 'FAILED', $path !== '' ? $path : 'logging.file is empty');
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

        if (!$dependenciesOnly) {
            $vacationDomain = trim((string)($configuration['vacation_domain'] ?? ''));
            $results[] = $vacationDomain !== ''
                ? new CheckResult('Configuration', 'Vacation domain', 'OK', $vacationDomain)
                : new CheckResult(
                    'Configuration',
                    'Vacation domain',
                    'MISSING',
                    'set vacation_domain in PostfixAdmin or [vacation] domain in vacation-php.conf',
                );
        }

        $this->checkDependencies($configuration, $results);
        if (!$dependenciesOnly && $vacationPath !== null) {
            $this->checkDatabase($configuration, $this->databaseDependenciesAvailable($configuration), $results);
            $this->checkSmtp($configuration, $results);
            $this->checkLogging($configuration, $results);
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
        if ($server === '') {
            throw new RuntimeException('--test requires a configured SMTP server; dynamic MX delivery is message-specific');
        }
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
    private function runTransport(array $arguments): int
    {
        $envelopeSender = $this->nullableString($arguments['envelope_sender']);
        $envelopeRecipient = $this->nullableString($arguments['recipient']);
        if ($envelopeSender === null || $envelopeRecipient === null) {
            throw new RuntimeException('Vacation transport requires -f sender -- recipient');
        }
        $configuration = $this->runtimeConfiguration($arguments);
        $claimedRepository = null;
        $claimedVacation = '';
        $claimedSender = '';
        $processingCompleted = false;
        try {
            $inspection = (new VacationMessageInspector())->inspectStream(
                $this->input,
                $envelopeSender,
                $envelopeRecipient,
                $configuration,
            );
            if (!$inspection->eligible) {
                $this->log($configuration, 'debug', 'Message ignored: ' . $inspection->reason);
                return 0;
            }

            $database = $this->connectDatabase($configuration);
            $tables = is_array($configuration['resolved_tables'] ?? null)
                ? $configuration['resolved_tables']
                : array_combine(self::TABLE_KEYS, self::TABLE_KEYS);
            $repository = new VacationRepository(
                $database,
                $tables,
                $this->normalizeDatabaseType($configuration['database_type'] ?? ''),
            );
            $vacation = $repository->findActiveVacation(
                $inspection->envelopeRecipient,
                (string)($configuration['vacation_domain'] ?? ''),
            );
            if ($vacation === null) {
                $this->log(
                    $configuration,
                    'debug',
                    "No active Vacation record for {$inspection->envelopeRecipient}",
                );
                return 0;
            }
            $vacationAddress = strtolower((string)$vacation['email']);
            if (!$repository->claimNotification($vacation, $inspection->envelopeSender)) {
                $this->log(
                    $configuration,
                    'debug',
                    "Notification interval has not elapsed for {$vacationAddress} and {$inspection->envelopeSender}",
                );
                return 0;
            }
            $claimedRepository = $repository;
            $claimedVacation = $vacationAddress;
            $claimedSender = $inspection->envelopeSender;

            $message = (new VacationReplyComposer())->compose(
                $vacation,
                $inspection,
                $configuration,
                $repository->accountName($vacationAddress),
            );
            $testMode = in_array(
                strtolower((string)($arguments['transport_test'] ?? '')),
                ['1', 'true', 'yes'],
                true,
            );
            if ($testMode) {
                $this->write($message);
                $this->log($configuration, 'info', 'Transport test generated a Vacation reply without delivery');
                return 0;
            }

            $sendmail = trim((string)($configuration['sendmail_path'] ?? ''));
            if ($sendmail !== '') {
                $this->deliverWithSendmail(
                    $sendmail,
                    $vacationAddress,
                    $inspection->envelopeSender,
                    $message,
                );
            } else {
                $deliveryConfiguration = $this->resolveDeliveryConfiguration($configuration, $vacationAddress);
                $this->deliverWithSmtp(
                    $deliveryConfiguration,
                    $vacationAddress,
                    $inspection->envelopeSender,
                    $message,
                );
            }
            $processingCompleted = true;
            $this->log(
                $configuration,
                'info',
                "Vacation response sent from {$vacationAddress} to {$inspection->envelopeSender}",
            );
            return 0;
        } catch (Throwable $exception) {
            try {
                if (!$processingCompleted && $claimedRepository instanceof VacationRepository) {
                    $claimedRepository->forgetNotification($claimedVacation, $claimedSender);
                }
                $this->log($configuration, 'error', $exception->getMessage());
            } catch (Throwable) {
                // Preserve the original transport failure for Postfix.
            }
            throw $exception;
        }
    }

    /** @param array<string, bool|string|null> $arguments @return array<string, mixed> */
    private function runtimeConfiguration(array $arguments): array
    {
        $path = $this->findVacationConfig($this->nullableString($arguments['config']));
        if ($path === null) {
            throw new RuntimeException('No vacation-php.conf found; run --init-config first or use --config');
        }
        $loaded = $this->loadVacationConfig($path);
        $configuration = $loaded['values'];
        foreach ($loaded['warnings'] as $warning) {
            $this->writeError("WARNING: {$warning}" . PHP_EOL);
        }
        $rootArgument = $this->nullableString($arguments['postfixadmin_root'])
            ?? ($configuration['postfixadmin_root'] ?? null);
        $roots = $this->discoverPostfixAdminRoots(is_string($rootArgument) ? $rootArgument : null);
        if ($roots === []) {
            throw new RuntimeException('No PostfixAdmin installation found; use --postfixadmin-root');
        }
        $postfixAdmin = $this->loadPostfixAdminConfig($this->choosePostfixAdminRoot($roots, true));
        $configuration = array_replace($postfixAdmin, $configuration);
        if (trim((string)($configuration['vacation_domain'] ?? '')) === '') {
            throw new RuntimeException('vacation_domain is required for Vacation transport');
        }
        return $configuration;
    }

    /** @param array<string, mixed> $configuration @return array<string, mixed> */
    private function resolveDeliveryConfiguration(array $configuration, string $vacationAddress): array
    {
        if (trim((string)($configuration['smtp_server'] ?? 'localhost')) !== '') {
            return $configuration;
        }
        [, $domain] = explode('@', $vacationAddress, 2);
        $records = dns_get_record($domain, DNS_MX);
        if (!is_array($records) || $records === []) {
            throw new RuntimeException("No MX record found for {$domain}");
        }
        usort($records, static fn ($left, $right) => ($left['pri'] ?? 0) <=> ($right['pri'] ?? 0));
        $target = rtrim((string)($records[0]['target'] ?? ''), '.');
        if ($target === '') {
            throw new RuntimeException("No usable MX target found for {$domain}");
        }
        $configuration['smtp_server'] = $target;
        if (($configuration['smtp_local_address'] ?? '') === 'localhost') {
            $configuration['smtp_local_address'] = '';
        }
        return $configuration;
    }

    /** @param array<string, mixed> $configuration */
    private function deliverWithSmtp(
        array $configuration,
        string $sender,
        string $recipient,
        string $message,
    ): void {
        $helo = $this->resolveSmtpHelo($configuration);
        $socket = $this->smtpConnect($configuration, $helo);
        try {
            $this->smtpExpect($this->smtpCommand($socket, "MAIL FROM:<{$sender}>"), [250], 'MAIL FROM');
            $this->smtpExpect($this->smtpCommand($socket, "RCPT TO:<{$recipient}>"), [250, 251], 'RCPT TO');
            $this->smtpExpect($this->smtpCommand($socket, 'DATA'), [354], 'DATA');
            $message = preg_replace('/(?m)^\./', '..', $message) ?? $message;
            if (fwrite($socket, rtrim($message, "\r\n") . "\r\n.\r\n") === false) {
                throw new RuntimeException('Could not write the Vacation message to SMTP');
            }
            $this->smtpExpect($this->smtpReadResponse($socket), [250], 'message body');
        } finally {
            $this->smtpQuit($socket);
        }
    }

    private function deliverWithSendmail(string $path, string $sender, string $recipient, string $message): void
    {
        if (!str_starts_with($path, '/') || !is_executable($path)) {
            throw new RuntimeException('sendmail must be an absolute executable path');
        }
        $process = proc_open(
            [$path, '-f', $sender, $recipient],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
        );
        if (!is_resource($process)) {
            throw new RuntimeException("Could not execute sendmail: {$path}");
        }
        fwrite($pipes[0], $message);
        fclose($pipes[0]);
        $standardOutput = stream_get_contents($pipes[1]);
        $standardError = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status !== 0) {
            $detail = trim((string)$standardError) ?: trim((string)$standardOutput);
            throw new RuntimeException("sendmail exited with status {$status}" . ($detail !== '' ? ": {$detail}" : ''));
        }
    }

    /** @param array<string, mixed> $configuration */
    private function log(array $configuration, string $level, string $message): void
    {
        $priorities = ['error' => LOG_ERR, 'info' => LOG_INFO, 'debug' => LOG_DEBUG];
        $configured = strtolower((string)($configuration['log_level'] ?? 'info'));
        $rank = ['error' => 0, 'info' => 1, 'debug' => 2];
        if (($rank[$level] ?? 0) > ($rank[$configured] ?? 1)) {
            return;
        }
        if (!empty($configuration['log_syslog'])) {
            openlog('postfixadmin-vacation', LOG_PID, LOG_MAIL);
            syslog($priorities[$level] ?? LOG_INFO, $message);
            closelog();
        }
        if (!empty($configuration['log_file_enabled'])) {
            $path = trim((string)($configuration['log_file'] ?? ''));
            if ($path === '') {
                throw new RuntimeException('File logging is enabled but logging.file is empty');
            }
            $line = sprintf("%s %-5s %s%s", date(DATE_ATOM), strtoupper($level), $message, PHP_EOL);
            if (file_put_contents($path, $line, FILE_APPEND | LOCK_EX) === false) {
                throw new RuntimeException("Could not write Vacation log: {$path}");
            }
        }
    }

    /** @param array<string, bool|string|null> $arguments */
    private function runInspectMessage(array $arguments): int
    {
        $envelopeSender = $this->nullableString($arguments['envelope_sender']);
        $envelopeRecipient = $this->nullableString($arguments['recipient']);
        if ($envelopeSender === null || $envelopeRecipient === null) {
            throw new RuntimeException('--inspect-message requires -f sender -- recipient');
        }

        $configuration = [];
        $configPath = $this->findVacationConfig($this->nullableString($arguments['config']));
        if ($configPath !== null) {
            $loaded = $this->loadVacationConfig($configPath);
            $configuration = $loaded['values'];
            foreach ($loaded['warnings'] as $warning) {
                $this->writeError("WARNING: {$warning}" . PHP_EOL);
            }
        }
        $rootArgument = $this->nullableString($arguments['postfixadmin_root'])
            ?? ($configuration['postfixadmin_root'] ?? null);
        $roots = $this->discoverPostfixAdminRoots(is_string($rootArgument) ? $rootArgument : null);
        if ($roots === []) {
            throw new RuntimeException('No PostfixAdmin installation found; use --postfixadmin-root');
        }
        $postfixAdmin = $this->loadPostfixAdminConfig($this->choosePostfixAdminRoot($roots, true));
        $configuration = array_replace($postfixAdmin, $configuration);

        $messagePath = $this->nullableString($arguments['inspect_message']);
        if ($messagePath === null) {
            throw new RuntimeException('--inspect-message requires a file path or - for standard input');
        }
        $closeStream = false;
        if ($messagePath === '-') {
            $stream = $this->input;
        } else {
            $messagePath = $this->expandHome($messagePath);
            $stream = fopen($messagePath, 'rb');
            if ($stream === false) {
                throw new RuntimeException("Could not open message: {$messagePath}");
            }
            $closeStream = true;
        }
        try {
            $result = (new VacationMessageInspector())->inspectStream(
                $stream,
                $envelopeSender,
                $envelopeRecipient,
                $configuration,
            );
        } finally {
            if ($closeStream) {
                fclose($stream);
            }
        }

        $status = $result->eligible ? 'ELIGIBLE' : 'IGNORED';
        $this->write("Message inspection: {$status} - {$result->reason}" . PHP_EOL);
        if ($result->eligible) {
            $this->write("Envelope sender: {$result->envelopeSender}" . PHP_EOL);
            $this->write("Vacation recipient: {$result->envelopeRecipient}" . PHP_EOL);
            $this->write("Message-ID: {$result->messageId}" . PHP_EOL);
        }
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

    private function normalizeLogLevel(mixed $value): string
    {
        $level = strtolower(trim((string)$value));
        if (!in_array($level, ['error', 'info', 'debug'], true)) {
            throw new RuntimeException('Logging level must be error, info, or debug');
        }
        return $level;
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

    private function perlDateFormatToPhp(string $format): string
    {
        return strtr($format, [
            '%Y' => 'Y',
            '%y' => 'y',
            '%m' => 'm',
            '%d' => 'd',
            '%b' => 'M',
            '%B' => 'F',
            '%H' => 'H',
            '%M' => 'i',
            '%S' => 's',
            '%%' => '%',
        ]);
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
