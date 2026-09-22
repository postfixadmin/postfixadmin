<?php

/**
 * Class OIDC
 *
 * Handles OpenID Connect authentication flow for PostfixAdmin.
 * Uses firebase/php-jwt for JWT validation.
 */

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

class OIDC
{
    private string $clientId;
    private string $clientSecret;
    private string $issuerUrl;
    private string $redirectUri;
    private string $scopes;
    private array $discovery = [];

    public function __construct(?array $config = null)
    {
        if ($config === null) {
            $CONF = Config::getInstance()->getAll();
            $config = $CONF['oidc'] ?? [];
        }
        $this->clientId = $config['client_id'] ?? '';
        $this->clientSecret = $config['client_secret'] ?? '';
        $this->issuerUrl = rtrim($config['issuer_url'] ?? '', '/');
        $this->redirectUri = $config['redirect_uri'] ?? '';
        $this->scopes = $config['scopes'] ?? 'openid email profile';
    }

    private function logSecurityEvent(string $message): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        error_log("OIDC SECURITY [{$ip}]: {$message}");
    }

    /**
     * Accepted MFA methods from amr claim (RFC 8176 + common extensions).
     * Any one of these in the amr array indicates MFA was performed.
     */
    private const MFA_METHODS = [
        'mfa', 'otp', 'totp', 'hotp', 'hwk',
        'fpt', 'face', 'retina', 'vbm', 'wia',
        'sc', 'pin', 'sms', 'email', 'user',
        'fido', 'nok', 'kba', 'mcf',
    ];

    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret) && !empty($this->issuerUrl) && !empty($this->redirectUri);
    }

    public function discover(): bool
    {
        $url = $this->issuerUrl . '/.well-known/openid-configuration';
        $response = $this->httpGet($url);
        if ($response === false) {
            return false;
        }
        $this->discovery = json_decode($response, true);
        if (!is_array($this->discovery) || !isset($this->discovery['authorization_endpoint'])) {
            return false;
        }
        // Validate discovery document issuer matches configured issuer
        if (!isset($this->discovery['issuer']) || $this->discovery['issuer'] !== $this->issuerUrl) {
            $this->logSecurityEvent('Discovery document issuer mismatch - possible MITM attack');
            return false;
        }
        return true;
    }

    public function authorize(): void
    {
        if (empty($this->discovery)) {
            if (!$this->discover()) {
                die('OIDC discovery failed - could not reach provider');
            }
        }

        $state = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(32));

        $_SESSION['oidc_state'] = $state;
        $_SESSION['oidc_nonce'] = $nonce;

        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'scope' => $this->scopes,
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
            'nonce' => $nonce,
        ];

        $url = $this->discovery['authorization_endpoint'] . '?' . http_build_query($params);
        header('Location: ' . $url);
        exit;
    }

    public function handleCallback(string $code, string $state): array|false
    {
        // Validate state
        if (!isset($_SESSION['oidc_state'])) {
            $this->logSecurityEvent('No state in session - possible CSRF attack');
            return false;
        }
        if (!hash_equals($_SESSION['oidc_state'], $state)) {
            $this->logSecurityEvent('State mismatch - possible session fixation attack');
            return false;
        }
        $nonce = $_SESSION['oidc_nonce'] ?? '';
        unset($_SESSION['oidc_state'], $_SESSION['oidc_nonce']);

        // Exchange code for tokens
        if (empty($this->discovery)) {
            $this->discover();
        }

        if (empty($this->discovery['token_endpoint'])) {
            return false;
        }

        $tokenResponse = $this->httpPost($this->discovery['token_endpoint'], [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        if ($tokenResponse === false) {
            return false;
        }

        $tokens = json_decode($tokenResponse, true);
        if (!is_array($tokens) || !isset($tokens['id_token'])) {
            return false;
        }

        // Validate ID token using firebase/php-jwt
        $jwks = $this->getJwks();
        if (empty($jwks)) {
            return false;
        }

        try {
            $decoded = JWT::decode($tokens['id_token'], JWK::parseKeySet($jwks));
            /** @var array<string, mixed> $claims */
            $claims = json_decode(json_encode($decoded), true);
            if (!is_array($claims)) {
                error_log('OIDC: ID token claims are not an array');
                return false;
            }
        } catch (\Exception $e) {
            $this->logSecurityEvent('ID token validation failed: ' . $e->getMessage());
            return false;
        }

        // Verify nonce (must be present and match)
        if (empty($nonce) || !isset($claims['nonce']) || !hash_equals($nonce, $claims['nonce'])) {
            $this->logSecurityEvent('Nonce mismatch - possible replay attack');
            return false;
        }

        // Verify issuer matches configured issuer
        if (empty($claims['iss']) || $claims['iss'] !== $this->issuerUrl) {
            $this->logSecurityEvent('Issuer mismatch - possible token injection attack');
            return false;
        }

        // Verify audience matches client ID (aud can be string or array per OIDC spec)
        $aud = $claims['aud'] ?? '';
        if (is_array($aud)) {
            $audMatch = in_array($this->clientId, $aud);
        } else {
            $audMatch = ($aud === $this->clientId);
        }
        if (!$audMatch) {
            $this->logSecurityEvent('Audience mismatch - token not intended for this client');
            return false;
        }

        // Get userinfo if endpoint available
        if (isset($tokens['access_token']) && isset($this->discovery['userinfo_endpoint'])) {
            $userinfo = $this->getUserinfo($tokens['access_token']);
            if ($userinfo !== false) {
                // Verify UserInfo sub matches ID token sub before merging claims
                if (!isset($userinfo['sub']) || $userinfo['sub'] !== $claims['sub']) {
                    $this->logSecurityEvent('UserInfo sub mismatch - possible token substitution attack');
                    return false;
                }
                $claims = array_merge($claims, $userinfo);
            }
        }

        return $claims;
    }

    protected function getJwks(): array
    {
        $url = $this->discovery['jwks_uri'] ?? ($this->issuerUrl . '/protocol/openid-connect/certs');
        $response = $this->httpGet($url);
        if ($response === false) {
            return [];
        }
        return json_decode($response, true);
    }

    protected function getUserinfo(string $accessToken): array|false
    {
        $url = $this->discovery['userinfo_endpoint'];
        $response = $this->httpGet($url, 'Authorization: Bearer ' . $accessToken);
        if ($response === false) {
            return false;
        }
        return json_decode($response, true);
    }

    protected function httpGet(string $url, string $headers = ''): string|false
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [$headers]);
        }
        /** @var string|false $response */
        $response = curl_exec($ch) ?: false;
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) {
            return false;
        }
        return $response;
    }

    protected function httpPost(string $url, array $params): string|false
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        /** @var string|false $response */
        $response = curl_exec($ch) ?: false;
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) {
            return false;
        }
        return $response;
    }
}
