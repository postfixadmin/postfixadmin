<?php

/**
 * Postfix policy decision for $CONF['domain_smtp_client_ips'].
 *
 * Binds hosts that forward mail without SMTP authentication (listed in
 * domain.smtp_client_ips) to the sender domain(s) they are listed for.
 * Used by scripts/postfixadmin-smtp-client-policy.php, see
 * DOCUMENTS/SMTP_CLIENT_IPS.md.
 *
 * Decision order (first match wins):
 *   1. SASL authenticated session            -> DUNNO
 *   2. client address not an IP              -> DUNNO
 *   3. client not listed for any domain:
 *        inside client_networks and
 *        unassigned_client_action = reject   -> REJECT
 *        otherwise                           -> DUNNO
 *   4. client listed, but only for inactive
 *      domains                               -> REJECT
 *   5. null sender (reject_null_sender)      -> REJECT
 *   6. sender domain not one of the client's
 *      active domains / their alias domains  -> REJECT
 *   7. require_existing_sender and sender is
 *      no active mailbox or alias            -> REJECT
 *   8.                                       -> DUNNO
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

    private const DEFAULTS = [
        'unassigned_client_action' => 'reject',
        'client_networks' => [],
        'require_existing_sender' => true,
        'reject_null_sender' => true,
        'allow_subdomains' => false,
        'cache_ttl' => 30,
        'database_user' => null,
        'database_password' => null,
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
        foreach ($this->options['client_networks'] as $network) {
            $this->clientNetworks[] = (array)SmtpClientIpList::parse($network);
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

        if ($o['unassigned_client_action'] === 'reject' && $o['client_networks'] === []) {
            throw new InvalidArgumentException("smtp_client_policy: unassigned_client_action 'reject' requires client_networks (the networks of the forwarding hosts)");
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
        if (SmtpClientIpList::packAddress($client) === null) {
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
                if ($pos !== false) {
                    $attrs[substr($line, 0, $pos)] = substr($line, $pos + 1);
                }
                continue;
            }

            if ($policy === null || $startupError !== null || $tooLong) {
                $action = self::TEMPFAIL;
            } else {
                $action = $policy->decide($attrs);
            }
            fwrite($out, "action=$action\n\n");
            fflush($out);
            $count++;
            $attrs = [];
            $tooLong = false;

            if ($policy !== null && $startupError === null && $policy->failed()) {
                self::log(LOG_NOTICE, 'exiting after a temporary failure, the next request starts a new process');
                break;
            }
        }
        return $count;
    }

    public static function log(int $priority, string $message): void
    {
        if (defined('PHPUNIT_TEST')) {
            return;
        }
        syslog($priority, $message);
    }
}
