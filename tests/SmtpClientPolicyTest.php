<?php

/**
 * Decision tests for the SMTP client policy against the test database.
 * Addresses use the documentation ranges 192.0.2.0/24 and 2001:db8::/32.
 */
class SmtpClientPolicyTest extends \PHPUnit\Framework\TestCase
{
    private string $com;
    private string $org;
    private string $net;
    private string $off;
    private int $now = 1000;

    protected function setUp(): void
    {
        $id = uniqid();
        $this->com = "pol-com-$id.example";
        $this->org = "pol-org-$id.example";
        $this->net = "pol-net-$id.example";   // alias domain of $com
        $this->off = "pol-off-$id.example";   // inactive domain

        $this->insertDomain($this->com, '192.0.2.10, 192.0.2.32/28, 2001:db8::10', true);
        $this->insertDomain($this->org, '192.0.2.11, 192.0.2.10, 192.0.2.40', true);
        $this->insertDomain($this->net, '', true);
        $this->insertDomain($this->off, '192.0.2.20', false);

        db_insert('alias_domain', ['alias_domain' => $this->net, 'target_domain' => $this->com, 'active' => db_get_boolean(true)]);
        $this->insertAlias("noreply@{$this->com}", $this->com, true);
        $this->insertAlias("disabled@{$this->com}", $this->com, false);
        $this->insertAlias("@{$this->org}", $this->org, true); // catch-all
        $this->insertAlias("app@{$this->off}", $this->off, true);
    }

    protected function tearDown(): void
    {
        foreach ([$this->com, $this->org, $this->net, $this->off] as $domain) {
            db_delete('alias', 'domain', $domain);
            db_delete('alias_domain', 'alias_domain', $domain);
            db_delete('domain', 'domain', $domain);
        }
    }

    private function policy(array $options = []): SmtpClientPolicy
    {
        $options += ['client_networks' => ['192.0.2.0/24', '2001:db8::/64'], 'cache_ttl' => 30];
        return new SmtpClientPolicy($options, null, null, function () {
            return $this->now;
        });
    }

    private function ask(SmtpClientPolicy $policy, string $client, string $sender, array $extra = []): string
    {
        return $policy->decide(['request' => 'smtpd_access_policy', 'client_address' => $client, 'sender' => $sender] + $extra);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('decisionProvider')]
    public function testDecision(string $client, string $senderTemplate, string $expectedPrefix, array $options = [], array $extra = []): void
    {
        $sender = strtr($senderTemplate, ['COM' => $this->com, 'ORG' => $this->org, 'NET' => $this->net, 'OFF' => $this->off]);
        $action = $this->ask($this->policy($options), $client, $sender, $extra);
        self::assertStringStartsWith($expectedPrefix, $action, "$client <$sender> -> $action");
    }

    public static function decisionProvider(): array
    {
        return [
            'own domain' => ['192.0.2.10', 'noreply@COM', 'DUNNO'],
            'own domain, upper case' => ['192.0.2.10', 'NoReply@COM', 'DUNNO'],
            'other domain' => ['192.0.2.11', 'noreply@COM', 'REJECT 5.7.1 Sender domain'],
            'alias domain of own domain' => ['192.0.2.10', 'noreply@NET', 'DUNNO'],
            'host listed for two domains' => ['192.0.2.10', 'anything@ORG', 'DUNNO'],
            'network entry' => ['192.0.2.33', 'noreply@COM', 'DUNNO'],
            'ipv6 host' => ['2001:db8::10', 'noreply@COM', 'DUNNO'],
            'ipv4-mapped client address' => ['::ffff:192.0.2.10', 'noreply@COM', 'DUNNO'],
            'overlapping networks combine domains (com)' => ['192.0.2.40', 'noreply@COM', 'DUNNO'],
            'overlapping networks combine domains (org)' => ['192.0.2.40', 'x@ORG', 'DUNNO'],
            'sender does not exist' => ['192.0.2.10', 'nobody@COM', 'REJECT 5.7.1 Sender address'],
            'sender alias inactive' => ['192.0.2.10', 'disabled@COM', 'REJECT 5.7.1 Sender address'],
            'catch-all counts as existing' => ['192.0.2.11', 'whatever@ORG', 'DUNNO'],
            'existence check disabled' => ['192.0.2.10', 'nobody@COM', 'DUNNO', ['require_existing_sender' => false]],
            'subdomain not permitted' => ['192.0.2.10', 'cron@app.COM', 'REJECT 5.7.1 Sender domain', ['require_existing_sender' => false]],
            'subdomain permitted' => ['192.0.2.10', 'cron@app.COM', 'DUNNO', ['require_existing_sender' => false, 'allow_subdomains' => true]],
            'null sender' => ['192.0.2.10', '', 'REJECT 5.7.1 Null sender'],
            'null sender permitted' => ['192.0.2.10', '', 'DUNNO', ['reject_null_sender' => false]],
            'sasl session is not restricted' => ['192.0.2.11', 'user@COM', 'DUNNO', [], ['sasl_username' => 'user@COM']],
            'only inactive domain' => ['192.0.2.20', 'app@OFF', 'REJECT 5.7.1 Client is not assigned to an active domain'],
            'unassigned in client_networks' => ['192.0.2.99', 'noreply@COM', 'REJECT 5.7.1 Client is not assigned'],
            'unassigned ipv6 in client_networks' => ['2001:db8::99', 'noreply@COM', 'REJECT 5.7.1 Client is not assigned'],
            'unassigned outside client_networks' => ['198.51.100.1', 'noreply@COM', 'DUNNO'],
            'unassigned with action dunno' => ['192.0.2.99', 'noreply@COM', 'DUNNO', ['unassigned_client_action' => 'dunno']],
            'client address unknown' => ['unknown', 'noreply@COM', 'DUNNO'],
        ];
    }

    /** Regression: clearing a client's last assignment must not lift the restriction. */
    public function testRemovingLastAssignmentRejectsAfterCacheRefresh(): void
    {
        $policy = $this->policy();
        self::assertSame('DUNNO', $this->ask($policy, '192.0.2.11', "x@{$this->org}"));

        // 192.0.2.11 is only listed for $org
        db_update('domain', 'domain', $this->org, ['smtp_client_ips' => '192.0.2.10, 192.0.2.40']);

        self::assertSame('DUNNO', $this->ask($policy, '192.0.2.11', "x@{$this->org}"), 'cached until cache_ttl expires');
        $this->now += 30;
        self::assertStringStartsWith('REJECT 5.7.1 Client is not assigned', $this->ask($policy, '192.0.2.11', "x@{$this->org}"));
    }

    /** With unassigned_client_action = dunno the restriction is lifted - documented behaviour. */
    public function testRemovingLastAssignmentWithDunnoFallsBackToNormalProcessing(): void
    {
        $policy = $this->policy(['unassigned_client_action' => 'dunno']);
        db_update('domain', 'domain', $this->org, ['smtp_client_ips' => '192.0.2.10']);
        $this->now += 30;
        self::assertSame('DUNNO', $this->ask($policy, '192.0.2.11', "noreply@{$this->com}"));
    }

    /** Regression: deactivating a client's only domain must not lift the restriction. */
    public function testDeactivatingOnlyDomainRejectsAfterCacheRefresh(): void
    {
        $policy = $this->policy();
        self::assertSame('DUNNO', $this->ask($policy, '192.0.2.11', "x@{$this->org}"));

        db_update('domain', 'domain', $this->org, ['active' => db_get_boolean(false)]);
        $this->now += 30;

        self::assertStringStartsWith('REJECT 5.7.1 Client is not assigned to an active domain', $this->ask($policy, '192.0.2.11', "x@{$this->org}"));
        // 192.0.2.10 is also listed for the still active $com
        self::assertSame('DUNNO', $this->ask($policy, '192.0.2.10', "noreply@{$this->com}"));
        self::assertStringStartsWith('REJECT 5.7.1 Sender domain', $this->ask($policy, '192.0.2.10', "x@{$this->org}"));
    }

    public function testInvalidStoredEntryIsSkipped(): void
    {
        // written directly, bypassing DomainHandler validation
        db_update('domain', 'domain', $this->org, ['smtp_client_ips' => '192.0.2.11/24 garbage 192.0.2.11']);
        $policy = $this->policy();
        self::assertSame('DUNNO', $this->ask($policy, '192.0.2.11', "x@{$this->org}"));
        self::assertStringStartsWith('REJECT 5.7.1 Client is not assigned', $this->ask($policy, '192.0.2.12', "x@{$this->org}"));
    }

    public function testDatabaseFailureIsTemporary(): void
    {
        $policy = new SmtpClientPolicy(['client_networks' => ['192.0.2.0/24']], function () {
            throw new Exception('database unavailable');
        });
        self::assertSame(SmtpClientPolicy::TEMPFAIL, $this->ask($policy, '192.0.2.10', "noreply@{$this->com}"));

        $policy = new SmtpClientPolicy(['client_networks' => ['192.0.2.0/24']], null, function () {
            throw new Exception('database unavailable');
        });
        self::assertSame(SmtpClientPolicy::TEMPFAIL, $this->ask($policy, '192.0.2.10', "noreply@{$this->com}"));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidOptionsProvider')]
    public function testInvalidOptionsAreRejected(array $options, string $message): void
    {
        try {
            SmtpClientPolicy::validateOptions($options);
            self::fail('expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString($message, $e->getMessage());
        }
    }

    public static function invalidOptionsProvider(): array
    {
        return [
            'unknown option' => [['client_networks' => ['192.0.2.0/24'], 'unknown_client_action' => 'reject'], 'unknown option'],
            'bad action' => [['unassigned_client_action' => 'deny', 'client_networks' => ['192.0.2.0/24']], 'unassigned_client_action'],
            'reject without networks' => [[], 'requires client_networks'],
            'invalid network' => [['client_networks' => ['192.0.2.0/24', 'web.example.com']], 'invalid entry in client_networks'],
            'host bits in network' => [['client_networks' => ['192.0.2.10/24']], 'host bits set'],
            'network not a string' => [['client_networks' => [24]], 'invalid entry in client_networks'],
            'networks not a list' => [['client_networks' => 24], 'must be a list'],
            'bool option as string' => [['client_networks' => ['192.0.2.0/24'], 'reject_null_sender' => 'yes'], 'reject_null_sender'],
            'negative cache_ttl' => [['client_networks' => ['192.0.2.0/24'], 'cache_ttl' => -1], 'cache_ttl'],
        ];
    }

    public function testValidOptionsAreNormalized(): void
    {
        $o = SmtpClientPolicy::validateOptions(['client_networks' => '192.0.2.0/24, 2001:DB8::/64 192.0.2.0/24']);
        self::assertSame(['192.0.2.0/24', '2001:db8::/64'], $o['client_networks']);
        self::assertSame('reject', $o['unassigned_client_action']);

        $o = SmtpClientPolicy::validateOptions(['unassigned_client_action' => 'dunno']);
        self::assertSame([], $o['client_networks']);
    }

    public function testServeAnswersEachRequest(): void
    {
        $in = $this->stream(
            "request=smtpd_access_policy\nclient_address=192.0.2.10\nsender=noreply@{$this->com}\n\n"
            . "request=smtpd_access_policy\nclient_address=192.0.2.11\nsender=noreply@{$this->com}\n\n"
        );
        $out = fopen('php://memory', 'w+');

        self::assertSame(2, SmtpClientPolicy::serve($in, $out, $this->policy()));
        rewind($out);
        $response = stream_get_contents($out);
        self::assertMatchesRegularExpression("/^action=DUNNO\n\naction=REJECT 5\\.7\\.1 Sender domain [^\n]+\n\n$/", $response);
    }

    /** Regression: a broken database connection must not be reused for later requests. */
    public function testServeEndsAfterDatabaseErrorSoTheNextProcessReconnects(): void
    {
        $policy = new SmtpClientPolicy(['client_networks' => ['192.0.2.0/24']], function () {
            throw new Exception('MySQL server has gone away');
        });
        $in = $this->stream(
            "request=smtpd_access_policy\nclient_address=192.0.2.10\nsender=noreply@{$this->com}\n\n"
            . "request=smtpd_access_policy\nclient_address=192.0.2.10\nsender=noreply@{$this->com}\n\n"
        );
        $out = fopen('php://memory', 'w+');

        self::assertSame(1, SmtpClientPolicy::serve($in, $out, $policy));
        rewind($out);
        self::assertSame('action=' . SmtpClientPolicy::TEMPFAIL . "\n\n", stream_get_contents($out));
        self::assertTrue($policy->failed());
    }

    public function testServeWithStartupErrorAnswersTemporaryFailure(): void
    {
        $in = $this->stream("request=smtpd_access_policy\nclient_address=192.0.2.10\nsender=noreply@{$this->com}\n\n");
        $out = fopen('php://memory', 'w+');

        self::assertSame(1, SmtpClientPolicy::serve($in, $out, null, 'invalid configuration'));
        rewind($out);
        self::assertSame('action=' . SmtpClientPolicy::TEMPFAIL . "\n\n", stream_get_contents($out));
    }

    public function testServeOverlongLineAnswersTemporaryFailureForThatRequestOnly(): void
    {
        $in = $this->stream(
            "request=smtpd_access_policy\nclient_address=192.0.2.10\nsender=" . str_repeat('a', 10000) . "\n\n"
            . "request=smtpd_access_policy\nclient_address=192.0.2.10\nsender=noreply@{$this->com}\n\n"
        );
        $out = fopen('php://memory', 'w+');

        self::assertSame(2, SmtpClientPolicy::serve($in, $out, $this->policy()));
        rewind($out);
        self::assertSame('action=' . SmtpClientPolicy::TEMPFAIL . "\n\naction=DUNNO\n\n", stream_get_contents($out));
    }

    /** @return resource */
    private function stream(string $data)
    {
        $in = fopen('php://memory', 'w+');
        fwrite($in, $data);
        rewind($in);
        return $in;
    }

    private function insertDomain(string $domain, string $ips, bool $active): void
    {
        db_insert('domain', [
            'domain' => $domain,
            'description' => 'smtp client policy test',
            'transport' => '',
            'smtp_client_ips' => $ips,
            'active' => db_get_boolean($active),
        ]);
    }

    private function insertAlias(string $address, string $domain, bool $active): void
    {
        db_insert('alias', ['address' => $address, 'goto' => "admin@$domain", 'domain' => $domain, 'active' => db_get_boolean($active)]);
    }
}
