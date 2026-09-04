<?php

/**
 * Checks and stores the binary DNS status shown in the domain overview.
 *
 * Lookups are performed only by refresh(), never while rendering a list.
 */
class DomainDnsStatus
{
    private int $mode;

    public function __construct(private float $timeout = 1.0, ?int $mode = null)
    {
        $this->mode = $mode ?? self::configuredMode();
    }

    public static function configuredMode(): int
    {
        $configured = Config::read('domain_dns_status_check');
        if (!is_int($configured) && !is_string($configured)) {
            return 0;
        }
        $mode = (int)$configured;
        return in_array($mode, [0, 1, 2], true) ? $mode : 0;
    }

    /** @param string[] $domains
     *  @return array{active: int, inactive: int}
     */
    public function refresh(array $domains): array
    {
        $result = ['active' => 0, 'inactive' => 0];
        if ($this->mode === 0) {
            return $result;
        }
        foreach (array_unique($domains) as $domain) {
            if (!is_string($domain) || $domain === '' || $domain === 'ALL') {
                continue;
            }
            $active = $this->isActive($domain);
            db_update('domain', 'domain', $domain, [
                'dns_active' => $active,
                'dns_checked' => date('Y-m-d H:i:s'),
            ], []);
            $result[$active ? 'active' : 'inactive']++;
        }
        return $result;
    }

    public function isActive(string $domain): bool
    {
        if ($this->mode === 2) {
            return $this->hasUsableMx($domain);
        }
        if ($this->mode !== 1) {
            return false;
        }
        foreach ($this->nameservers($domain) as $nameserver) {
            foreach ($this->nameserverAddresses($nameserver) as $address) {
                if ($this->authoritativeServerResponds($domain, $address)) {
                    return true;
                }
            }
        }
        return false;
    }

    protected function hasUsableMx(string $domain): bool
    {
        foreach ($this->mxTargets($domain) as $target) {
            if ($this->nameserverAddresses($target) !== []) {
                return true;
            }
        }
        return false;
    }

    /** @return string[] */
    protected function mxTargets(string $domain): array
    {
        if (!function_exists('dns_get_record')) {
            return [];
        }
        $records = @dns_get_record(rtrim($domain, '.'), DNS_MX);
        if (!is_array($records)) {
            return [];
        }
        $targets = [];
        foreach ($records as $record) {
            if (isset($record['target']) && is_string($record['target'])) {
                $target = strtolower(rtrim($record['target'], '.'));
                if ($target !== '') {
                    $targets[] = $target;
                }
            }
        }
        return array_values(array_unique($targets));
    }

    /** @param string[] $domains */
    public static function countInactive(array $domains): int
    {
        if ($domains === []) {
            return 0;
        }
        $params = ['dns_active' => false];
        $where = db_in_clause('domain', $domains, $params);
        $table = table_by_key('domain');
        $row = db_query_one("SELECT count(*) AS inactive_count FROM $table WHERE dns_active = :dns_active AND $where", $params);
        return (int)($row['inactive_count'] ?? 0);
    }

    /** @return string[] */
    protected function nameservers(string $domain): array
    {
        if (!function_exists('dns_get_record')) {
            return [];
        }
        $records = @dns_get_record(rtrim($domain, '.'), DNS_NS);
        if (!is_array($records)) {
            return [];
        }
        $nameservers = [];
        foreach ($records as $record) {
            if (isset($record['target']) && is_string($record['target'])) {
                $nameservers[] = strtolower(rtrim($record['target'], '.'));
            }
        }
        return array_values(array_unique($nameservers));
    }

    /** @return string[] */
    protected function nameserverAddresses(string $nameserver): array
    {
        $records = @dns_get_record($nameserver, DNS_A | DNS_AAAA);
        if (!is_array($records)) {
            return [];
        }
        $addresses = [];
        foreach ($records as $record) {
            if (isset($record['ip']) && filter_var($record['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $addresses[] = $record['ip'];
            } elseif (isset($record['ipv6']) && filter_var($record['ipv6'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $addresses[] = $record['ipv6'];
            }
        }
        return array_values(array_unique($addresses));
    }

    protected function authoritativeServerResponds(string $domain, string $address): bool
    {
        $id = random_int(0, 65535);
        $query = pack('nnnnnn', $id, 0, 1, 0, 0, 0) . $this->encodeName($domain) . pack('nn', 6, 1);
        $endpoint = str_contains($address, ':') ? "udp://[$address]:53" : "udp://$address:53";
        $socket = @stream_socket_client($endpoint, $errno, $error, $this->timeout, STREAM_CLIENT_CONNECT);
        if ($socket === false) {
            return false;
        }
        $seconds = (int)$this->timeout;
        $microseconds = (int)round(($this->timeout - (float)$seconds) * 1000000.0);
        stream_set_timeout($socket, $seconds, $microseconds);
        $written = @fwrite($socket, $query);
        $response = $written === strlen($query) ? @fread($socket, 4096) : false;
        fclose($socket);
        if (!is_string($response) || strlen($response) < 12) {
            return false;
        }
        $header = unpack('nid/nflags', substr($response, 0, 4));
        if (!is_array($header)) {
            return false;
        }
        $flags = (int)$header['flags'];
        return (int)$header['id'] === $id
            && ($flags & 0x8000) !== 0
            && ($flags & 0x0400) !== 0
            && ($flags & 0x000f) === 0;
    }

    private function encodeName(string $domain): string
    {
        $encoded = '';
        foreach (explode('.', rtrim($domain, '.')) as $label) {
            $length = strlen($label);
            if ($length === 0 || $length > 63) {
                return "\0";
            }
            $encoded .= chr($length) . $label;
        }
        return $encoded . "\0";
    }
}
