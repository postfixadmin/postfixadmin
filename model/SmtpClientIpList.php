<?php

/**
 * Parsing, validation and matching of the IP address / CIDR network lists
 * used by domain.smtp_client_ips and $CONF['smtp_client_policy']['client_networks'].
 *
 * The web interface (DomainHandler) and the Postfix policy service
 * (SmtpClientPolicy) both use this class, so stored values and the policy's
 * interpretation of them are always identical.
 *
 * Canonical form of an entry:
 *   - a host:    "10.0.2.10", "2001:db8::10"
 *   - a network: "10.0.2.0/24", "2001:db8::/64" (host bits must be zero)
 * IPv4-mapped IPv6 addresses (::ffff:10.0.2.10) are stored as IPv4.
 */
class SmtpClientIpList
{
    public const ERROR_INVALID = 'invalid';
    public const ERROR_HOST_BITS = 'host_bits';

    /**
     * @return string[]
     */
    public static function split(string $value): array
    {
        $parts = preg_split('/[\s,;]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
        return $parts === false ? [] : $parts;
    }

    /**
     * Return the canonical form of $entry, or null if it is not a valid
     * address or network. $error is set to one of the ERROR_* constants.
     */
    public static function normalize(string $entry, ?string &$error = null): ?string
    {
        $error = null;
        $parsed = self::parse($entry, $error);
        if ($parsed === null) {
            return null;
        }
        [$packed, $prefix] = $parsed;
        $max = strlen($packed) * 8;
        $address = (string)inet_ntop($packed);

        return $prefix === $max ? $address : $address . '/' . $prefix;
    }

    /**
     * Canonical, de-duplicated list, separated by ", ".
     * Invalid entries are dropped (validate with invalidEntries() first).
     */
    public static function format(string $value): string
    {
        $result = [];
        foreach (self::split($value) as $entry) {
            $normalized = self::normalize($entry);
            if ($normalized !== null && !in_array($normalized, $result, true)) {
                $result[] = $normalized;
            }
        }
        return implode(', ', $result);
    }

    /**
     * @return array<string,string> invalid entry => ERROR_* constant
     */
    public static function invalidEntries(string $value): array
    {
        $invalid = [];
        foreach (self::split($value) as $entry) {
            $error = null;
            if (self::normalize($entry, $error) === null) {
                $invalid[$entry] = (string)$error;
            }
        }
        return $invalid;
    }

    /**
     * Parse an entry into [packed network address, prefix length].
     * Hosts get the full prefix length (32 / 128).
     *
     * @return array{0:string,1:int}|null
     */
    public static function parse(string $entry, ?string &$error = null): ?array
    {
        $error = self::ERROR_INVALID;
        $entry = trim($entry);
        $prefix = null;
        $address = $entry;

        if (strpos($entry, '/') !== false) {
            [$address, $prefixText] = explode('/', $entry, 2);
            if (!preg_match('/^[0-9]{1,3}$/D', $prefixText)) {
                return null;
            }
            $prefix = (int)$prefixText;
        }

        $packed = self::packAddress($address);
        if ($packed === null) {
            return null;
        }

        if (strlen($packed) === 4 && strpos($address, ':') !== false) {
            // IPv4-mapped IPv6 address: the prefix refers to the IPv6 form
            if ($prefix !== null) {
                if ($prefix < 96) {
                    return null;
                }
                $prefix -= 96;
            }
        }

        $max = strlen($packed) * 8;
        if ($prefix === null) {
            $prefix = $max;
        }
        if ($prefix < 1 || $prefix > $max) {
            return null; // "/0" would match every client
        }
        if (self::applyPrefix($packed, $prefix) !== $packed) {
            $error = self::ERROR_HOST_BITS;
            return null;
        }

        $error = null;
        return [$packed, $prefix];
    }

    /**
     * Packed binary form of a single address (no prefix); IPv4-mapped IPv6
     * addresses are returned as 4-byte IPv4. Null if invalid.
     */
    public static function packAddress(string $address): ?string
    {
        if (strpos($address, ':') === false) {
            if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return null; // also rejects legacy notations inet_pton() may accept
            }
        } elseif (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return null;
        }

        $packed = @inet_pton($address);
        if ($packed === false) {
            return null;
        }
        if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
            $packed = substr($packed, 12);
        }
        return $packed;
    }

    /**
     * Does $address (single IP) lie within the parsed network?
     *
     * @param array{0:string,1:int} $network as returned by parse()
     */
    public static function contains(array $network, string $address): bool
    {
        $packed = self::packAddress($address);
        if ($packed === null || strlen($packed) !== strlen($network[0])) {
            return false;
        }
        return self::applyPrefix($packed, $network[1]) === $network[0];
    }

    private static function applyPrefix(string $packed, int $prefix): string
    {
        $result = '';
        $len = strlen($packed);
        for ($i = 0; $i < $len; $i++) {
            $bits = max(0, min(8, $prefix - $i * 8));
            $mask = $bits === 0 ? 0 : (0xff << (8 - $bits)) & 0xff;
            $result .= chr(ord($packed[$i]) & $mask);
        }
        return $result;
    }
}
