<?php

class DomainSmtpClientIpsTest extends \PHPUnit\Framework\TestCase
{
    private $origFlag;

    protected function setUp(): void
    {
        $this->origFlag = Config::read('domain_smtp_client_ips');
        $_SESSION = [
            'sessid' => [
                'roles' => ['global-admin']
            ]
        ];
    }

    protected function tearDown(): void
    {
        Config::write('domain_smtp_client_ips', $this->origFlag);
        $_SESSION = [];
    }

    public function testMigrationIsRetrySafe(): void
    {
        upgrade_1858();
        upgrade_1858();

        self::assertTrue(_db_field_exists(table_by_key('domain'), 'smtp_client_ips'));
    }

    public function testFieldIsHiddenWhenDisabled(): void
    {
        Config::write('domain_smtp_client_ips', 'NO');
        $struct = (new DomainHandler(0, 'admin', true))->getStruct();

        self::assertSame(0, $struct['smtp_client_ips']['editable']);
        self::assertSame(0, $struct['smtp_client_ips']['display_in_form']);
    }

    public function testSaveStoresNormalizedList(): void
    {
        Config::write('domain_smtp_client_ips', 'YES');
        $domain = $this->createDomain();

        try {
            $handler = new DomainHandler(0, 'admin', true);
            self::assertSame(1, $handler->getStruct()['smtp_client_ips']['editable']);
            self::assertTrue($handler->init($domain));
            self::assertTrue($handler->set(['smtp_client_ips' => '10.0.2.10 2001:DB8::10, 10.0.2.10']));
            self::assertTrue($handler->save());

            $stored = db_query_one(
                'SELECT smtp_client_ips FROM domain WHERE domain = :domain',
                ['domain' => $domain]
            );
            self::assertSame('10.0.2.10, 2001:db8::10', $stored['smtp_client_ips']);
        } finally {
            $this->deleteDomain($domain);
        }
    }

    public function testHostBitsAreRejectedWithHint(): void
    {
        Config::write('domain_smtp_client_ips', 'YES');
        $domain = $this->createDomain();

        try {
            $handler = new DomainHandler(0, 'admin', true);
            self::assertTrue($handler->init($domain));
            self::assertFalse($handler->set(['smtp_client_ips' => '10.0.2.10/24']));
            self::assertStringContainsString('10.0.2.10/24', $handler->errormsg['smtp_client_ips']);
            self::assertStringContainsString('network address', $handler->errormsg['smtp_client_ips']);
        } finally {
            $this->deleteDomain($domain);
        }
    }

    public function testEquivalentEntriesAreStoredOnce(): void
    {
        Config::write('domain_smtp_client_ips', 'YES');
        $domain = $this->createDomain();

        try {
            $handler = new DomainHandler(0, 'admin', true);
            self::assertTrue($handler->init($domain));
            self::assertTrue($handler->set(['smtp_client_ips' => '10.0.2.10 ::ffff:10.0.2.10 10.0.2.10/32 2001:DB8::/64 2001:db8:0:0::/64']));
            self::assertTrue($handler->save());
            $stored = db_query_one('SELECT smtp_client_ips FROM domain WHERE domain = :domain', ['domain' => $domain]);
            self::assertSame('10.0.2.10, 2001:db8::/64', $stored['smtp_client_ips']);
        } finally {
            $this->deleteDomain($domain);
        }
    }

    public function testInvalidEntryIsRejected(): void
    {
        Config::write('domain_smtp_client_ips', 'YES');
        $domain = $this->createDomain();

        try {
            $handler = new DomainHandler(0, 'admin', true);
            self::assertTrue($handler->init($domain));
            self::assertFalse($handler->set(['smtp_client_ips' => '10.0.2.10, web.example.com']));
            self::assertArrayHasKey('smtp_client_ips', $handler->errormsg);
            self::assertStringContainsString('web.example.com', $handler->errormsg['smtp_client_ips']);
        } finally {
            $this->deleteDomain($domain);
        }
    }

    public function testEmptyValueIsAllowed(): void
    {
        Config::write('domain_smtp_client_ips', 'YES');
        $domain = $this->createDomain();

        try {
            $handler = new DomainHandler(0, 'admin', true);
            self::assertTrue($handler->init($domain));
            self::assertTrue($handler->set(['smtp_client_ips' => '']));
            self::assertTrue($handler->save());
        } finally {
            $this->deleteDomain($domain);
        }
    }

    private function createDomain(): string
    {
        $domain = 'smtp-client-ips-' . uniqid() . '.example';
        db_insert('domain', ['domain' => $domain, 'description' => 'test', 'transport' => '']);
        db_insert(
            'domain_admins',
            ['username' => 'admin', 'domain' => $domain, 'active' => 1],
            ['created'],
            true
        );
        return $domain;
    }

    private function deleteDomain(string $domain): void
    {
        db_delete('domain_admins', 'domain', $domain);
        db_delete('domain', 'domain', $domain);
    }
}
