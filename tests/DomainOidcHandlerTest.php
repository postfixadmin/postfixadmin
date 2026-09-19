<?php

use PHPUnit\Framework\TestCase;

/**
 * Test DomainOidcHandler CRUD operations against SQLite.
 */
class DomainOidcHandlerTest extends TestCase
{
    private $testDomain = 'test-oidc-handler.com';

    public function setUp(): void
    {
        // Skip on MySQL - uses SQLite/PostgreSQL-specific SQL
        if (getenv('DATABASE') === 'mysql') {
            $this->markTestSkipped('SQLite/PostgreSQL-specific test');
        }

        // Reset global OIDC config to known state
        global $CONF;
        $CONF['oidc_mfa'] = 'none';
        $CONF['oidc_mfa_methods'] = ['mfa', 'otp', 'hwk'];
        $CONF['oidc_mfa_blacklist'] = [];

        // Clean up
        db_execute("DELETE FROM domain_oidc WHERE domain = ?", [$this->testDomain]);
        db_execute("DELETE FROM domain WHERE domain = ?", [$this->testDomain]);
    }

    public function tearDown(): void
    {
        db_execute("DELETE FROM domain_oidc WHERE domain = ?", [$this->testDomain]);
        db_execute("DELETE FROM domain WHERE domain = ?", [$this->testDomain]);
    }

    public function testSaveCreatesNewConfig(): void
    {
        // Create domain first
        db_execute("INSERT INTO domain (domain, description, transport) VALUES (?, ?, ?)", [$this->testDomain, 'Test', '']);

        $handler = new DomainOidcHandler($this->testDomain);
        $handler->save([
            'issuer_url' => 'https://keycloak.example.com/realms/test',
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
        ]);

        $this->assertTrue($handler->exists());

        $config = $handler->get();
        $this->assertEquals($this->testDomain, $config['domain']);
        $this->assertEquals('https://keycloak.example.com/realms/test', $config['issuer_url']);
        $this->assertEquals('test-client', $config['client_id']);
    }

    public function testSaveUpdatesExistingConfig(): void
    {
        db_execute("INSERT INTO domain (domain, description, transport) VALUES (?, ?, ?)", [$this->testDomain, 'Test', '']);

        $handler = new DomainOidcHandler($this->testDomain);
        $handler->save([
            'issuer_url' => 'https://old.example.com',
            'client_id' => 'old-client',
            'client_secret' => 'old-secret',
        ]);

        $handler->save([
            'issuer_url' => 'https://new.example.com',
            'client_id' => 'new-client',
            'client_secret' => 'new-secret',
        ]);

        $config = $handler->get();
        $this->assertEquals('https://new.example.com', $config['issuer_url']);
        $this->assertEquals('new-client', $config['client_id']);
    }

    public function testDelete(): void
    {
        db_execute("INSERT INTO domain (domain, description, transport) VALUES (?, ?, ?)", [$this->testDomain, 'Test', '']);

        $handler = new DomainOidcHandler($this->testDomain);
        $handler->save([
            'issuer_url' => 'https://keycloak.example.com',
            'client_id' => 'client',
            'client_secret' => 'secret',
        ]);

        $this->assertTrue($handler->exists());
        $handler->delete();
        $this->assertFalse($handler->exists());
    }

    public function testGetByIssuer(): void
    {
        db_execute("INSERT INTO domain (domain, description, transport) VALUES (?, ?, ?)", [$this->testDomain, 'Test', '']);

        $handler = new DomainOidcHandler($this->testDomain);
        $handler->save([
            'issuer_url' => 'https://keycloak.example.com/realms/special',
            'client_id' => 'client',
            'client_secret' => 'secret',
        ]);

        $config = DomainOidcHandler::getByIssuer('https://keycloak.example.com/realms/special');
        $this->assertNotFalse($config);
        $this->assertEquals($this->testDomain, $config['domain']);
    }

    public function testGetAll(): void
    {
        db_execute("INSERT INTO domain (domain, description, transport) VALUES (?, ?, ?)", [$this->testDomain, 'Test', '']);

        $handler = new DomainOidcHandler($this->testDomain);
        $handler->save([
            'issuer_url' => 'https://keycloak.example.com',
            'client_id' => 'client',
            'client_secret' => 'secret',
        ]);

        $all = DomainOidcHandler::getAll();
        $this->assertNotEmpty($all);

        $found = false;
        foreach ($all as $config) {
            if ($config['domain'] === $this->testDomain) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Test domain should be in getAll() results');
    }

    public function testGetMfaMethodsFallsBackToGlobal(): void
    {
        global $CONF;
        $CONF['oidc_mfa_methods'] = ['mfa', 'otp', 'hwk'];

        db_execute("INSERT INTO domain (domain, description, transport) VALUES (?, ?, ?)", [$this->testDomain, 'Test', '']);

        $handler = new DomainOidcHandler($this->testDomain);
        $handler->save([
            'issuer_url' => 'https://keycloak.example.com',
            'client_id' => 'client',
            'client_secret' => 'secret',
        ]);

        $methods = $handler->getMfaMethods();
        $this->assertEquals(['mfa', 'otp', 'hwk'], $methods);
    }

    public function testGetMfaMethodsPerDomain(): void
    {
        global $CONF;
        $CONF['oidc_mfa_methods'] = ['mfa', 'otp', 'hwk'];

        db_execute("INSERT INTO domain (domain, description, transport) VALUES (?, ?, ?)", [$this->testDomain, 'Test', '']);

        $handler = new DomainOidcHandler($this->testDomain);
        $handler->save([
            'issuer_url' => 'https://keycloak.example.com',
            'client_id' => 'client',
            'client_secret' => 'secret',
            'mfa_methods' => 'mfa,fido,face',
        ]);

        $methods = $handler->getMfaMethods();
        $this->assertEquals(['mfa', 'fido', 'face'], $methods);
    }

    public function testGetMfaPolicyFallsBackToGlobal(): void
    {
        global $CONF;
        $CONF['oidc_mfa'] = 'mfa_or_totp';

        db_execute("INSERT INTO domain (domain, description, transport) VALUES (?, ?, ?)", [$this->testDomain, 'Test', '']);

        $handler = new DomainOidcHandler($this->testDomain);
        $handler->save([
            'issuer_url' => 'https://keycloak.example.com',
            'client_id' => 'client',
            'client_secret' => 'secret',
        ]);

        $policy = $handler->getMfaPolicy();
        $this->assertEquals('mfa_or_totp', $policy);
    }

    public function testGetMfaPolicyPerDomain(): void
    {
        global $CONF;
        $CONF['oidc_mfa'] = 'none';

        db_execute("INSERT INTO domain (domain, description, transport) VALUES (?, ?, ?)", [$this->testDomain, 'Test', '']);

        $handler = new DomainOidcHandler($this->testDomain);
        $handler->save([
            'issuer_url' => 'https://keycloak.example.com',
            'client_id' => 'client',
            'client_secret' => 'secret',
            'mfa_policy' => 'idp_mfa',
        ]);

        $policy = $handler->getMfaPolicy();
        $this->assertEquals('idp_mfa', $policy);
    }

    public function testGetMfaBlacklistPerDomain(): void
    {
        db_execute("INSERT INTO domain (domain, description, transport) VALUES (?, ?, ?)", [$this->testDomain, 'Test', '']);

        $handler = new DomainOidcHandler($this->testDomain);
        $handler->save([
            'issuer_url' => 'https://keycloak.example.com',
            'client_id' => 'client',
            'client_secret' => 'secret',
            'mfa_blacklist' => 'sms,email,pin',
        ]);

        $blacklist = $handler->getMfaBlacklist();
        $this->assertEquals(['sms', 'email', 'pin'], $blacklist);
    }
}
