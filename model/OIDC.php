<?php

/**
 * Class OIDC
 *
 * Handles OpenID Connect authentication flow for PostfixAdmin.
 * Supports authorization code flow with PKCE.
 */

class OIDC
{
    private string $clientId;
    private string $clientSecret;
    private string $issuerUrl;
    private string $redirectUri;
    private string $scopes;
    private array $discovery = [];
    private array $jwks = [];

    public function __construct()
    {
        $CONF = Config::getInstance()->getAll();
        $this->clientId = $CONF['oidc']['client_id'] ?? '';
        $this->clientSecret = $CONF['oidc']['client_secret'] ?? '';
        $this->issuerUrl = rtrim($CONF['oidc']['issuer_url'] ?? '', '/');
        $this->redirectUri = $CONF['oidc']['redirect_uri'] ?? '';
        $this->scopes = $CONF['oidc']['scopes'] ?? 'openid email profile';
    }

    /**
     * Check if OIDC is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret) && !empty($this->issuerUrl);
    }

    /**
     * Fetch OIDC discovery document
     */
    public function discover(): bool
    {
        $url = $this->issuerUrl . '/.well-known/openid-configuration';
        $response = $this->httpGet($url);
        if ($response === false) {
            return false;
        }
        $this->discovery = json_decode($response, true);
        return is_array($this->discovery) && isset($this->discovery['authorization_endpoint']);
    }

    /**
     * Build authorization URL and redirect to IdP
     */
    public function authorize(): void
    {
        if (empty($this->discovery)) {
            $this->discover();
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

    /**
     * Handle callback from IdP
     * @return array|false User info on success, false on failure
     */
    public function handleCallback(string $code, string $state): array|false
    {
        // Validate state
        if (!isset($_SESSION['oidc_state']) || !hash_equals($_SESSION['oidc_state'], $state)) {
            error_log('OIDC: State mismatch');
            return false;
        }
        $nonce = $_SESSION['oidc_nonce'] ?? '';
        unset($_SESSION['oidc_state'], $_SESSION['oidc_nonce']);

        // Exchange code for tokens
        if (empty($this->discovery)) {
            $this->discover();
        }

        $tokenResponse = $this->httpPost($this->discovery['token_endpoint'], [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        if ($tokenResponse === false) {
            error_log('OIDC: Token exchange failed');
            return false;
        }

        $tokens = json_decode($tokenResponse, true);
        if (!is_array($tokens) || !isset($tokens['id_token'])) {
            error_log('OIDC: No id_token in response');
            return false;
        }

        // Validate ID token
        $claims = $this->validateIdToken($tokens['id_token']);
        if ($claims === false) {
            error_log('OIDC: ID token validation failed');
            return false;
        }

        // Verify nonce
        if (!empty($nonce) && isset($claims['nonce']) && !hash_equals($nonce, $claims['nonce'])) {
            error_log('OIDC: Nonce mismatch');
            return false;
        }

        // Get userinfo if endpoint available
        if (isset($tokens['access_token']) && isset($this->discovery['userinfo_endpoint'])) {
            $userinfo = $this->getUserinfo($tokens['access_token']);
            if ($userinfo !== false) {
                $claims = array_merge($claims, $userinfo);
            }
        }

        return $claims;
    }

    /**
     * Validate ID token (signature, issuer, audience, expiration)
     */
    private function validateIdToken(string $idToken): array|false
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            return false;
        }

        $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        if (!is_array($payload)) {
            return false;
        }

        // Validate issuer
        if (isset($payload['iss']) && $payload['iss'] !== $this->issuerUrl) {
            error_log('OIDC: Issuer mismatch');
            return false;
        }

        // Validate audience
        if (isset($payload['aud'])) {
            $aud = is_array($payload['aud']) ? $payload['aud'] : [$payload['aud']];
            if (!in_array($this->clientId, $aud)) {
                error_log('OIDC: Audience mismatch');
                return false;
            }
        }

        // Validate expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            error_log('OIDC: Token expired');
            return false;
        }

        // Validate signature
        if (!$this->validateSignature($idToken, $header)) {
            error_log('OIDC: Signature validation failed');
            return false;
        }

        return $payload;
    }

    /**
     * Validate JWT signature using JWKS
     */
    private function validateSignature(string $idToken, array $header): bool
    {
        if (empty($header['kid'])) {
            return false;
        }

        $jwks = $this->getJwks();
        $key = null;
        foreach ($jwks['keys'] ?? [] as $k) {
            if ($k['kid'] === $header['kid']) {
                $key = $k;
                break;
            }
        }

        if ($key === null) {
            return false;
        }

        // For RS256, verify using public key
        if (($key['kty'] ?? '') === 'RSA' && isset($key['n']) && isset($key['e'])) {
            return $this->verifyRsaSignature($idToken, $key);
        }

        return false;
    }

    /**
     * Verify RSA signature
     */
    private function verifyRsaSignature(string $idToken, array $key): bool
    {
        $parts = explode('.', $idToken);
        $signature = base64_decode(strtr($parts[2], '-_', '+/'));
        $data = $parts[0] . '.' . $parts[1];

        // Build PEM public key from n and e
        $modulus = base64_decode(strtr($key['n'], '-_', '+/'));
        $exponent = base64_decode(strtr($key['e'], '-_', '+/'));

        $pem = $this->rsaPublicKeyToPem($modulus, $exponent);
        if ($pem === false) {
            return false;
        }

        $pubKey = openssl_pkey_get_public($pem);
        if ($pubKey === false) {
            return false;
        }

        $result = openssl_verify($data, $signature, $pubKey, 'sha256');
        return $result === 1;
    }

    /**
     * Convert RSA public key components to PEM format
     */
    private function rsaPublicKeyToPem(string $modulus, string $exponent): string|false
    {
        // Build DER-encoded RSA public key
        $modulus = "\x00" . $modulus; // Add leading zero for positive integer
        $exponent = "\x00" . $exponent;

        $modulusLen = strlen($modulus);
        $exponentLen = strlen($exponent);

        // DER encode
        $der = "\x30" . chr($modulusLen + $exponentLen + 4)
             . "\x02" . chr($modulusLen) . $modulus
             . "\x02" . chr($exponentLen) . $exponent;

        // Wrap in BIT STRING
        $der = "\x30" . chr(strlen($der) + 3) . "\x03" . chr(strlen($der) + 1) . "\x00" . $der;

        // Wrap in SEQUENCE with algorithm identifier
        $rsaOid = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
        $der = "\x30" . chr(strlen($rsaOid) + strlen($der) + 2) . $rsaOid . "\x03" . chr(strlen($der) + 1) . "\x00" . $der;

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----";
    }

    /**
     * Fetch JWKS from IdP
     */
    private function getJwks(): array
    {
        if (!empty($this->jwks)) {
            return $this->jwks;
        }

        if (empty($this->discovery)) {
            $this->discover();
        }

        $url = $this->discovery['jwks_uri'] ?? ($this->issuerUrl . '/protocol/openid-connect/certs');
        $response = $this->httpGet($url);
        if ($response === false) {
            return [];
        }

        $this->jwks = json_decode($response, true);
        return is_array($this->jwks) ? $this->jwks : [];
    }

    /**
     * Get userinfo from IdP
     */
    private function getUserinfo(string $accessToken): array|false
    {
        $url = $this->discovery['userinfo_endpoint'];
        $response = $this->httpGet($url, 'Authorization: Bearer ' . $accessToken);
        if ($response === false) {
            return false;
        }
        return json_decode($response, true);
    }

    /**
     * HTTP GET request
     */
    private function httpGet(string $url, string $headers = ''): string|false
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [$headers]);
        }
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $httpCode === 200 ? $response : false;
    }

    /**
     * HTTP POST request
     */
    private function httpPost(string $url, array $params): string|false
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $httpCode === 200 ? $response : false;
    }
}
