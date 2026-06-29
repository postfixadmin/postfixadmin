<?php

class BroadcastQueueTest extends \PHPUnit\Framework\TestCase
{
    private array $domains = [];

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = uniqid('', true);
        $this->domains = [
            "broadcast-a-$suffix.test",
            "broadcast-b-$suffix.test",
        ];

        $this->cleanBroadcastTables();
        db_query('DELETE FROM alias');
        db_query('DELETE FROM mailbox');
        db_query('DELETE FROM domain');
    }

    protected function tearDown(): void
    {
        $this->cleanBroadcastTables();
        db_query('DELETE FROM alias');
        db_query('DELETE FROM mailbox');
        db_query('DELETE FROM domain');

        parent::tearDown();
    }

    public function testBuildRecipientsIncludesActiveMailboxesAndAliases(): void
    {
        $this->createDomain($this->domains[0]);
        $this->createMailbox("one@{$this->domains[0]}", $this->domains[0]);
        $this->createMailbox("inactive@{$this->domains[0]}", $this->domains[0], false);
        $this->createAlias("alias@{$this->domains[0]}", $this->domains[0], "alias-target@example.net,one@{$this->domains[0]}");

        $recipients = BroadcastQueue::buildRecipients([$this->domains[0]], false);

        $this->assertSame(["alias-target@example.net", "one@{$this->domains[0]}"], $recipients);
        $this->assertSame(["one@{$this->domains[0]}"], BroadcastQueue::buildRecipients([$this->domains[0]], true));
    }

    public function testCreateJobBusyDomainsAndDryRunProcessing(): void
    {
        $this->createDomain($this->domains[0]);
        $recipients = ["one@{$this->domains[0]}", "two@{$this->domains[0]}"];

        $jobId = BroadcastQueue::createJob(
            'admin@example.test',
            'noreply@example.test',
            'PostfixAdmin',
            'Test subject',
            'Test body',
            [$this->domains[0]],
            true,
            $recipients
        );

        $this->assertGreaterThan(0, $jobId);
        $this->assertSame([$this->domains[0]], BroadcastQueue::getBusyDomains($this->domains));

        $job = BroadcastQueue::getJob($jobId);
        $this->assertSame('pending', $job['status']);
        $this->assertSame(2, (int)$job['total_count']);
        $this->assertSame([$this->domains[0]], BroadcastQueue::getJobDomains($jobId));

        $result = BroadcastQueue::processNext(10, true);
        $this->assertSame($jobId, $result['job_id']);
        $this->assertSame(2, $result['processed']);
        $this->assertSame(2, $result['sent']);
        $this->assertSame('finished', $result['status']);

        $job = BroadcastQueue::getJob($jobId);
        $this->assertSame('finished', $job['status']);
        $this->assertSame(2, (int)$job['sent_count']);
        $this->assertSame([], BroadcastQueue::getBusyDomains($this->domains));

        $statuses = array_column(BroadcastQueue::getRecipients($jobId), 'status');
        $this->assertSame(['sent', 'sent'], $statuses);
    }

    public function testCancelAndResetInactiveJobs(): void
    {
        $this->createDomain($this->domains[0]);
        $jobId = BroadcastQueue::createJob(
            'admin@example.test',
            'noreply@example.test',
            'PostfixAdmin',
            'Cancel subject',
            'Cancel body',
            [$this->domains[0]],
            true,
            ["one@{$this->domains[0]}"]
        );

        BroadcastQueue::requestCancel($jobId);
        $this->assertSame('cancelling', BroadcastQueue::getJob($jobId)['status']);

        $result = BroadcastQueue::processNext(10, true);
        $this->assertSame('cancelled', $result['status']);
        $this->assertSame('cancelled', BroadcastQueue::getJob($jobId)['status']);
        $this->assertSame('cancelled', BroadcastQueue::getRecipients($jobId)[0]['status']);

        BroadcastQueue::resetInactive();
        $this->assertSame([], BroadcastQueue::getJobs());
        $this->assertSame([], BroadcastQueue::getRecipients($jobId));
        $this->assertSame([], BroadcastQueue::getJobDomains($jobId));
    }

    private function createDomain(string $domain): void
    {
        db_insert('domain', [
            'domain' => $domain,
            'description' => 'broadcast test',
            'aliases' => 10,
            'mailboxes' => 10,
            'transport' => 'virtual',
            'active' => 1,
        ]);
    }

    private function createMailbox(string $username, string $domain, bool $active = true): void
    {
        db_insert('mailbox', [
            'username' => $username,
            'password' => 'test',
            'name' => 'Broadcast Test',
            'maildir' => $username . '/',
            'local_part' => substr($username, 0, strpos($username, '@')),
            'domain' => $domain,
            'active' => $active ? 1 : 0,
        ]);
    }

    private function createAlias(string $address, string $domain, string $goto, bool $active = true): void
    {
        db_insert('alias', [
            'address' => $address,
            'goto' => $goto,
            'domain' => $domain,
            'active' => $active ? 1 : 0,
        ]);
    }

    private function cleanBroadcastTables(): void
    {
        db_query('DELETE FROM broadcast_recipient');
        db_query('DELETE FROM broadcast_job_domain');
        db_query('DELETE FROM broadcast_job');
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
