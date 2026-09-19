<?php

/**
 * DomainOidcHandler
 *
 * Handles per-domain OIDC configuration CRUD operations.
 * Each domain can have its own IdP configuration stored in the domain_oidc table.
 */

class DomainOidcHandler
{
    private string $domain;

    public function __construct(string $domain)
    {
        $this->domain = $domain;
    }

    /**
     * Get OIDC config for this domain
     * @return array|null Config array or null if not configured
     */
    public function get(): ?array
    {
        $table = table_by_key('domain_oidc');
        $config = db_query_one("SELECT * FROM $table WHERE domain = ?", [$this->domain]);
        if (!$config) {
            return null;
        }
        if (isset($config['client_secret'])) {
            $config['client_secret'] = base64_decode($config['client_secret']);
        }
        return $config;
    }

    /**
     * Check if domain has OIDC configured
     */
    public function exists(): bool
    {
        $config = $this->get();
        return $config !== null;
    }

    /**
     * Save OIDC config for this domain
     */
    public function save(array $config): bool
    {
        $table = table_by_key('domain_oidc');

        // Build update/insert fields
        $fields = [
            'issuer_url' => $config['issuer_url'],
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'scopes' => $config['scopes'] ?? 'openid email profile',
            'login_button_text' => $config['login_button_text'] ?? 'Login with SSO',
            'auto_provision' => $config['auto_provision'] ? 1 : 0,
            'mfa_policy' => $config['mfa_policy'] ?? null,
            'mfa_methods' => $config['mfa_methods'] ?? null,
            'mfa_blacklist' => $config['mfa_blacklist'] ?? null,
        ];

        if ($this->exists()) {
            // Update
            $set = [];
            $params = [];
            foreach ($fields as $key => $value) {
                $set[] = "$key = ?";
                $params[] = $value;
            }
            $params[] = $this->domain;
            $sql = "UPDATE $table SET " . implode(', ', $set) . " WHERE domain = ?";
            db_execute($sql, $params);
        } else {
            // Insert
            $fields['domain'] = $this->domain;
            $keys = array_keys($fields);
            $placeholders = array_fill(0, count($keys), '?');
            $sql = "INSERT INTO $table (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $placeholders) . ")";
            db_execute($sql, array_values($fields));
        }

        return true;
    }

    /**
     * Delete OIDC config for this domain
     */
    public function delete(): bool
    {
        $table = table_by_key('domain_oidc');
        db_execute("DELETE FROM $table WHERE domain = ?", [$this->domain]);
        return true;
    }

    /**
     * Get OIDC config by issuer URL (for callback routing)
     * @return array|null Config array or null if not found
     */
    public static function getByIssuer(string $issuerUrl): ?array
    {
        $table = table_by_key('domain_oidc');
        return db_query_one("SELECT * FROM $table WHERE issuer_url = ?", [$issuerUrl]);
    }

    /**
     * Get all domains with OIDC configured
     */
    public static function getAll(): array
    {
        $table = table_by_key('domain_oidc');
        return db_query_all("SELECT * FROM $table");
    }

    /**
     * Get effective MFA methods for this domain (falls back to global config)
     */
    public function getMfaMethods(): array
    {
        $config = $this->get();
        if ($config && !empty($config['mfa_methods'])) {
            return array_map('trim', explode(',', $config['mfa_methods']));
        }
        global $CONF;
        return $CONF['oidc_mfa_methods'] ?? [];
    }

    /**
     * Get effective MFA blacklist for this domain (falls back to global config)
     */
    public function getMfaBlacklist(): array
    {
        $config = $this->get();
        if ($config && !empty($config['mfa_blacklist'])) {
            return array_map('trim', explode(',', $config['mfa_blacklist']));
        }
        global $CONF;
        return $CONF['oidc_mfa_blacklist'] ?? [];
    }

    /**
     * Get effective MFA policy for this domain (falls back to global config)
     */
    public function getMfaPolicy(): string
    {
        $config = $this->get();
        if ($config && !empty($config['mfa_policy'])) {
            return $config['mfa_policy'];
        }
        global $CONF;
        return $CONF['oidc_mfa'] ?? 'none';
    }
}
