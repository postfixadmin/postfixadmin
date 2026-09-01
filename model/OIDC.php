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

    public function __construct()
    {
        $CONF = Config::getInstance()->getAll();
        $this->clientId = $CONF['oidc']['client_id'] ?? '';
        $this->clientSecret = $CONF['oidc']['client_secret'] ?? '';
        $this->issuerUrl = rtrim($CONF['oidc']['issuer_url'] ?? '', '/');
        $this->redirectUri = $CONF['oidc']['redirect_uri'] ?? '';
        $this->scopes = $CONF['oidc']['scopes'] ?? 'openid email profile';
    }

    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret) && !empty($this->issuerUrl);
    }

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
        if (!isset($_SESSION['oidc_state']) || !hash_equals($_SESSION['oidc_state'], $state)) {
            error_log('OIDC: State mismatch');
            return false;
        }
        $nonce = $_SESSION['oidc_nonce'] ?? '';
        unset($_SESSION['oidc_state'], $_SESSION['oidc_nonce']);

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

        // Validate ID token using firebase/php-jwt
        $jwks = $this->getJwks();
        try {
            $claims = JWT::decode($tokens['id_token'], JWK::parseKeySet($jwks));
            $claims = json_decode(json_encode($claims), true);
        } catch (\Exception $e) {
            error_log('OIDC: ID token validation failed: ' . $e->getMessage());
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

    private function getJwks(): array
    {
        $url = $this->discovery['jwks_uri'] ?? ($this->issuerUrl . '/protocol/openid-connect/certs');
        $response = $this->httpGet($url);
        if ($response === false) {
            return [];
        }
        return json_decode($response, true);
    }

    private function getUserinfo(string $accessToken): array|false
    {
        $url = $this->discovery['userinfo_endpoint'];
        $response = $this->httpGet($url, 'Authorization: Bearer ' . $accessToken);
        if ($response === false) {
            return false;
        }
        return json_decode($response, true);
    }

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
