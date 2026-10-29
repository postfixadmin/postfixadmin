<?php

class DomainSmtpClientIpsTest extends \PHPUnit\Framework\TestCase
{
    private $origFlag;
    private $origPolicy;

    protected function setUp(): void
    {
        $this->origFlag = Config::read('domain_smtp_client_ips');
        $this->origPolicy = Config::read('smtp_client_policy');
        $_SESSION = [
            'sessid' => [
                'roles' => ['global-admin']
            ]
        ];
    }

    protected function tearDown(): void
    {
        Config::write('domain_smtp_client_ips', $this->origFlag);
        Config::write('smtp_client_policy', $this->origPolicy);
        $_SESSION = [];
    }

    public function testMigrationIsRetrySafe(): void
    {
        upgrade_1858();
        upgrade_1858();

        self::assertTrue(_db_field_exists(table_by_key('domain'), 'smtp_client_ips'));
    }

    public function testSaveWarnsAboutAddressesThePolicyDoesNotCheck(): void
    {
        Config::write('domain_smtp_client_ips', 'YES');
        $map = (string)tempnam(sys_get_temp_dir(), 'pfa-cidr');
        file_put_contents($map, "10.0.2.250\tDUNNO\n10.0.2.0/24\tpfa_smtp_client_policy\n");
        Config::write('smtp_client_policy', ['client_networks_map' => $map]);
        $domain = $this->createDomain();

        try {
            $handler = new DomainHandler(0, 'admin', true);
            self::assertTrue($handler->init($domain));
            self::assertTrue($handler->set(['smtp_client_ips' => '10.0.2.10, 10.0.2.250, 10.20.0.5']));
            self::assertTrue($handler->save(), 'warnings do not block saving');

            $messages = implode("\n", $handler->errormsg);
            self::assertStringContainsString('10.0.2.250 (matched first by line 1 (10.0.2.250 DUNNO))', $messages);
            self::assertStringContainsString("10.20.0.5 (not in $map)", $messages);
            self::assertStringNotContainsString('10.0.2.10 ', $messages);

            $stored = db_query_one('SELECT smtp_client_ips FROM domain WHERE domain = :domain', ['domain' => $domain]);
            self::assertSame('10.0.2.10, 10.0.2.250, 10.20.0.5', $stored['smtp_client_ips']);
        } finally {
            $this->deleteDomain($domain);
            unlink($map);
        }
    }

    public function testSaveWithoutWarnings(): void
    {
        Config::write('domain_smtp_client_ips', 'YES');
        Config::write('smtp_client_policy', ['client_networks' => ['10.0.2.0/24']]);
        $domain = $this->createDomain();

        try {
            $handler = new DomainHandler(0, 'admin', true);
            self::assertTrue($handler->init($domain));
            self::assertTrue($handler->set(['smtp_client_ips' => '10.0.2.10']));
            self::assertTrue($handler->save());
            self::assertSame([], $handler->errormsg);
        } finally {
            $this->deleteDomain($domain);
        }
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
