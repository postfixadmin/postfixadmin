<?php

use PHPUnit\Framework\TestCase;

/**
 * Test per-domain OIDC fields via DomainHandler (stored in domain table).
 */
class DomainOidcTest extends TestCase
{
    private $testDomain = 'test-oidc-domain.com';

    public function setUp(): void
    {
        // Skip on MySQL - uses SQLite/PostgreSQL-specific SQL
        if (getenv('DATABASE') === 'mysql') {
            $this->markTestSkipped('SQLite/PostgreSQL-specific test');
        }

        global $CONF;
        $CONF['oidc_mfa'] = 'none';
        $CONF['oidc_mfa_methods'] = ['mfa', 'otp', 'hwk'];
        $CONF['oidc_mfa_blacklist'] = [];

        // Clean up
        db_execute("DELETE FROM domain_admins WHERE domain = ?", [$this->testDomain]);
        db_execute("DELETE FROM domain WHERE domain = ?", [$this->testDomain]);
    }

    public function tearDown(): void
    {
        db_execute("DELETE FROM domain_admins WHERE domain = ?", [$this->testDomain]);
        db_execute("DELETE FROM domain WHERE domain = ?", [$this->testDomain]);
    }

    private function createDomain(): void
    {
        db_execute("INSERT INTO domain (domain, description, transport) VALUES (?, ?, ?)", [$this->testDomain, 'Test', '']);
    }

    public function testSaveCreatesOidcConfig(): void
    {
        $this->createDomain();

        $handler = new DomainHandler();
        $handler->init($this->testDomain);
        $handler->set([
            'oidc_enabled' => 1,
            'oidc_issuer_url' => 'https://keycloak.example.com/realms/test',
            'oidc_client_id' => 'test-client',
            'oidc_client_secret' => 'test-secret',
        ]);
        $this->assertTrue($handler->save());

        // Verify by direct DB query
        $table = table_by_key('domain');
        $row = db_query_one("SELECT oidc_issuer_url, oidc_client_id, oidc_client_secret FROM $table WHERE domain = ?", [$this->testDomain]);
        $this->assertEquals('https://keycloak.example.com/realms/test', $row['oidc_issuer_url']);
        $this->assertEquals('test-client', $row['oidc_client_id']);
        // b64p stores base64_encode
        $this->assertEquals('test-secret', base64_decode($row['oidc_client_secret']));
    }

    public function testSaveUpdatesExistingConfig(): void
    {
        $this->createDomain();

        $handler = new DomainHandler();
        $handler->init($this->testDomain);
        $handler->set([
            'oidc_enabled' => 1,
            'oidc_issuer_url' => 'https://old.example.com',
            'oidc_client_id' => 'old-client',
            'oidc_client_secret' => 'old-secret',
        ]);
        $handler->save();

        $handler->init($this->testDomain);
        $handler->set([
            'oidc_enabled' => 1,
            'oidc_issuer_url' => 'https://new.example.com',
            'oidc_client_id' => 'new-client',
            'oidc_client_secret' => 'new-secret',
        ]);
        $handler->save();

        $table = table_by_key('domain');
        $row = db_query_one("SELECT oidc_issuer_url, oidc_client_id, oidc_client_secret FROM $table WHERE domain = ?", [$this->testDomain]);
        $this->assertEquals('https://new.example.com', $row['oidc_issuer_url']);
        $this->assertEquals('new-client', $row['oidc_client_id']);
        $this->assertEquals('new-secret', base64_decode($row['oidc_client_secret']));
    }

    public function testOidcEnabledDerivedFromIssuerUrl(): void
    {
        $this->createDomain();

        $handler = new DomainHandler();
        $handler->init($this->testDomain);
        $handler->set([
            'oidc_enabled' => 1,
            'oidc_issuer_url' => 'https://keycloak.example.com',
            'oidc_client_id' => 'client',
            'oidc_client_secret' => 'secret',
        ]);
        $handler->save();

        // Reload and check oidc_enabled is derived
        $handler2 = new DomainHandler();
        $handler2->init($this->testDomain);
        $handler2->view();
        $result = $handler2->result();
        $this->assertEquals(1, $result['oidc_enabled']);
    }

    public function testOidcEnabledFalseWhenNoIssuer(): void
    {
        $this->createDomain();

        $handler = new DomainHandler();
        $handler->init($this->testDomain);
        $handler->view();
        $result = $handler->result();
        $this->assertEquals(0, $result['oidc_enabled']);
    }

    public function testDeleteOidcConfigByUnchecking(): void
    {
        $this->createDomain();

        // First: configure OIDC
        $handler = new DomainHandler();
        $handler->init($this->testDomain);
        $handler->set([
            'oidc_enabled' => 1,
            'oidc_issuer_url' => 'https://keycloak.example.com',
            'oidc_client_id' => 'client',
            'oidc_client_secret' => 'secret',
        ]);
        $handler->save();

        // Then: disable by setting oidc_enabled = 0
        $handler2 = new DomainHandler();
        $handler2->init($this->testDomain);
        $handler2->set([
            'oidc_enabled' => 0,
            'oidc_issuer_url' => '',
            'oidc_client_id' => '',
            'oidc_client_secret' => '',
        ]);
        $handler2->save();

        $table = table_by_key('domain');
        $row = db_query_one("SELECT oidc_issuer_url FROM $table WHERE domain = ?", [$this->testDomain]);
        $this->assertTrue(empty($row['oidc_issuer_url']));
    }

    public function testGetMfaMethodsFallsBackToGlobal(): void
    {
        global $CONF;
        $CONF['oidc_mfa_methods'] = ['mfa', 'otp', 'hwk'];

        $this->createDomain();
        $handler = new DomainHandler();
        $handler->init($this->testDomain);
        $handler->view();
        $this->assertEquals(['mfa', 'otp', 'hwk'], $handler->getMfaMethods());
    }

    public function testGetMfaMethodsPerDomain(): void
    {
        $this->createDomain();

        $handler = new DomainHandler();
        $handler->init($this->testDomain);
        $handler->set([
            'oidc_enabled' => 1,
            'oidc_issuer_url' => 'https://keycloak.example.com',
            'oidc_client_id' => 'client',
            'oidc_client_secret' => 'secret',
            'oidc_mfa_methods' => 'mfa,fido,face',
        ]);
        $handler->save();

        $handler2 = new DomainHandler();
        $handler2->init($this->testDomain);
        $handler2->view();
        $this->assertEquals(['mfa', 'fido', 'face'], $handler2->getMfaMethods());
    }

    public function testGetMfaPolicyFallsBackToGlobal(): void
    {
        global $CONF;
        $CONF['oidc_mfa'] = 'mfa_or_totp';

        $this->createDomain();
        $handler = new DomainHandler();
        $handler->init($this->testDomain);
        $handler->view();
        $this->assertEquals('mfa_or_totp', $handler->getMfaPolicy());
    }

    public function testGetMfaPolicyPerDomain(): void
    {
        $this->createDomain();

        $handler = new DomainHandler();
        $handler->init($this->testDomain);
        $handler->set([
            'oidc_enabled' => 1,
            'oidc_issuer_url' => 'https://keycloak.example.com',
            'oidc_client_id' => 'client',
            'oidc_client_secret' => 'secret',
            'oidc_mfa_policy' => 'idp_mfa',
        ]);
        $handler->save();

        $handler2 = new DomainHandler();
        $handler2->init($this->testDomain);
        $handler2->view();
        $this->assertEquals('idp_mfa', $handler2->getMfaPolicy());
    }

    public function testGetMfaBlacklistPerDomain(): void
    {
        $this->createDomain();

        $handler = new DomainHandler();
        $handler->init($this->testDomain);
        $handler->set([
            'oidc_enabled' => 1,
            'oidc_issuer_url' => 'https://keycloak.example.com',
            'oidc_client_id' => 'client',
            'oidc_client_secret' => 'secret',
            'oidc_mfa_blacklist' => 'sms,email,pin',
        ]);
        $handler->save();

        $handler2 = new DomainHandler();
        $handler2->init($this->testDomain);
        $handler2->view();
        $this->assertEquals(['sms', 'email', 'pin'], $handler2->getMfaBlacklist());
    }
}
