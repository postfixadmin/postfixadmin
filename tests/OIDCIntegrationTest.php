<?php

use PHPUnit\Framework\TestCase;
use Firebase\JWT\JWT;

class MockOIDCHttpClient
{
    public array $responses = [];
    public array $requests = [];

    public function get(string $url, string $headers = ''): string|false
    {
        $this->requests[] = ['method' => 'GET', 'url' => $url];
        return array_shift($this->responses) ?? false;
    }

    public function post(string $url, array $params): string|false
    {
        $this->requests[] = ['method' => 'POST', 'url' => $url];
        return array_shift($this->responses) ?? false;
    }
}

class TestableOIDC extends OIDC
{
    private MockOIDCHttpClient $mockClient;

    public function setMockClient(MockOIDCHttpClient $client): void
    {
        $this->mockClient = $client;
    }

    protected function httpGet(string $url, string $headers = ''): string|false
    {
        return $this->mockClient->get($url, $headers);
    }

    protected function httpPost(string $url, array $params): string|false
    {
        return $this->mockClient->post($url, $params);
    }

    public function testableHandleCallback(string $code, string $state): array|false
    {
        return $this->handleCallback($code, $state);
    }
}

class OIDCIntegrationTest extends TestCase
{
    private $testIss = 'https://keycloak.example.com/realms/test';
    private $testClientId = 'test-client';
    private $testEmail = 'test@example.com';
    private $testSub = 'user-12345';
    private MockOIDCHttpClient $mockClient;
    private TestableOIDC $oidc;
    private string $privateKey = '';
    private array $jwks = [];

    public function setUp(): void
    {
        global $CONF;
        Config::write('oidc', [
            'client_id' => $this->testClientId,
            'client_secret' => 'test-secret',
            'issuer_url' => $this->testIss,
            'redirect_uri' => 'https://mailadmin.example.com/oidc_callback.php',
            'scopes' => 'openid email profile',
        ]);
        $CONF['oidc'] = Config::read('oidc');
        $CONF['additional_auth'] = ['oidc'];
        $CONF['oidc_mfa'] = 'none';
        $CONF['oidc_auto_provision'] = false;

        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $this->privateKey);
        $pubKey = openssl_pkey_get_details($res);
        $this->jwks = $this->createJwksFromPublicKey($pubKey['key']);

        $this->mockClient = new MockOIDCHttpClient();
        $this->oidc = new TestableOIDC();
        $this->oidc->setMockClient($this->mockClient);
    }

    private function createJwksFromPublicKey(string $publicKey): array
    {
        $keyDetails = openssl_pkey_get_details(openssl_pkey_get_public($publicKey));
        $modulus = rtrim(strtr(base64_encode($keyDetails['rsa']['n']), '+/', '-_'), '=');
        $exponent = rtrim(strtr(base64_encode($keyDetails['rsa']['e']), '+/', '-_'), '=');
        return [
            'keys' => [[
                'kty' => 'RSA',
                'kid' => 'test-key-1',
                'n' => $modulus,
                'e' => $exponent,
                'alg' => 'RS256',
                'use' => 'sig',
            ]]
        ];
    }

    private function generateJWT(array $claims): string
    {
        $defaultClaims = [
            'iss' => $this->testIss,
            'aud' => [$this->testClientId],
            'sub' => $this->testSub,
            'email' => $this->testEmail,
            'email_verified' => true,
            'exp' => time() + 3600,
            'iat' => time(),
            'nonce' => 'valid-nonce',
        ];
        return JWT::encode(array_merge($defaultClaims, $claims), $this->privateKey, 'RS256', 'test-key-1');
    }

    private function setupAllResponses(): void
    {
        $this->mockClient->responses[] = json_encode([
            'issuer' => $this->testIss,
            'authorization_endpoint' => $this->testIss . '/auth',
            'token_endpoint' => $this->testIss . '/token',
            'userinfo_endpoint' => $this->testIss . '/userinfo',
            'jwks_uri' => $this->testIss . '/certs',
        ]);
        $this->mockClient->responses[] = json_encode([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
            'id_token' => $this->generateJWT([]),
        ]);
        $this->mockClient->responses[] = json_encode($this->jwks);
        $this->mockClient->responses[] = json_encode([
            'sub' => $this->testSub,
            'email' => $this->testEmail,
            'name' => 'Test User',
        ]);
    }

    public function testHandleCallbackSuccess(): void
    {
        $this->setupAllResponses();
        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        $result = $this->oidc->testableHandleCallback('test-code', 'valid-state');

        $this->assertIsArray($result);
        $this->assertEquals($this->testEmail, $result['email']);
        $this->assertEquals($this->testSub, $result['sub']);
    }

    public function testHandleCallbackStateMismatch(): void
    {
        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        $this->assertFalse($this->oidc->testableHandleCallback('test-code', 'wrong-state'));
    }

    public function testHandleCallbackMissingState(): void
    {
        $_SESSION['oidc_nonce'] = 'valid-nonce';
        $this->assertFalse($this->oidc->testableHandleCallback('test-code', 'some-state'));
    }

    public function testHandleCallbackDiscoveryFailure(): void
    {
        $this->mockClient->responses[] = false;
        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        $this->assertFalse($this->oidc->testableHandleCallback('test-code', 'valid-state'));
    }

    public function testHandleCallbackTokenFailure(): void
    {
        $this->mockClient->responses[] = json_encode([
            'issuer' => $this->testIss,
            'authorization_endpoint' => $this->testIss . '/auth',
            'token_endpoint' => $this->testIss . '/token',
            'jwks_uri' => $this->testIss . '/certs',
        ]);
        $this->mockClient->responses[] = false;
        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        $this->assertFalse($this->oidc->testableHandleCallback('test-code', 'valid-state'));
    }

    public function testHandleCallbackJwksFailure(): void
    {
        $this->mockClient->responses[] = json_encode([
            'issuer' => $this->testIss,
            'authorization_endpoint' => $this->testIss . '/auth',
            'token_endpoint' => $this->testIss . '/token',
            'jwks_uri' => $this->testIss . '/certs',
        ]);
        $this->mockClient->responses[] = json_encode([
            'access_token' => 'test-access-token',
            'id_token' => $this->generateJWT([]),
        ]);
        $this->mockClient->responses[] = false;
        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        $this->assertFalse($this->oidc->testableHandleCallback('test-code', 'valid-state'));
    }

    public function testHandleCallbackIssuerMismatch(): void
    {
        $this->mockClient->responses[] = json_encode([
            'issuer' => $this->testIss,
            'authorization_endpoint' => $this->testIss . '/auth',
            'token_endpoint' => $this->testIss . '/token',
            'jwks_uri' => $this->testIss . '/certs',
        ]);
        $this->mockClient->responses[] = json_encode([
            'access_token' => 'test-access-token',
            'id_token' => $this->generateJWT(['iss' => 'https://evil.example.com']),
        ]);
        $this->mockClient->responses[] = json_encode($this->jwks);
        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        $this->assertFalse($this->oidc->testableHandleCallback('test-code', 'valid-state'));
    }

    public function testHandleCallbackAudienceMismatch(): void
    {
        $this->mockClient->responses[] = json_encode([
            'issuer' => $this->testIss,
            'authorization_endpoint' => $this->testIss . '/auth',
            'token_endpoint' => $this->testIss . '/token',
            'jwks_uri' => $this->testIss . '/certs',
        ]);
        $this->mockClient->responses[] = json_encode([
            'access_token' => 'test-access-token',
            'id_token' => $this->generateJWT(['aud' => ['wrong-client']]),
        ]);
        $this->mockClient->responses[] = json_encode($this->jwks);
        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        $this->assertFalse($this->oidc->testableHandleCallback('test-code', 'valid-state'));
    }

    public function testHandleCallbackNonceMismatch(): void
    {
        $this->mockClient->responses[] = json_encode([
            'issuer' => $this->testIss,
            'authorization_endpoint' => $this->testIss . '/auth',
            'token_endpoint' => $this->testIss . '/token',
            'jwks_uri' => $this->testIss . '/certs',
        ]);
        $this->mockClient->responses[] = json_encode([
            'access_token' => 'test-access-token',
            'id_token' => $this->generateJWT(['nonce' => 'wrong-nonce']),
        ]);
        $this->mockClient->responses[] = json_encode($this->jwks);
        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        $this->assertFalse($this->oidc->testableHandleCallback('test-code', 'valid-state'));
    }

    public function testHandleCallbackMissingNonce(): void
    {
        $this->mockClient->responses[] = json_encode([
            'issuer' => $this->testIss,
            'authorization_endpoint' => $this->testIss . '/auth',
            'token_endpoint' => $this->testIss . '/token',
            'jwks_uri' => $this->testIss . '/certs',
        ]);
        $this->mockClient->responses[] = json_encode([
            'access_token' => 'test-access-token',
            'id_token' => JWT::encode([
                'iss' => $this->testIss,
                'aud' => [$this->testClientId],
                'sub' => $this->testSub,
                'email' => $this->testEmail,
                'exp' => time() + 3600,
                'iat' => time(),
            ], $this->privateKey, 'RS256', 'test-key-1'),
        ]);
        $this->mockClient->responses[] = json_encode($this->jwks);
        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        $this->assertFalse($this->oidc->testableHandleCallback('test-code', 'valid-state'));
    }

    public function testHandleCallbackExpiredToken(): void
    {
        $this->mockClient->responses[] = json_encode([
            'issuer' => $this->testIss,
            'authorization_endpoint' => $this->testIss . '/auth',
            'token_endpoint' => $this->testIss . '/token',
            'jwks_uri' => $this->testIss . '/certs',
        ]);
        $this->mockClient->responses[] = json_encode([
            'access_token' => 'test-access-token',
            'id_token' => $this->generateJWT(['exp' => time() - 3600]),
        ]);
        $this->mockClient->responses[] = json_encode($this->jwks);
        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        $this->assertFalse($this->oidc->testableHandleCallback('test-code', 'valid-state'));
    }

    public function testHandleCallbackUserInfoSubMismatch(): void
    {
        $this->mockClient->responses[] = json_encode([
            'issuer' => $this->testIss,
            'authorization_endpoint' => $this->testIss . '/auth',
            'token_endpoint' => $this->testIss . '/token',
            'userinfo_endpoint' => $this->testIss . '/userinfo',
            'jwks_uri' => $this->testIss . '/certs',
        ]);
        $this->mockClient->responses[] = json_encode([
            'access_token' => 'test-access-token',
            'id_token' => $this->generateJWT([]),
        ]);
        $this->mockClient->responses[] = json_encode($this->jwks);
        $this->mockClient->responses[] = json_encode([
            'sub' => 'different-user-id',
            'email' => 'different@example.com',
        ]);
        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        $this->assertFalse($this->oidc->testableHandleCallback('test-code', 'valid-state'));
    }

    public function testHandleCallbackUserInfoSuccess(): void
    {
        $this->setupAllResponses();
        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        $result = $this->oidc->testableHandleCallback('test-code', 'valid-state');

        $this->assertIsArray($result);
        $this->assertEquals($this->testEmail, $result['email']);
        $this->assertEquals('Test User', $result['name']);
    }

    // Test: amr claim with otp detected
    public function testHandleCallbackAmrOtp(): void
    {
        $this->mockClient->responses[] = json_encode([
            'issuer' => $this->testIss,
            'authorization_endpoint' => $this->testIss . '/auth',
            'token_endpoint' => $this->testIss . '/token',
            'userinfo_endpoint' => $this->testIss . '/userinfo',
            'jwks_uri' => $this->testIss . '/certs',
        ]);
        $this->mockClient->responses[] = json_encode([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
            'id_token' => $this->generateJWT(['amr' => ['pwd', 'otp']]),
        ]);
        $this->mockClient->responses[] = json_encode($this->jwks);
        $this->mockClient->responses[] = json_encode([
            'sub' => $this->testSub,
            'email' => $this->testEmail,
        ]);

        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        $result = $this->oidc->testableHandleCallback('test-code', 'valid-state');
        $this->assertIsArray($result);
        $this->assertTrue(in_array('otp', $result['amr']));
    }

    // Test: amr claim with mfa detected
    public function testHandleCallbackAmrMfa(): void
    {
        $this->mockClient->responses[] = json_encode([
            'issuer' => $this->testIss,
            'authorization_endpoint' => $this->testIss . '/auth',
            'token_endpoint' => $this->testIss . '/token',
            'userinfo_endpoint' => $this->testIss . '/userinfo',
            'jwks_uri' => $this->testIss . '/certs',
        ]);
        $this->mockClient->responses[] = json_encode([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
            'id_token' => $this->generateJWT(['amr' => ['pwd', 'mfa']]),
        ]);
        $this->mockClient->responses[] = json_encode($this->jwks);
        $this->mockClient->responses[] = json_encode([
            'sub' => $this->testSub,
            'email' => $this->testEmail,
        ]);

        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        $result = $this->oidc->testableHandleCallback('test-code', 'valid-state');
        $this->assertIsArray($result);
        $this->assertTrue(in_array('mfa', $result['amr']));
    }

    // Test: amr claim with hwk detected
    public function testHandleCallbackAmrHwk(): void
    {
        $this->mockClient->responses[] = json_encode([
            'issuer' => $this->testIss,
            'authorization_endpoint' => $this->testIss . '/auth',
            'token_endpoint' => $this->testIss . '/token',
            'userinfo_endpoint' => $this->testIss . '/userinfo',
            'jwks_uri' => $this->testIss . '/certs',
        ]);
        $this->mockClient->responses[] = json_encode([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
            'id_token' => $this->generateJWT(['amr' => ['pwd', 'hwk']]),
        ]);
        $this->mockClient->responses[] = json_encode($this->jwks);
        $this->mockClient->responses[] = json_encode([
            'sub' => $this->testSub,
            'email' => $this->testEmail,
        ]);

        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        $result = $this->oidc->testableHandleCallback('test-code', 'valid-state');
        $this->assertIsArray($result);
        $this->assertTrue(in_array('hwk', $result['amr']));
    }

    // Test: amr claim with totp detected
    public function testHandleCallbackAmrTotp(): void
    {
        $this->mockClient->responses[] = json_encode([
            'issuer' => $this->testIss,
            'authorization_endpoint' => $this->testIss . '/auth',
            'token_endpoint' => $this->testIss . '/token',
            'userinfo_endpoint' => $this->testIss . '/userinfo',
            'jwks_uri' => $this->testIss . '/certs',
        ]);
        $this->mockClient->responses[] = json_encode([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
            'id_token' => $this->generateJWT(['amr' => ['pwd', 'totp']]),
        ]);
        $this->mockClient->responses[] = json_encode($this->jwks);
        $this->mockClient->responses[] = json_encode([
            'sub' => $this->testSub,
            'email' => $this->testEmail,
        ]);

        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        $result = $this->oidc->testableHandleCallback('test-code', 'valid-state');
        $this->assertIsArray($result);
        $this->assertTrue(in_array('totp', $result['amr']));
    }

    // Test: amr claim with fido detected
    public function testHandleCallbackAmrFido(): void
    {
        $this->mockClient->responses[] = json_encode([
            'issuer' => $this->testIss,
            'authorization_endpoint' => $this->testIss . '/auth',
            'token_endpoint' => $this->testIss . '/token',
            'userinfo_endpoint' => $this->testIss . '/userinfo',
            'jwks_uri' => $this->testIss . '/certs',
        ]);
        $this->mockClient->responses[] = json_encode([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
            'id_token' => $this->generateJWT(['amr' => ['pwd', 'fido']]),
        ]);
        $this->mockClient->responses[] = json_encode($this->jwks);
        $this->mockClient->responses[] = json_encode([
            'sub' => $this->testSub,
            'email' => $this->testEmail,
        ]);

        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        $result = $this->oidc->testableHandleCallback('test-code', 'valid-state');
        $this->assertIsArray($result);
        $this->assertTrue(in_array('fido', $result['amr']));
    }

    // Test: amr claim with sms detected
    public function testHandleCallbackAmrSms(): void
    {
        $this->mockClient->responses[] = json_encode([
            'issuer' => $this->testIss,
            'authorization_endpoint' => $this->testIss . '/auth',
            'token_endpoint' => $this->testIss . '/token',
            'userinfo_endpoint' => $this->testIss . '/userinfo',
            'jwks_uri' => $this->testIss . '/certs',
        ]);
        $this->mockClient->responses[] = json_encode([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
            'id_token' => $this->generateJWT(['amr' => ['pwd', 'sms']]),
        ]);
        $this->mockClient->responses[] = json_encode($this->jwks);
        $this->mockClient->responses[] = json_encode([
            'sub' => $this->testSub,
            'email' => $this->testEmail,
        ]);

        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        $result = $this->oidc->testableHandleCallback('test-code', 'valid-state');
        $this->assertIsArray($result);
        $this->assertTrue(in_array('sms', $result['amr']));
    }

    // Test: amr claim with pin detected
    public function testHandleCallbackAmrPin(): void
    {
        $this->mockClient->responses[] = json_encode([
            'issuer' => $this->testIss,
            'authorization_endpoint' => $this->testIss . '/auth',
            'token_endpoint' => $this->testIss . '/token',
            'userinfo_endpoint' => $this->testIss . '/userinfo',
            'jwks_uri' => $this->testIss . '/certs',
        ]);
        $this->mockClient->responses[] = json_encode([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
            'id_token' => $this->generateJWT(['amr' => ['pwd', 'pin']]),
        ]);
        $this->mockClient->responses[] = json_encode($this->jwks);
        $this->mockClient->responses[] = json_encode([
            'sub' => $this->testSub,
            'email' => $this->testEmail,
        ]);

        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        $result = $this->oidc->testableHandleCallback('test-code', 'valid-state');
        $this->assertIsArray($result);
        $this->assertTrue(in_array('pin', $result['amr']));
    }

    // Test: amr claim with face detected
    public function testHandleCallbackAmrFace(): void
    {
        $this->mockClient->responses[] = json_encode([
            'issuer' => $this->testIss,
            'authorization_endpoint' => $this->testIss . '/auth',
            'token_endpoint' => $this->testIss . '/token',
            'userinfo_endpoint' => $this->testIss . '/userinfo',
            'jwks_uri' => $this->testIss . '/certs',
        ]);
        $this->mockClient->responses[] = json_encode([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
            'id_token' => $this->generateJWT(['amr' => ['pwd', 'face']]),
        ]);
        $this->mockClient->responses[] = json_encode($this->jwks);
        $this->mockClient->responses[] = json_encode([
            'sub' => $this->testSub,
            'email' => $this->testEmail,
        ]);

        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        $result = $this->oidc->testableHandleCallback('test-code', 'valid-state');
        $this->assertIsArray($result);
        $this->assertTrue(in_array('face', $result['amr']));
    }

    // Test: amr claim with smart card detected
    public function testHandleCallbackAmrSc(): void
    {
        $this->mockClient->responses[] = json_encode([
            'issuer' => $this->testIss,
            'authorization_endpoint' => $this->testIss . '/auth',
            'token_endpoint' => $this->testIss . '/token',
            'userinfo_endpoint' => $this->testIss . '/userinfo',
            'jwks_uri' => $this->testIss . '/certs',
        ]);
        $this->mockClient->responses[] = json_encode([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
            'id_token' => $this->generateJWT(['amr' => ['pwd', 'sc']]),
        ]);
        $this->mockClient->responses[] = json_encode($this->jwks);
        $this->mockClient->responses[] = json_encode([
            'sub' => $this->testSub,
            'email' => $this->testEmail,
        ]);

        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        $result = $this->oidc->testableHandleCallback('test-code', 'valid-state');
        $this->assertIsArray($result);
        $this->assertTrue(in_array('sc', $result['amr']));
    }
}
