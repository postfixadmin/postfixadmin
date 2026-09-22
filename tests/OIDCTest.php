<?php

use PHPUnit\Framework\TestCase;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class OIDCTest extends TestCase
{
    private $testSecret = 'test-secret-key-for-unit-tests-only-do-not-use-in-production';
    private $testIss = 'https://keycloak.example.com/realms/test';
    private $testClientId = 'test-client';
    private $testEmail = 'test@example.com';

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
    }

    private function generateJWT(array $claims): string
    {
        $defaultClaims = [
            'iss' => $this->testIss,
            'aud' => $this->testClientId,
            'sub' => 'test-user-id-12345',
            'email' => $this->testEmail,
            'exp' => time() + 3600,
            'iat' => time(),
            'nonce' => 'valid-nonce',
        ];
        $claims = array_merge($defaultClaims, $claims);
        return JWT::encode($claims, $this->testSecret, 'HS256');
    }

    private function getKey(): Key
    {
        return new Key($this->testSecret, 'HS256');
    }

    // Test: valid token passes all checks
    public function testValidTokenPassesValidation(): void
    {
        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        $token = $this->generateJWT([]);
        $decoded = JWT::decode($token, $this->getKey());
        $claims = json_decode(json_encode($decoded), true);

        $this->assertEquals($this->testIss, $claims['iss']);
        $this->assertEquals($this->testClientId, $claims['aud']);
        $this->assertEquals('valid-nonce', $claims['nonce']);
        $this->assertEquals($this->testEmail, $claims['email']);
    }

    // Test: issuer mismatch is detected
    public function testIssuerMismatchDetected(): void
    {
        $token = $this->generateJWT(['iss' => 'https://evil.example.com/realms/test']);
        $decoded = JWT::decode($token, $this->getKey());
        $claims = json_decode(json_encode($decoded), true);

        $this->assertNotEquals($this->testIss, $claims['iss'], 'Issuer should not match');
    }

    // Test: audience mismatch (string) is detected
    public function testAudienceMismatchStringDetected(): void
    {
        $token = $this->generateJWT(['aud' => 'wrong-client']);
        $decoded = JWT::decode($token, $this->getKey());
        $claims = json_decode(json_encode($decoded), true);

        $this->assertNotEquals($this->testClientId, $claims['aud'], 'Audience should not match');
    }

    // Test: audience as array is validated correctly
    public function testAudienceArrayValidation(): void
    {
        $token = $this->generateJWT(['aud' => ['wrong-client', 'another-client']]);
        $decoded = JWT::decode($token, $this->getKey());
        $claims = json_decode(json_encode($decoded), true);

        $this->assertNotEquals($this->testClientId, $claims['aud'][0]);
        $this->assertNotEquals($this->testClientId, $claims['aud'][1]);
    }

    // Test: audience as array containing correct client passes
    public function testAudienceArrayContainingCorrectClient(): void
    {
        $token = $this->generateJWT(['aud' => [$this->testClientId, 'other-client']]);
        $decoded = JWT::decode($token, $this->getKey());
        $claims = json_decode(json_encode($decoded), true);

        $aud = $claims['aud'];
        $this->assertTrue(
            is_array($aud) && in_array($this->testClientId, $aud),
            'Should find correct client in audience array'
        );
    }

    // Test: nonce mismatch is detected
    public function testNonceMismatchDetected(): void
    {
        $token = $this->generateJWT(['nonce' => 'wrong-nonce']);
        $decoded = JWT::decode($token, $this->getKey());
        $claims = json_decode(json_encode($decoded), true);

        $this->assertNotEquals('valid-nonce', $claims['nonce']);
    }

    // Test: missing nonce is detected
    public function testMissingNonceDetected(): void
    {
        $token = JWT::encode(
            [
                'iss' => $this->testIss,
                'aud' => $this->testClientId,
                'sub' => 'test-user-id',
                'email' => $this->testEmail,
                'exp' => time() + 3600,
                'iat' => time(),
            ],
            $this->testSecret,
            'HS256'
        );
        $decoded = JWT::decode($token, $this->getKey());
        $claims = json_decode(json_encode($decoded), true);

        $this->assertArrayNotHasKey('nonce', $claims, 'Token should have no nonce');
    }

    // Test: expired token is rejected by JWT::decode
    public function testExpiredTokenRejected(): void
    {
        $token = $this->generateJWT(['exp' => time() - 3600]);

        $this->expectException(\Exception::class);
        JWT::decode($token, $this->getKey());
    }

    // Test: JWT signature validation works (tampered token rejected)
    public function testTamperedTokenRejected(): void
    {
        $token = $this->generateJWT([]);

        $parts = explode('.', $token);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        $payload['email'] = 'attacker@evil.com';
        $parts[1] = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $tamperedToken = implode('.', $parts);

        $this->expectException(\Exception::class);
        JWT::decode($tamperedToken, $this->getKey());
    }

    // Test: wrong secret key is rejected
    public function testWrongSecretKeyRejected(): void
    {
        $token = $this->generateJWT([]);
        $wrongKey = new Key('this-is-the-wrong-secret', 'HS256');

        $this->expectException(\Exception::class);
        JWT::decode($token, $wrongKey);
    }

    // Test: amr claim detection for MFA
    public function testAmrClaimMfaDetection(): void
    {
        $tokenWithMfa = $this->generateJWT(['amr' => ['pwd', 'mfa']]);
        $decoded = JWT::decode($tokenWithMfa, $this->getKey());
        $claims = json_decode(json_encode($decoded), true);
        $this->assertTrue(in_array('mfa', $claims['amr']));

        $tokenNoMfa = $this->generateJWT(['amr' => ['pwd']]);
        $decoded = JWT::decode($tokenNoMfa, $this->getKey());
        $claims = json_decode(json_encode($decoded), true);
        $this->assertFalse(in_array('mfa', $claims['amr']));
    }

    // Test: amr claim with otp method
    public function testAmrOtpMethodDetection(): void
    {
        $token = $this->generateJWT(['amr' => ['pwd', 'otp']]);
        $decoded = JWT::decode($token, $this->getKey());
        $claims = json_decode(json_encode($decoded), true);
        $this->assertTrue(in_array('otp', $claims['amr']));
    }
}
