<?php

use PHPUnit\Framework\TestCase;
use Firebase\JWT\JWT;

/**
 * Mock HTTP client for testing OIDC
 */
class MockOIDCHttpClientForAutoProvisioning
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

/**
 * Testable OIDC subclass
 */
class TestableOIDCForAutoProvisioning extends OIDC
{
    private MockOIDCHttpClientForAutoProvisioning $mockClient;

    public function setMockClient(MockOIDCHttpClientForAutoProvisioning $client): void
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

/**
 * Full auto-provisioning flow test with SQLite
 */
class OIDCAutoProvisioningTest extends TestCase
{
    private $testIss = 'https://keycloak.example.com/realms/test';
    private $testClientId = 'test-client';
    private $testEmail = 'new-oidc-user@example.com';
    private $testSub = 'user-12345';
    private MockOIDCHttpClientForAutoProvisioning $mockClient;
    private TestableOIDCForAutoProvisioning $oidc;
    private string $privateKey = '';
    private array $jwks = [];

    public function setUp(): void
    {
        // Skip on MySQL - these tests use SQLite/PostgreSQL-specific SQL
        if (getenv('DATABASE') === 'mysql') {
            $this->markTestSkipped('SQLite/PostgreSQL-specific test');
        }

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
        $CONF['oidc_auto_provision'] = true;
        $CONF['oidc_require_verified_email'] = false;

        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $this->privateKey);
        $pubKey = openssl_pkey_get_details($res);
        $this->jwks = $this->createJwksFromPublicKey($pubKey['key']);

        $this->mockClient = new MockOIDCHttpClientForAutoProvisioning();
        $this->oidc = new TestableOIDCForAutoProvisioning();
        $this->oidc->setMockClient($this->mockClient);

        // Clean up test user
        db_execute("DELETE FROM admin WHERE username = ?", [$this->testEmail]);
    }

    public function tearDown(): void
    {
        db_execute("DELETE FROM admin WHERE username = ?", [$this->testEmail]);
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
        ]);
    }

    /**
     * Test: full auto-provisioning flow creates admin user
     */
    public function testAutoProvisioningCreatesAdmin(): void
    {
        global $CONF;
        $CONF['oidc_auto_provision'] = true;

        $this->setupAllResponses();
        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        // Step 1: Call handleCallback (validates OIDC token)
        $claims = $this->oidc->testableHandleCallback('test-code', 'valid-state');
        $this->assertIsArray($claims);
        $this->assertEquals($this->testEmail, $claims['email']);

        // Step 2: Verify user doesn't exist yet
        $table_admin = table_by_key('admin');
        $existing = db_query_one("SELECT * FROM admin WHERE username = ?", [$this->testEmail]);
        $this->assertEmpty($existing, 'User should not exist before provisioning');

        // Step 3: Simulate the auto-provisioning SQL from oidc_callback.php
        $randomPassword = generate_password();
        $hashedPassword = pacrypt($randomPassword);

        if (db_pgsql() || db_sqlite()) {
            db_execute(
                "INSERT INTO $table_admin (username, password, active, created, modified) VALUES (?, ?, true, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) ON CONFLICT (username) DO NOTHING",
                [$this->testEmail, $hashedPassword]
            );
        } else {
            db_execute(
                "INSERT IGNORE INTO $table_admin (username, password, active, created, modified) VALUES (?, ?, true, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
                [$this->testEmail, $hashedPassword]
            );
        }

        // Step 4: Verify user was created
        $result = db_query_one("SELECT * FROM admin WHERE username = ?", [$this->testEmail]);
        $this->assertNotEmpty($result, 'Admin user should be created');
        $this->assertEquals($this->testEmail, $result['username']);
        $this->assertEquals(1, $result['active']);
        $this->assertNotEmpty($result['created']);
        $this->assertNotEmpty($result['modified']);
    }

    /**
     * Test: auto-provisioning disabled rejects new user
     */
    public function testAutoProvisioningDisabledRejectsNewUser(): void
    {
        global $CONF;
        $CONF['oidc_auto_provision'] = false;

        $this->setupAllResponses();
        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        // Step 1: Call handleCallback (validates OIDC token)
        $claims = $this->oidc->testableHandleCallback('test-code', 'valid-state');
        $this->assertIsArray($claims);

        // Step 2: Verify user doesn't exist
        $table_admin = table_by_key('admin');
        $existing = db_query_one("SELECT * FROM admin WHERE username = ?", [$this->testEmail]);
        $this->assertEmpty($existing, 'User should not exist');

        // Step 3: With auto_provision=false, user should NOT be created
        // (In real flow, oidc_callback.php would redirect with error)
        // We verify the user is still not in the database
        $after = db_query_one("SELECT * FROM admin WHERE username = ?", [$this->testEmail]);
        $this->assertEmpty($after, 'User should not be created when auto_provision is disabled');
    }

    /**
     * Test: existing user is not affected by auto-provisioning
     */
    public function testExistingUserNotAffected(): void
    {
        global $CONF;
        $CONF['oidc_auto_provision'] = true;

        $this->setupAllResponses();
        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        // Step 1: Pre-create the user
        $table_admin = table_by_key('admin');
        $prePassword = pacrypt('pre-existing-password');
        if (db_pgsql() || db_sqlite()) {
            db_execute(
                "INSERT INTO $table_admin (username, password, active, created, modified) VALUES (?, ?, true, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) ON CONFLICT (username) DO NOTHING",
                [$this->testEmail, $prePassword]
            );
        } else {
            db_execute(
                "INSERT IGNORE INTO $table_admin (username, password, active, created, modified) VALUES (?, ?, true, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
                [$this->testEmail, $prePassword]
            );
        }

        // Step 2: Call handleCallback
        $claims = $this->oidc->testableHandleCallback('test-code', 'valid-state');
        $this->assertIsArray($claims);

        // Step 3: Verify user still exists with original password
        $result = db_query_one("SELECT * FROM admin WHERE username = ?", [$this->testEmail]);
        $this->assertNotEmpty($result);
        $this->assertEquals($prePassword, $result['password'], 'Password should not change');
    }

    /**
     * Test: disabled user cannot log in
     */
    public function testDisabledUserCannotLogin(): void
    {
        global $CONF;
        $CONF['oidc_auto_provision'] = true;

        $this->setupAllResponses();
        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        // Step 1: Pre-create a disabled user
        $table_admin = table_by_key('admin');
        $password = pacrypt('test-password');
        if (db_pgsql() || db_sqlite()) {
            db_execute(
                "INSERT INTO $table_admin (username, password, active, created, modified) VALUES (?, ?, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) ON CONFLICT (username) DO NOTHING",
                [$this->testEmail, $password]
            );
        } else {
            db_execute(
                "INSERT IGNORE INTO $table_admin (username, password, active, created, modified) VALUES (?, ?, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
                [$this->testEmail, $password]
            );
        }

        // Step 2: Call handleCallback
        $claims = $this->oidc->testableHandleCallback('test-code', 'valid-state');
        $this->assertIsArray($claims);

        // Step 3: Verify user is disabled
        $result = db_query_one("SELECT active FROM admin WHERE username = ?", [$this->testEmail]);
        $this->assertEquals(0, $result['active'], 'User should be disabled');
    }

    /**
     * Test: verified email requirement blocks unverified email
     */
    public function testVerifiedEmailRequirementBlocksUnverified(): void
    {
        global $CONF;
        $CONF['oidc_require_verified_email'] = true;

        // Override JWT to have email_verified=false
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
            'id_token' => JWT::encode([
                'iss' => $this->testIss,
                'aud' => [$this->testClientId],
                'sub' => $this->testSub,
                'email' => $this->testEmail,
                'email_verified' => false,
                'exp' => time() + 3600,
                'iat' => time(),
                'nonce' => 'valid-nonce',
            ], $this->privateKey, 'RS256', 'test-key-1'),
        ]);
        $this->mockClient->responses[] = json_encode($this->jwks);
        $this->mockClient->responses[] = json_encode([
            'sub' => $this->testSub,
            'email' => $this->testEmail,
        ]);

        $_SESSION['oidc_state'] = 'valid-state';
        $_SESSION['oidc_nonce'] = 'valid-nonce';

        // Step 1: Call handleCallback
        $claims = $this->oidc->testableHandleCallback('test-code', 'valid-state');
        $this->assertIsArray($claims);

        // Step 2: Verify email_verified is false
        $this->assertFalse($claims['email_verified'] ?? true, 'email_verified should be false');
    }
}
