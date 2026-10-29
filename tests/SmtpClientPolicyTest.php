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
    /** @var string[] */
    private array $tempFiles = [];

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
        foreach ($this->tempFiles as $file) {
            @chmod($file, 0600);
            @unlink($file);
        }
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
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidRequestProvider')]
    public function testInvalidRequestIsDeferred(array $attrs): void
    {
        $policy = $this->policy();
        self::assertSame(SmtpClientPolicy::INVALID_REQUEST, $policy->decide($attrs));
        self::assertFalse($policy->failed(), 'no reason to restart the process');
    }

    public static function invalidRequestProvider(): array
    {
        $valid = ['request' => 'smtpd_access_policy', 'client_address' => '192.0.2.10', 'sender' => 'noreply@example.com'];
        $without = function (string $key) use ($valid): array {
            unset($valid[$key]);
            return $valid;
        };
        return [
            'empty request' => [[]],
            'request type missing' => [$without('request')],
            'other request type' => [['request' => 'junk'] + $valid],
            'client_address missing' => [$without('client_address')],
            'client_address empty' => [['client_address' => ''] + $valid],
            'client_address unknown' => [['client_address' => 'unknown'] + $valid],
            'client_address hostname' => [['client_address' => 'mail.example.com'] + $valid],
            'client_address network' => [['client_address' => '192.0.2.0/24'] + $valid],
            'sender missing' => [$without('sender')],
            'sasl session still needs a valid request' => [['sasl_username' => 'user'] + $without('client_address')],
        ];
    }

    public function testEmptySenderIsAValidRequest(): void
    {
        $policy = $this->policy(['reject_null_sender' => false]);
        self::assertSame('DUNNO', $this->ask($policy, '192.0.2.10', ''));
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
            'networks and map' => [['client_networks' => ['192.0.2.0/24'], 'client_networks_map' => '/etc/postfix/smtp_client_networks.cidr'], 'either client_networks or client_networks_map'],
            'relative map path' => [['client_networks_map' => 'smtp_client_networks.cidr'], 'absolute path'],
            'map path not a string' => [['client_networks_map' => true], 'absolute path'],
            'bad class name' => [['client_networks_map' => '/etc/postfix/x.cidr', 'client_networks_map_class' => 'a b'], 'restriction class name'],
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
    public function testServeLineWithoutEqualsSignIsInvalidForThatRequestOnly(): void
    {
        $in = $this->stream(
            "request=smtpd_access_policy\nclient_address=192.0.2.10\nsender=noreply@{$this->com}\ngarbage\n\n"
            . "request=smtpd_access_policy\nclient_address=192.0.2.10\nsender=noreply@{$this->com}\n\n"
        );
        $out = fopen('php://memory', 'w+');

        self::assertSame(2, SmtpClientPolicy::serve($in, $out, $this->policy()));
        rewind($out);
        self::assertSame('action=' . SmtpClientPolicy::INVALID_REQUEST . "\n\naction=DUNNO\n\n", stream_get_contents($out));
    }

    public function testServeEmptyRequestIsInvalid(): void
    {
        $in = $this->stream("\n");
        $out = fopen('php://memory', 'w+');

        self::assertSame(1, SmtpClientPolicy::serve($in, $out, $this->policy()));
        rewind($out);
        self::assertSame('action=' . SmtpClientPolicy::INVALID_REQUEST . "\n\n", stream_get_contents($out));
    }

    public function testCidrMapIsGeneratedFromClientNetworks(): void
    {
        $policy = $this->policy(['client_networks' => ['192.0.2.0/24', '2001:db8::/64']]);
        $map = $policy->cidrMap();
        self::assertStringContainsString("192.0.2.0/24\tpfa_smtp_client_policy\n", $map);
        self::assertStringContainsString("2001:db8::/64\tpfa_smtp_client_policy\n", $map);
        self::assertSame(['errors' => [], 'warnings' => []], $policy->checkCidrMap($map, $this->assignments('192.0.2.10', '2001:db8::10')));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cidrMapProvider')]
    public function testCidrMapCheck(string $map, array $assigned, array $expectedErrors, array $expectedWarnings): void
    {
        $result = $this->policy(['client_networks' => ['192.0.2.0/24']])->checkCidrMap($map, $this->assignments(...$assigned));
        self::assertCount(count($expectedErrors), $result['errors'], implode("\n", $result['errors']));
        foreach ($expectedErrors as $i => $text) {
            self::assertStringContainsString($text, $result['errors'][$i]);
        }
        self::assertCount(count($expectedWarnings), $result['warnings'], implode("\n", $result['warnings']));
        foreach ($expectedWarnings as $i => $text) {
            self::assertStringContainsString($text, $result['warnings'][$i]);
        }
    }

    public static function cidrMapProvider(): array
    {
        $class = "\tpfa_smtp_client_policy";
        return [
            'matching map' => ["# comment\n\n192.0.2.0/24$class\n", ['192.0.2.10'], [], []],
            'larger network in the map' => ["192.0.0.0/16$class\n", ['192.0.2.10'], [], ['192.0.0.0/16 is sent to the policy but not in client_networks']],
            'bracketed address' => ["[192.0.2.0]/24$class\n", ['192.0.2.10'], [], []],
            'network missing' => ["198.51.100.0/24$class\n", ['192.0.2.10'], [
                'client_networks entry 192.0.2.0/24: not in the map',
                'example.com: 192.0.2.10 not in the map - its binding is not (fully) enforced (map incomplete',
            ], ['198.51.100.0/24 is sent to the policy but not in client_networks']],
            'only partly covered' => ["192.0.2.0/25$class\n", ['192.0.2.10'], ['192.0.2.0/24: only partly covered'], []],
            'shadowed by an earlier line' => ["192.0.0.0/16\tOK\n192.0.2.0/24$class\n", ['192.0.2.10'], [
                'matched first by line 1 (192.0.0.0/16 OK)',
                'example.com: 192.0.2.10 matched first by line 1',
            ], []],
            'assignment outside client_networks and map' => ["192.0.2.0/24$class\n", ['192.0.2.10', '203.0.113.7'], [
                'example.com: 203.0.113.7 not in the map',
            ], []],
            'wrong class name' => ["192.0.2.0/24\tother_class\n", ['192.0.2.10'], [
                'matched first by line 1 (192.0.2.0/24 other_class)',
                'example.com: 192.0.2.10 matched first by line 1',
            ], []],
            'host bits in the map' => ["192.0.2.1/24$class\n192.0.2.0/24$class\n", ['192.0.2.10'], ['line 1: \'192.0.2.1/24\' has host bits set'], []],
            'if block' => ["if 192.0.2.0/24\n192.0.2.0/24$class\nendif\n", ['192.0.2.10'], [], ['line 1: if/endif', 'line 3: if/endif']],
            'empty map' => ['', ['192.0.2.10'], ['client_networks entry 192.0.2.0/24: not in the map', 'example.com: 192.0.2.10 not in the map'], []],
            'exception before the network' => ["192.0.2.250\tDUNNO\n192.0.2.0/24$class\n", ['192.0.2.10'], [], [
                'line 1: 192.0.2.250 DUNNO comes before line 2 (192.0.2.0/24) - exception: these clients are not checked by the policy',
            ]],
            'exception with assignment' => ["192.0.2.0/28\tOK\n192.0.2.0/24$class\n", ['192.0.2.10', '192.0.2.20'], [
                'example.com: 192.0.2.10 matched first by line 1 (192.0.2.0/28 OK) - its binding is not (fully) enforced',
            ], ['line 1: 192.0.2.0/28 OK comes before line 2']],
            'line after the network is never used' => ["192.0.2.0/24$class\n192.0.2.250\tREJECT\n", ['192.0.2.10'], [], [
                'line 2: 192.0.2.250 REJECT is never used - line 1 (192.0.2.0/24) matches these clients first',
            ]],
            'unrelated line' => ["198.51.100.0/24\tOK\n192.0.2.0/24$class\n", ['192.0.2.10'], [], []],
        ];
    }

    private function mapFile(string $content): string
    {
        $file = (string)tempnam(sys_get_temp_dir(), 'pfa-cidr');
        file_put_contents($file, $content);
        $this->tempFiles[] = $file;
        return $file;
    }

    private static function assertThrows(string $class, string $message, callable $code): void
    {
        try {
            $code();
            self::fail("expected $class");
        } catch (Throwable $e) {
            self::assertInstanceOf($class, $e, $e->getMessage());
            self::assertStringContainsString($message, $e->getMessage());
        }
    }

    public function testNetworksAreReadFromTheMap(): void
    {
        $file = $this->mapFile("# forwarding hosts\n192.0.2.0/24\tpfa_smtp_client_policy\n198.51.100.0/24\tOK\n2001:db8::/64\tpfa_smtp_client_policy\n");
        $policy = new SmtpClientPolicy(['client_networks_map' => $file], null, null, function () {
            return $this->now;
        });
        self::assertSame(['192.0.2.0/24', '2001:db8::/64'], $policy->options()['client_networks']);
        self::assertStringStartsWith('REJECT 5.7.1 Client is not assigned', $this->ask($policy, '192.0.2.99', "noreply@{$this->com}"));
        self::assertStringStartsWith('REJECT 5.7.1 Client is not assigned', $this->ask($policy, '2001:db8::99', "noreply@{$this->com}"));
        self::assertSame('DUNNO', $this->ask($policy, '198.51.100.1', "noreply@{$this->com}"), 'other action, not a policy network');
        self::assertSame('DUNNO', $this->ask($policy, '192.0.2.10', "noreply@{$this->com}"));

        // read once per process, like smtpd: a later change needs a new process (postfix reload)
        file_put_contents($file, "203.0.113.0/24\tpfa_smtp_client_policy\n");
        self::assertSame(['192.0.2.0/24', '2001:db8::/64'], $policy->options()['client_networks']);
    }

    public function testMapModeChecksTheSameFile(): void
    {
        $file = $this->mapFile("192.0.0.0/16\tOK\n192.0.2.0/24\tpfa_smtp_client_policy\n");
        $policy = new SmtpClientPolicy(['client_networks_map' => $file]);
        $result = $policy->checkCidrMap((string)file_get_contents($file), $this->assignments('192.0.2.10'));
        self::assertStringContainsString('policy network 192.0.2.0/24: matched first by line 1 (192.0.0.0/16 OK)', $result['errors'][0]);
    }

    public function testCustomClassName(): void
    {
        $file = $this->mapFile("192.0.2.0/24\tcontainers\n");
        $policy = new SmtpClientPolicy(['client_networks_map' => $file, 'client_networks_map_class' => 'containers']);
        self::assertSame(['192.0.2.0/24'], $policy->options()['client_networks']);
        self::assertSame(['errors' => [], 'warnings' => []], $policy->checkCidrMap((string)file_get_contents($file), $this->assignments('192.0.2.10')));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidMapProvider')]
    public function testInvalidMapIsAConfigurationError(string $content, string $message): void
    {
        $file = $this->mapFile($content);
        self::assertThrows(InvalidArgumentException::class, $message, function () use ($file) {
            new SmtpClientPolicy(['client_networks_map' => $file]);
        });
    }

    public static function invalidMapProvider(): array
    {
        $class = "\tpfa_smtp_client_policy";
        return [
            'no entry for the class' => ["192.0.2.0/24\tOK\n", 'contains no entry for pfa_smtp_client_policy'],
            'empty file' => ['', 'contains no entry for pfa_smtp_client_policy'],
            'host bits' => ["192.0.2.1/24$class\n", 'has host bits set'],
            'if block' => ["if 192.0.2.0/24\n192.0.2.0/24$class\nendif\n", 'if/endif blocks are not evaluated'],
            'pattern not understood' => ["0.0.0.0/0\tOK\n192.0.2.0/24$class\n", "'0.0.0.0/0' is not evaluated"],
            'continuation line' => ["192.0.2.0/24$class\n  more\n", 'continuation lines are not evaluated'],
        ];
    }

    public function testMissingMapFileIsReported(): void
    {
        self::assertThrows(RuntimeException::class, '/nonexistent/smtp_client_networks.cidr does not exist', function () {
            SmtpClientPolicy::readCidrMapFile('/nonexistent/smtp_client_networks.cidr', '');
        });
        self::assertThrows(RuntimeException::class, 'does not exist', function () {
            new SmtpClientPolicy(['client_networks_map' => '/nonexistent/smtp_client_networks.cidr']);
        });
    }

    public function testUnreadableMapFileNamesUserAndMode(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('root can read every file');
        }
        $file = $this->mapFile("192.0.2.0/24\tpfa_smtp_client_policy\n");
        chmod($file, 0);
        self::assertThrows(RuntimeException::class, 'mode 0000', function () use ($file) {
            SmtpClientPolicy::readCidrMapFile($file, '');
        });
    }

    public function testMapFileOutsideOpenBasedirIsReported(): void
    {
        $sep = PATH_SEPARATOR;
        self::assertThrows(
            RuntimeException::class,
            "/etc/postfix/smtp_client_networks.cidr is outside open_basedir (/srv/postfixadmin/$sep/tmp/) - add it to open_basedir",
            function () use ($sep) {
                SmtpClientPolicy::readCidrMapFile('/etc/postfix/smtp_client_networks.cidr', "/srv/postfixadmin/$sep/tmp/");
            }
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('openBasedirProvider')]
    public function testOpenBasedirRule(string $openBasedir, bool $allowed): void
    {
        $error = SmtpClientPolicy::openBasedirError('/etc/postfix/smtp_client_networks.cidr', $openBasedir);
        self::assertSame($allowed, $error === null, (string)$error);
    }

    public static function openBasedirProvider(): array
    {
        $sep = PATH_SEPARATOR;
        return [
            'not set' => ['', true],
            'other directories' => ["/srv/postfixadmin/$sep/tmp/", false],
            'the file itself' => ["/srv/postfixadmin/$sep/etc/postfix/smtp_client_networks.cidr", true],
            'the directory' => ['/etc/postfix/', true],
            'similar directory name' => ['/etc/postfix-old/', false],
            'empty entries ignored' => ["$sep/tmp/$sep", false],
        ];
    }

    public function testAddressWarningsAgainstTheMap(): void
    {
        $file = $this->mapFile("192.0.2.250\tDUNNO\n192.0.2.0/24\tpfa_smtp_client_policy\n192.0.2.251\tREJECT\n198.51.100.0/24\tOK\n");
        $w = SmtpClientPolicy::addressWarnings(
            '192.0.2.10, 192.0.2.250, 192.0.2.0/24, 198.51.100.7, 203.0.113.5, 192.0.2.251',
            ['client_networks_map' => $file]
        );
        self::assertNull($w['failed']);
        self::assertSame([
            '192.0.2.250 (matched first by line 1 (192.0.2.250 DUNNO))',
            '198.51.100.7 (matched first by line 4 (198.51.100.0/24 OK))',
            "203.0.113.5 (not in $file)",
        ], $w['not_checked']);
        self::assertSame(['192.0.2.0/24 (line 1: 192.0.2.250 DUNNO)'], $w['partly']);
    }

    public function testAddressWarningsPartlyCoveredNetwork(): void
    {
        $file = $this->mapFile("192.0.2.0/25\tpfa_smtp_client_policy\n");
        $w = SmtpClientPolicy::addressWarnings('192.0.2.0/24', ['client_networks_map' => $file]);
        self::assertSame([], $w['not_checked']);
        self::assertSame(['192.0.2.0/24 (only partly covered (line 1: 192.0.2.0/25))'], $w['partly']);
    }

    public function testAddressWarningsWithClientNetworks(): void
    {
        $w = SmtpClientPolicy::addressWarnings('192.0.2.10, 198.51.100.1, 2001:db8::10', ['client_networks' => ['192.0.2.0/24', '2001:db8::/64']]);
        self::assertSame(['not_checked' => ['198.51.100.1 (outside client_networks)'], 'partly' => [], 'failed' => null], $w);
    }

    public function testAddressWarningsReportConfigurationProblems(): void
    {
        self::assertStringContainsString('requires client_networks', (string)SmtpClientPolicy::addressWarnings('192.0.2.10', [])['failed']);
        self::assertStringContainsString('does not exist', (string)SmtpClientPolicy::addressWarnings('192.0.2.10', ['client_networks_map' => '/nonexistent/x.cidr'])['failed']);
        self::assertSame(['not_checked' => [], 'partly' => [], 'failed' => null], SmtpClientPolicy::addressWarnings('', []), 'nothing to check');
    }

    private function assignments(string ...$addresses): array
    {
        $result = [];
        foreach ($addresses as $address) {
            $result[] = ['network' => (array)SmtpClientIpList::parse($address), 'domain' => 'example.com', 'active' => true];
        }
        return $result;
    }

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
