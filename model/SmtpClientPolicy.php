<?php

/**
 * Postfix policy decision for $CONF['domain_smtp_client_ips'].
 *
 * Binds hosts that forward mail without SMTP authentication (listed in
 * domain.smtp_client_ips) to the sender domain(s) they are listed for.
 * Used by scripts/postfixadmin-smtp-client-policy.php, see
 * DOCUMENTS/SMTP_CLIENT_IPS.md.
 *
 * Every request must contain request=smtpd_access_policy, a client_address
 * that is an IP address and a sender attribute (empty for the null sender);
 * otherwise, and for attribute lines without "=", the answer is
 * INVALID_REQUEST (a temporary failure).
 *
 * Decision order (first match wins):
 *   1. SASL authenticated session            -> DUNNO
 *   2. client not listed for any domain:
 *        inside client_networks and
 *        unassigned_client_action = reject   -> REJECT
 *        otherwise                           -> DUNNO
 *   3. client listed, but only for inactive
 *      domains                               -> REJECT
 *   4. null sender (reject_null_sender)      -> REJECT
 *   5. sender domain not one of the client's
 *      active domains / their alias domains  -> REJECT
 *   6. require_existing_sender and sender is
 *      no active mailbox or alias            -> REJECT
 *   7.                                       -> DUNNO
 *
 * Any exception while deciding (e.g. database unavailable) results in
 * DEFER_IF_PERMIT, so nothing passes unchecked. Afterwards serve() ends the
 * process: the database layer keeps one connection per process, and a broken
 * connection (e.g. after a database restart) is not re-established. Postfix
 * starts a new process for the next request, which connects again.
 */
class SmtpClientPolicy
{
    public const TEMPFAIL = 'DEFER_IF_PERMIT 4.3.0 SMTP client policy temporarily unavailable';
    public const INVALID_REQUEST = 'DEFER_IF_PERMIT 4.3.0 SMTP client policy: invalid request';

    /** restriction class used in the documented Postfix cidr map */
    public const CIDR_MAP_CLASS = 'pfa_smtp_client_policy';

    private const DEFAULTS = [
        'unassigned_client_action' => 'reject',
        'client_networks' => [],
        'require_existing_sender' => true,
        'reject_null_sender' => true,
        'allow_subdomains' => false,
        'cache_ttl' => 30,
        'database_user' => null,
        'database_password' => null,
        'client_networks_map' => null,
        'client_networks_map_class' => self::CIDR_MAP_CLASS,
    ];

    /** @var array<string,mixed> */
    private array $options;

    /** @var array<int,array{0:string,1:int}> */
    private array $clientNetworks = [];

    /** @var callable():array{assignments:array<int,array{network:array{0:string,1:int},domain:string,active:bool}>,aliases:array<string,string>} */
    private $loader;

    /** @var callable(string[]):bool */
    private $senderExists;

    /** @var callable():int */
    private $clock;

    private ?array $cache = null;
    private int $cacheTime = 0;
    private bool $failed = false;

    /**
     * @param array<string,mixed> $options $CONF['smtp_client_policy']
     * @throws InvalidArgumentException on invalid options
     */
    public function __construct(array $options, ?callable $loader = null, ?callable $senderExists = null, ?callable $clock = null)
    {
        $this->options = self::validateOptions($options);
        if ($this->options['client_networks_map'] !== null) {
            // read once per process - like smtpd, which reads the map when it starts
            $this->options['client_networks'] = self::networksFromCidrMap(
                self::readCidrMapFile($this->options['client_networks_map']),
                $this->options['client_networks_map_class']
            );
        }
        foreach ($this->options['client_networks'] as $network) {
            $this->clientNetworks[] = self::network($network);
        }
        $this->loader = $loader ?? [self::class, 'loadFromDatabase'];
        $this->senderExists = $senderExists ?? [self::class, 'senderExistsInDatabase'];
        $this->clock = $clock ?? 'time';
    }

    /**
     * Validate $CONF['smtp_client_policy'] and fill in defaults.
     *
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     * @throws InvalidArgumentException
     */
    public static function validateOptions(array $options): array
    {
        $unknown = array_diff(array_keys($options), array_keys(self::DEFAULTS));
        if ($unknown !== []) {
            throw new InvalidArgumentException('smtp_client_policy: unknown option(s): ' . implode(', ', $unknown));
        }
        $o = array_merge(self::DEFAULTS, $options);

        if (!in_array($o['unassigned_client_action'], ['reject', 'dunno'], true)) {
            throw new InvalidArgumentException("smtp_client_policy: unassigned_client_action must be 'reject' or 'dunno'");
        }

        $networks = $o['client_networks'];
        if (is_string($networks)) {
            $networks = SmtpClientIpList::split($networks);
        }
        if (!is_array($networks)) {
            throw new InvalidArgumentException('smtp_client_policy: client_networks must be a list of networks');
        }
        $normalized = [];
        foreach ($networks as $network) {
            $error = null;
            $canonical = is_string($network) ? SmtpClientIpList::normalize($network, $error) : null;
            if ($canonical === null) {
                throw new InvalidArgumentException('smtp_client_policy: invalid entry in client_networks: ' . var_export($network, true)
                    . ($error === SmtpClientIpList::ERROR_HOST_BITS ? ' (host bits set)' : ''));
            }
            $normalized[] = $canonical;
        }
        $o['client_networks'] = array_values(array_unique($normalized));

        $map = $o['client_networks_map'];
        if ($map !== null && (!is_string($map) || $map === '' || $map[0] !== '/')) {
            throw new InvalidArgumentException('smtp_client_policy: client_networks_map must be an absolute path');
        }
        if (!is_string($o['client_networks_map_class']) || !preg_match('/^[A-Za-z0-9_.-]+$/D', $o['client_networks_map_class'])) {
            throw new InvalidArgumentException('smtp_client_policy: client_networks_map_class must be a restriction class name');
        }
        if ($map !== null && $o['client_networks'] !== []) {
            throw new InvalidArgumentException('smtp_client_policy: set either client_networks or client_networks_map, not both');
        }
        if ($o['unassigned_client_action'] === 'reject' && $o['client_networks'] === [] && $map === null) {
            throw new InvalidArgumentException("smtp_client_policy: unassigned_client_action 'reject' requires client_networks or client_networks_map (the networks of the forwarding hosts)");
        }

        foreach (['require_existing_sender', 'reject_null_sender', 'allow_subdomains'] as $key) {
            if (!is_bool($o[$key])) {
                throw new InvalidArgumentException("smtp_client_policy: $key must be true or false");
            }
        }
        if (!is_int($o['cache_ttl']) || $o['cache_ttl'] < 0) {
            throw new InvalidArgumentException('smtp_client_policy: cache_ttl must be an integer >= 0');
        }
        foreach (['database_user', 'database_password'] as $key) {
            if ($o[$key] !== null && !is_string($o[$key])) {
                throw new InvalidArgumentException("smtp_client_policy: $key must be a string");
            }
        }

        return $o;
    }

    /** @return array<string,mixed> */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * Decide one policy request.
     *
     * @param array<string,string> $attrs Postfix policy attributes
     * @return string action without the "action=" prefix
     */
    public function decide(array $attrs): string
    {
        $error = self::requestError($attrs);
        if ($error !== null) {
            self::log(LOG_WARNING, "invalid request: $error");
            return self::INVALID_REQUEST;
        }
        try {
            return $this->decideOrThrow($attrs);
        } catch (Throwable $e) {
            self::log(LOG_ERR, 'temporary failure: ' . $e->getMessage());
            $this->failed = true;
            return self::TEMPFAIL;
        }
    }

    /**
     * True after a request failed with an exception (e.g. a database error);
     * the process should then exit so that the next one starts with a fresh
     * database connection.
     */
    /**
     * Check the attributes the decision depends on. Postfix always sends them;
     * anything else is not a request this service can answer reliably.
     *
     * @param array<string,string> $attrs
     * @return string|null reason, or null if the request is valid
     */
    public static function requestError(array $attrs): ?string
    {
        if (($attrs['request'] ?? null) !== 'smtpd_access_policy') {
            return 'request is not smtpd_access_policy';
        }
        if (!isset($attrs['client_address'])) {
            return 'client_address missing';
        }
        if (SmtpClientIpList::packAddress($attrs['client_address']) === null) {
            return 'client_address is not an IP address';
        }
        if (!array_key_exists('sender', $attrs)) {
            return 'sender missing';
        }
        return null;
    }

    public function failed(): bool
    {
        return $this->failed;
    }

    private function decideOrThrow(array $attrs): string
    {
        $client = (string)($attrs['client_address'] ?? '');
        $sender = (string)($attrs['sender'] ?? '');

        if (($attrs['sasl_username'] ?? '') !== '') {
            return 'DUNNO';
        }

        $data = $this->data();
        $listed = false;
        $allowed = [];
        foreach ($data['assignments'] as $assignment) {
            if (SmtpClientIpList::contains($assignment['network'], $client)) {
                $listed = true;
                if ($assignment['active']) {
                    $allowed[$assignment['domain']] = true;
                }
            }
        }

        if (!$listed) {
            if ($this->options['unassigned_client_action'] === 'reject' && $this->inClientNetworks($client)) {
                self::log(LOG_NOTICE, "REJECT unassigned client=$client sender=<$sender>");
                return 'REJECT 5.7.1 Client is not assigned to any domain';
            }
            return 'DUNNO';
        }

        if ($allowed === []) {
            self::log(LOG_NOTICE, "REJECT client=$client sender=<$sender>: only assigned to inactive domains");
            return 'REJECT 5.7.1 Client is not assigned to an active domain';
        }

        // alias domains of the client's domains are permitted as well
        foreach ($data['aliases'] as $alias => $target) {
            if (isset($allowed[$target])) {
                $allowed[$alias] = true;
            }
        }

        if ($sender === '') {
            if ($this->options['reject_null_sender']) {
                self::log(LOG_NOTICE, "REJECT null sender client=$client");
                return 'REJECT 5.7.1 Null sender not permitted from this client';
            }
            return 'DUNNO';
        }

        $at = strrpos($sender, '@');
        $local = $at === false ? '' : strtolower(substr($sender, 0, $at));
        $senderDomain = strtolower(rtrim($at === false ? $sender : substr($sender, $at + 1), '.'));

        if (!$this->domainAllowed($senderDomain, $allowed)) {
            self::log(LOG_NOTICE, "REJECT client=$client sender=<$sender> allowed=" . implode(',', array_keys($allowed)));
            return "REJECT 5.7.1 Sender domain $senderDomain not permitted for this client";
        }

        if (!$this->options['require_existing_sender']) {
            return 'DUNNO';
        }

        $candidates = ["$local@$senderDomain", "@$senderDomain"];
        if (isset($data['aliases'][$senderDomain])) {
            $target = $data['aliases'][$senderDomain];
            $candidates[] = "$local@$target";
            $candidates[] = "@$target";
        }
        if (($this->senderExists)($candidates)) {
            return 'DUNNO';
        }

        self::log(LOG_NOTICE, "REJECT client=$client sender=<$sender>: sender does not exist");
        return "REJECT 5.7.1 Sender address <$sender> does not exist";
    }

    /** @param array<string,bool> $allowed */
    private function domainAllowed(string $domain, array $allowed): bool
    {
        if (isset($allowed[$domain])) {
            return true;
        }
        if ($this->options['allow_subdomains']) {
            foreach (array_keys($allowed) as $parent) {
                if (substr($domain, -strlen($parent) - 1) === '.' . $parent) {
                    return true;
                }
            }
        }
        return false;
    }

    private function inClientNetworks(string $client): bool
    {
        foreach ($this->clientNetworks as $network) {
            if (SmtpClientIpList::contains($network, $client)) {
                return true;
            }
        }
        return false;
    }

    private function data(): array
    {
        $now = ($this->clock)();
        if ($this->cache === null || $now - $this->cacheTime >= $this->options['cache_ttl']) {
            $this->cache = ($this->loader)();
            $this->cacheTime = $now;
        }
        return $this->cache;
    }

    /**
     * Read all smtp_client_ips assignments, including those of inactive
     * domains (so a client of a deactivated domain stays "listed" and is
     * rejected instead of falling back to permit_mynetworks).
     *
     * Invalid stored entries (e.g. written by other tools) are skipped and logged.
     */
    public static function loadFromDatabase(): array
    {
        $assignments = [];
        $rows = db_query_all(
            'SELECT domain, active, smtp_client_ips FROM ' . table_by_key('domain') . " WHERE smtp_client_ips <> ''"
        );
        foreach ($rows as $row) {
            $domain = strtolower((string)$row['domain']);
            $active = in_array($row['active'], [true, 1, '1', 't', 'true'], true);
            foreach (SmtpClientIpList::split((string)$row['smtp_client_ips']) as $entry) {
                $network = SmtpClientIpList::parse($entry);
                if ($network === null) {
                    self::log(LOG_WARNING, "ignoring invalid entry '$entry' in smtp_client_ips of $domain");
                    continue;
                }
                $assignments[] = ['network' => $network, 'domain' => $domain, 'active' => $active];
            }
        }

        $aliases = [];
        $rows = db_query_all(
            'SELECT alias_domain, target_domain FROM ' . table_by_key('alias_domain') . ' WHERE active = :active',
            ['active' => db_get_boolean(true)]
        );
        foreach ($rows as $row) {
            $aliases[strtolower((string)$row['alias_domain'])] = strtolower((string)$row['target_domain']);
        }

        return ['assignments' => $assignments, 'aliases' => $aliases];
    }

    /**
     * Is one of $candidates an active alias or mailbox?
     * (A catch-all "@domain" alias counts, like Postfix' reject_unlisted_sender.)
     *
     * @param string[] $candidates
     */
    public static function senderExistsInDatabase(array $candidates): bool
    {
        $params = ['active' => db_get_boolean(true)];
        $row = db_query_one(
            'SELECT 1 AS found FROM ' . table_by_key('alias') . ' WHERE active = :active AND ' . db_in_clause('address', $candidates, $params),
            $params
        );
        if ($row !== null) {
            return true;
        }

        $params = ['active' => db_get_boolean(true)];
        $row = db_query_one(
            'SELECT 1 AS found FROM ' . table_by_key('mailbox') . ' WHERE active = :active AND ' . db_in_clause('username', $candidates, $params),
            $params
        );
        return $row !== null;
    }

    /**
     * Handle the Postfix policy delegation protocol on the given streams
     * until end of input. Returns the number of requests answered.
     *
     * @param resource $in
     * @param resource $out
     * @param string|null $startupError if set, every request gets TEMPFAIL
     */
    public static function serve($in, $out, ?self $policy, ?string $startupError = null): int
    {
        $count = 0;
        $attrs = [];
        $tooLong = false;
        $malformed = false;
        $skipping = false;
        while (($line = fgets($in, 8192)) !== false) {
            if (substr($line, -1) !== "\n" && !feof($in)) {
                // over-long attribute line: skip it, answer TEMPFAIL for this request
                $tooLong = true;
                $skipping = true;
                continue;
            }
            if ($skipping) {
                $skipping = false; // rest of the over-long line
                continue;
            }
            $line = rtrim($line, "\r\n");
            if ($line !== '') {
                $pos = strpos($line, '=');
                if ($pos === false) {
                    $malformed = true; // not "name=value"
                } else {
                    $attrs[substr($line, 0, $pos)] = substr($line, $pos + 1);
                }
                continue;
            }

            if ($policy === null || $startupError !== null || $tooLong) {
                $action = self::TEMPFAIL;
            } elseif ($malformed) {
                self::log(LOG_WARNING, 'invalid request: attribute line without "="');
                $action = self::INVALID_REQUEST;
            } else {
                $action = $policy->decide($attrs);
            }
            fwrite($out, "action=$action\n\n");
            fflush($out);
            $count++;
            $attrs = [];
            $tooLong = false;
            $malformed = false;

            if ($policy !== null && $startupError === null && $policy->failed()) {
                self::log(LOG_NOTICE, 'exiting after a temporary failure, the next request starts a new process');
                break;
            }
        }
        return $count;
    }

    /**
     * Content of the Postfix cidr map that sends the client_networks to the
     * policy (see DOCUMENTS/SMTP_CLIENT_IPS.md).
     */
    public function cidrMap(?string $class = null): string
    {
        $class = $class ?? $this->options['client_networks_map_class'];
        $map = "# generated by postfixadmin-smtp-client-policy.php --print-cidr-map\n"
            . "# = \$CONF['smtp_client_policy']['client_networks']; regenerate after changing it\n";
        foreach ($this->options['client_networks'] as $network) {
            $map .= "$network\t$class\n";
        }
        return $map;
    }

    /**
     * Parse a Postfix cidr map. Lines this parser does not evaluate
     * (continuation lines, if/endif, patterns it cannot read) are reported as
     * warnings, lines Postfix does not accept (host bits) as errors.
     *
     * @return array{entries:array<int,array{line:int,text:string,network:array{0:string,1:int},action:string}>,errors:string[],warnings:string[]}
     */
    public static function parseCidrMap(string $map): array
    {
        $errors = [];
        $warnings = [];
        $entries = [];
        foreach (preg_split('/\r?\n/', $map) ?: [] as $i => $raw) {
            $no = $i + 1;
            $line = trim($raw);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (preg_match('/^\s/', $raw)) {
                $warnings[] = "line $no: continuation lines are not evaluated";
                continue;
            }
            $parts = preg_split('/\s+/', $line, 2) ?: [$line];
            $pattern = $parts[0];
            if (in_array(strtolower($pattern), ['if', 'endif'], true) || $pattern[0] === '!') {
                $warnings[] = "line $no: if/endif blocks are not evaluated";
                continue;
            }
            $pattern = (string)preg_replace('/^\[([^\]]+)\]/', '$1', $pattern);
            $error = null;
            $canonical = SmtpClientIpList::normalize($pattern, $error);
            if ($canonical === null) {
                if ($error === SmtpClientIpList::ERROR_HOST_BITS) {
                    $errors[] = "line $no: '{$parts[0]}' has host bits set - Postfix does not accept this line";
                } else {
                    $warnings[] = "line $no: '{$parts[0]}' is not evaluated";
                }
                continue;
            }
            $entries[] = [
                'line' => $no,
                'text' => $canonical,
                'network' => self::network($canonical),
                'action' => strtok($parts[1] ?? '', " \t") ?: '',
            ];
        }
        return ['entries' => $entries, 'errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Networks of $class in a cidr map that is used as the source of the
     * policy networks (option client_networks_map). Stricter than the check:
     * any line that cannot be evaluated is an error, because it could change
     * which lines Postfix matches first.
     *
     * @return string[]
     * @throws InvalidArgumentException
     */
    public static function networksFromCidrMap(string $map, string $class, string $name = 'client_networks_map'): array
    {
        $parsed = self::parseCidrMap($map);
        $problems = array_merge($parsed['errors'], $parsed['warnings']);
        if ($problems !== []) {
            throw new InvalidArgumentException("smtp_client_policy: $name: " . implode('; ', $problems));
        }
        $networks = [];
        foreach ($parsed['entries'] as $entry) {
            if ($entry['action'] === $class && !in_array($entry['text'], $networks, true)) {
                $networks[] = $entry['text'];
            }
        }
        if ($networks === []) {
            throw new InvalidArgumentException("smtp_client_policy: $name contains no entry for $class");
        }
        return $networks;
    }

    /**
     * Read the cidr map file with a precise reason on failure.
     *
     * @param string|null $openBasedir value of open_basedir (default: current setting)
     * @throws RuntimeException
     */
    public static function readCidrMapFile(string $path, ?string $openBasedir = null): string
    {
        $openBasedir = $openBasedir ?? (string)ini_get('open_basedir');
        $error = self::openBasedirError($path, $openBasedir);
        if ($error !== null) {
            throw new RuntimeException($error);
        }
        if (!@file_exists($path)) {
            throw new RuntimeException("$path does not exist" . ($openBasedir !== ''
                ? " or is not accessible (open_basedir is set; a symlink pointing outside it is blocked as well)" : ''));
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("$path is not readable as " . self::fileAccessInfo($path));
        }
        return $content;
    }

    /**
     * Is $path outside the open_basedir setting? Same rule as PHP: every entry
     * is a path prefix ("." = current directory). Symlinks are resolved by PHP
     * when the file is opened; that case is reported by readCidrMapFile().
     */
    public static function openBasedirError(string $path, string $openBasedir): ?string
    {
        if ($openBasedir === '') {
            return null;
        }
        foreach (explode(PATH_SEPARATOR, $openBasedir) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            if ($entry === '.') {
                $entry = (string)getcwd();
            }
            if (strncmp($path, $entry, strlen($entry)) === 0) {
                return null;
            }
        }
        return "$path is outside open_basedir ($openBasedir) - add it to open_basedir";
    }

    /**
     * Parse a network that is known to be valid (normalized before).
     *
     * @return array{0:string,1:int}
     */
    private static function network(string $network): array
    {
        $parsed = SmtpClientIpList::parse($network);
        if ($parsed === null) {
            throw new InvalidArgumentException("invalid network: $network");
        }
        return $parsed;
    }

    private static function fileAccessInfo(string $path): string
    {
        $user = 'uid ' . (function_exists('posix_geteuid') ? posix_geteuid() : (int)getmyuid());
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $pw = posix_getpwuid(posix_geteuid());
            if (is_array($pw)) {
                $user = 'user ' . $pw['name'];
            }
        }
        $stat = @stat($path);
        if ($stat === false) {
            return $user;
        }
        $owner = (string)$stat['uid'];
        $group = (string)$stat['gid'];
        if (function_exists('posix_getpwuid') && is_array($pw = posix_getpwuid($stat['uid']))) {
            $owner = $pw['name'];
        }
        if (function_exists('posix_getgrgid') && is_array($gr = posix_getgrgid($stat['gid']))) {
            $group = $gr['name'];
        }
        return sprintf('%s (owner %s:%s, mode %04o)', $user, $owner, $group, $stat['mode'] & 07777);
    }

    /**
     * Which line of a parsed cidr map does Postfix use for $network?
     * 'problem' is null if the whole network is sent to the policy (lines with
     * another action for a part of the network are exceptions and skipped).
     *
     * @param array<int,array{line:int,text:string,network:array{0:string,1:int},action:string}> $entries
     * @param array{0:string,1:int} $network
     * @return array{problem:?string,line:?int}
     */
    private static function firstMatch(array $entries, array $network, string $class): array
    {
        $base = (string)inet_ntop($network[0]);
        foreach ($entries as $entry) {
            if (!SmtpClientIpList::contains($entry['network'], $base)) {
                continue;
            }
            if ($entry['action'] !== $class && $entry['network'][1] > $network[1]) {
                continue; // exception for a part of the network
            }
            if ($entry['action'] !== $class) {
                return ['problem' => "matched first by line {$entry['line']} ({$entry['text']} {$entry['action']})", 'line' => $entry['line']];
            }
            if ($entry['network'][1] > $network[1]) {
                return ['problem' => "only partly covered (line {$entry['line']}: {$entry['text']})", 'line' => $entry['line']];
            }
            return ['problem' => null, 'line' => $entry['line']];
        }
        return ['problem' => 'not in the map', 'line' => null];
    }

    /**
     * Exceptions (lines with another action) for parts of $network that come
     * before the policy line at $policyLine.
     *
     * @param array<int,array{line:int,text:string,network:array{0:string,1:int},action:string}> $entries
     * @param array{0:string,1:int} $network
     * @return string[] "line N: network ACTION"
     */
    private static function exceptionsInside(array $entries, array $network, string $class, int $policyLine): array
    {
        $result = [];
        foreach ($entries as $entry) {
            if ($entry['line'] < $policyLine && $entry['action'] !== $class && $entry['network'][1] > $network[1]
                && SmtpClientIpList::contains($network, (string)inet_ntop($entry['network'][0]))) {
                $result[] = "line {$entry['line']}: {$entry['text']} {$entry['action']}";
            }
        }
        return $result;
    }

    /**
     * Warnings for the SMTP client IPs of a domain (web interface): entries the
     * policy will not check because Postfix does not send them to it.
     * With client_networks_map the entries are compared with the Postfix map
     * (first match, exceptions); with client_networks only with that list.
     *
     * @param array<string,mixed> $options $CONF['smtp_client_policy']
     * @return array{not_checked:string[],partly:string[],failed:?string}
     */
    public static function addressWarnings(string $list, array $options): array
    {
        $result = ['not_checked' => [], 'partly' => [], 'failed' => null];
        $entries = [];
        foreach (SmtpClientIpList::split($list) as $raw) {
            $text = SmtpClientIpList::normalize($raw);
            if ($text !== null && !isset($entries[$text])) {
                $entries[$text] = self::network($text);
            }
        }
        if ($entries === []) {
            return $result;
        }
        try {
            $policy = new self($options);
            $o = $policy->options();
            $map = $o['client_networks_map'] !== null ? self::parseCidrMap(self::readCidrMapFile($o['client_networks_map'])) : null;
        } catch (Throwable $e) {
            $result['failed'] = $e->getMessage();
            return $result;
        }

        foreach ($entries as $text => $network) {
            if ($map === null) {
                $inside = false;
                foreach ($policy->clientNetworks as $policyNetwork) {
                    if ($policyNetwork[1] <= $network[1] && SmtpClientIpList::contains($policyNetwork, (string)inet_ntop($network[0]))) {
                        $inside = true;
                        break;
                    }
                }
                if (!$inside) {
                    $result['not_checked'][] = "$text (outside client_networks)";
                }
                continue;
            }
            $match = self::firstMatch($map['entries'], $network, $o['client_networks_map_class']);
            if ($match['problem'] === 'not in the map') {
                $result['not_checked'][] = "$text (not in {$o['client_networks_map']})";
            } elseif ($match['problem'] !== null && strpos($match['problem'], 'only partly') === 0) {
                $result['partly'][] = "$text ({$match['problem']})";
            } elseif ($match['problem'] !== null) {
                $result['not_checked'][] = "$text ({$match['problem']})";
            } else {
                $exceptions = self::exceptionsInside($map['entries'], $network, $o['client_networks_map_class'], (int)$match['line']);
                if ($exceptions !== []) {
                    $result['partly'][] = "$text (" . implode('; ', $exceptions) . ')';
                }
            }
        }
        return $result;
    }

    /**
     * Compare a Postfix cidr map with the policy networks and the stored
     * assignments. Postfix uses the first matching line of a cidr map, so a
     * network counts as routed to the policy if the first line matching its
     * network address has the policy class and covers the whole network.
     *
     * @param array<int,array{network:array{0:string,1:int},domain:string,active:bool}> $assignments
     * @return array{errors:string[],warnings:string[]}
     */
    public function checkCidrMap(string $map, array $assignments, ?string $class = null): array
    {
        $class = $class ?? $this->options['client_networks_map_class'];
        $parsed = self::parseCidrMap($map);
        $errors = $parsed['errors'];
        $warnings = [];
        foreach ($parsed['warnings'] as $warning) {
            $warnings[] = "$warning by this check";
        }
        $entries = $parsed['entries'];
        $fromMap = $this->options['client_networks_map'] !== null;

        $problem = function (array $network) use ($entries, $class): ?string {
            return self::firstMatch($entries, $network, $class)['problem'];
        };

        foreach ($this->options['client_networks'] as $network) {
            $reason = $problem(self::network($network));
            if ($reason !== null) {
                $label = $fromMap ? "policy network $network" : "client_networks entry $network";
                $errors[] = "$label: $reason - clients there are not (all) sent to the policy";
            }
        }
        $seen = [];
        foreach ($assignments as $assignment) {
            $text = SmtpClientIpList::format(inet_ntop($assignment['network'][0]) . '/' . $assignment['network'][1]);
            if (isset($seen[$text . ' ' . $assignment['domain']])) {
                continue;
            }
            $seen[$text . ' ' . $assignment['domain']] = true;
            $reason = $problem($assignment['network']);
            if ($reason !== null) {
                $errors[] = "{$assignment['domain']}: $text $reason - its binding is not (fully) enforced (map incomplete, or a stale entry after a network change)";
            }
        }
        // lines with another action inside a policy network: before it they are
        // exceptions (Postfix does not ask the policy for those clients), after it
        // they are never used
        $inside = function (array $inner, array $outer): bool {
            return $outer['network'][1] < $inner['network'][1]
                && SmtpClientIpList::contains($outer['network'], (string)inet_ntop($inner['network'][0]));
        };
        foreach ($entries as $entry) {
            if ($entry['action'] === $class) {
                continue;
            }
            foreach ($entries as $policyEntry) {
                if ($policyEntry['action'] !== $class || !$inside($entry, $policyEntry)) {
                    continue;
                }
                if ($entry['line'] < $policyEntry['line']) {
                    $warnings[] = "line {$entry['line']}: {$entry['text']} {$entry['action']} comes before line {$policyEntry['line']} ({$policyEntry['text']})"
                        . ' - exception: these clients are not checked by the policy';
                } else {
                    $warnings[] = "line {$entry['line']}: {$entry['text']} {$entry['action']} is never used"
                        . " - line {$policyEntry['line']} ({$policyEntry['text']}) matches these clients first";
                }
                break;
            }
        }

        foreach ($entries as $entry) {
            if ($entry['action'] !== $class) {
                continue;
            }
            $covered = false;
            foreach ($this->clientNetworks as $network) {
                if ($network[1] <= $entry['network'][1] && SmtpClientIpList::contains($network, (string)inet_ntop($entry['network'][0]))) {
                    $covered = true;
                    break;
                }
            }
            if (!$covered) {
                $warnings[] = "line {$entry['line']}: {$entry['text']} is sent to the policy but not in client_networks"
                    . ' - unassigned clients there are not rejected';
            }
        }
        return ['errors' => $errors, 'warnings' => $warnings];
    }

    public static function log(int $priority, string $message): void
    {
        if (defined('PHPUNIT_TEST')) {
            return;
        }
        syslog($priority, $message);
    }
}
