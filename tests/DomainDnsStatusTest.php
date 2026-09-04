<?php

class DomainDnsStatusTest extends \PHPUnit\Framework\TestCase
{
    public function testDnsStatusMigrationIsRetrySafe(): void
    {
        upgrade_1857();
        upgrade_1857();

        self::assertTrue(_db_field_exists(table_by_key('domain'), 'dns_active'));
        self::assertTrue(_db_field_exists(table_by_key('domain'), 'dns_checked'));
    }

    public function testDomainIsActiveWhenAnAuthoritativeNameserverResponds(): void
    {
        $checker = new FakeDomainDnsStatus(
            ['example.com' => ['ns1.example.net', 'ns2.example.net']],
            ['ns1.example.net' => ['192.0.2.1'], 'ns2.example.net' => ['192.0.2.2']],
            ['192.0.2.2']
        );
        self::assertTrue($checker->isActive('example.com'));
    }

    public function testDomainIsInactiveWithoutDelegation(): void
    {
        self::assertFalse((new FakeDomainDnsStatus([], [], []))->isActive('example.com'));
    }

    public function testDomainIsInactiveWhenNoAuthoritativeNameserverResponds(): void
    {
        $checker = new FakeDomainDnsStatus(
            ['example.com' => ['ns1.example.net']],
            ['ns1.example.net' => ['192.0.2.1']],
            []
        );
        self::assertFalse($checker->isActive('example.com'));
    }

    public function testDomainIsActiveWhenMxTargetHasAnAddress(): void
    {
        $checker = new FakeDomainDnsStatus(
            [],
            ['mx.example.net' => ['192.0.2.25']],
            [],
            2,
            ['example.com' => ['mx.example.net']]
        );
        self::assertTrue($checker->isActive('example.com'));
    }

    public function testDomainIsInactiveWithoutUsableMx(): void
    {
        $checker = new FakeDomainDnsStatus([], [], [], 2, ['example.com' => ['mx.example.net']]);
        self::assertFalse($checker->isActive('example.com'));
    }

    public function testDisabledModeDoesNotCheckOrPersistDomains(): void
    {
        $checker = new FakeDomainDnsStatus([], [], [], 0);
        self::assertSame(['active' => 0, 'inactive' => 0], $checker->refresh(['example.com']));
        self::assertFalse($checker->isActive('example.com'));
    }

    public function testRefreshPersistsStatusAndInactiveCount(): void
    {
        $active = 'dns-active-' . uniqid() . '.example';
        $inactive = 'dns-inactive-' . uniqid() . '.example';
        db_insert('domain', ['domain' => $active, 'description' => 'test', 'transport' => '']);
        db_insert('domain', ['domain' => $inactive, 'description' => 'test', 'transport' => '']);

        try {
            $checker = new FakeDomainDnsStatus(
                [$active => ['ns.example.net'], $inactive => []],
                ['ns.example.net' => ['192.0.2.1']],
                ['192.0.2.1']
            );
            self::assertSame(['active' => 1, 'inactive' => 1], $checker->refresh([$active, $inactive]));
            self::assertSame(1, DomainDnsStatus::countInactive([$active, $inactive]));
            $rows = db_query_all(
                'SELECT dns_checked FROM domain WHERE domain IN (:active, :inactive)',
                ['active' => $active, 'inactive' => $inactive]
            );
            self::assertCount(2, $rows);
            self::assertNotEmpty($rows[0]['dns_checked']);
            self::assertNotEmpty($rows[1]['dns_checked']);

            $_SESSION = ['sessid' => ['roles' => ['global-admin']]];
            $handler = new DomainHandler();
            self::assertTrue($handler->getList(['dns_active' => 0]));
            self::assertArrayHasKey($inactive, $handler->result());
            self::assertArrayNotHasKey($active, $handler->result());
        } finally {
            db_delete('domain', 'domain', $active);
            db_delete('domain', 'domain', $inactive);
        }
    }
}

class FakeDomainDnsStatus extends DomainDnsStatus
{
    public function __construct(
        private array $ns,
        private array $addresses,
        private array $respondingAddresses,
        int $mode = 1,
        private array $mx = []
    ) {
        parent::__construct(0.01, $mode);
    }

    protected function nameservers(string $domain): array
    {
        return $this->ns[$domain] ?? [];
    }

    protected function nameserverAddresses(string $nameserver): array
    {
        return $this->addresses[$nameserver] ?? [];
    }

    protected function mxTargets(string $domain): array
    {
        return $this->mx[$domain] ?? [];
    }

    protected function authoritativeServerResponds(string $domain, string $address): bool
    {
        return in_array($address, $this->respondingAddresses, true);
    }
}
