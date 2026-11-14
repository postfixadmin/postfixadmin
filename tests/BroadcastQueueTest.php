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
        BroadcastQueue::setWorkerMode(BroadcastQueue::MODE_LIVE);
    }

    protected function tearDown(): void
    {
        BroadcastQueue::setWorkerMode(BroadcastQueue::MODE_LIVE);
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

        BroadcastQueue::requestCancel($jobId, [], true, '', false);
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

    public function testCancelWithNoBatchRunningFinalisesImmediately(): void
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

        $this->assertTrue(BroadcastQueue::requestCancel($jobId));
        $this->assertSame('cancelled', BroadcastQueue::getJob($jobId)['status']);
        $this->assertSame('cancelled', BroadcastQueue::getRecipients($jobId)[0]['status']);
    }

    public function testCancellationKeepsInterruptedSubmissionUnconfirmed(): void
    {
        $this->createDomain($this->domains[0]);
        $jobId = BroadcastQueue::createJob(
            'admin@example.test',
            'noreply@example.test',
            'PostfixAdmin',
            'Cancel interrupted',
            'Body',
            [$this->domains[0]],
            true,
            ["one@{$this->domains[0]}", "two@{$this->domains[0]}"]
        );
        $recipientTable = table_by_key('broadcast_recipient');
        db_execute(
            "UPDATE $recipientTable SET status = 'sending', smtp_response = 'Message-ID: <test@invalid>' WHERE job_id = :job_id AND recipient = :recipient",
            ['job_id' => $jobId, 'recipient' => "one@{$this->domains[0]}"]
        );

        $this->assertTrue(BroadcastQueue::requestCancel($jobId));
        $this->assertSame('cancelled', BroadcastQueue::getJob($jobId)['status']);
        $this->assertSame(['unknown', 'cancelled'], array_column(BroadcastQueue::getRecipients($jobId), 'status'));
        $this->assertSame(1, BroadcastQueue::getUnknownCount($jobId));
        $unknown = BroadcastQueue::getUnknownRecipients($jobId)[0];
        $this->assertTrue(BroadcastQueue::resolveUnknown($jobId, (int)$unknown['id'], 'retry'));
        $this->assertSame('pending', BroadcastQueue::getJob($jobId)['status']);
        $this->assertSame('finished', BroadcastQueue::processNext(1, true, $jobId)['status']);
    }

    public function testCancelWhileBatchRunningLeavesJobCancelling(): void
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

        $lock = BroadcastQueue::acquireProcessLock();
        $this->assertNotNull($lock);
        try {
            $this->assertTrue(BroadcastQueue::requestCancel($jobId));
            $this->assertSame('cancelling', BroadcastQueue::getJob($jobId)['status']);
        } finally {
            BroadcastQueue::releaseProcessLock($lock);
        }
    }

    public function testInterruptedSubmissionIsReportedWithoutRetryAndJobsStayIndependent(): void
    {
        $this->createDomain($this->domains[0]);
        $this->createDomain($this->domains[1]);
        $first = BroadcastQueue::createJob(
            'admin@example.test',
            'noreply@example.test',
            'PostfixAdmin',
            'First',
            'Body',
            [$this->domains[0]],
            true,
            ["one@{$this->domains[0]}", "two@{$this->domains[0]}"]
        );
        $second = BroadcastQueue::createJob(
            'admin@example.test',
            'noreply@example.test',
            'PostfixAdmin',
            'Second',
            'Body',
            [$this->domains[1]],
            true,
            ["one@{$this->domains[1]}"]
        );

        $recipientTable = table_by_key('broadcast_recipient');
        db_execute(
            "UPDATE $recipientTable SET status = 'sending', smtp_response = 'Message-ID: <test@invalid>' WHERE job_id = :job_id AND recipient = :recipient",
            ['job_id' => $first, 'recipient' => "one@{$this->domains[0]}"]
        );
        BroadcastQueue::recoverInterrupted($first);

        $result = BroadcastQueue::processNext(1, true, $second);
        $this->assertSame($second, $result['job_id']);
        $this->assertSame('finished', $result['status']);

        $result = BroadcastQueue::processNext(1, true, $first);
        $this->assertSame('finished', $result['status']);
        $job = BroadcastQueue::getJob($first);
        $this->assertSame(1, (int)$job['sent_count']);
        $this->assertSame(0, (int)$job['failed_count']);
        $this->assertSame(1, BroadcastQueue::getUnknownCount($first));
        $statuses = array_column(BroadcastQueue::getRecipients($first), 'status');
        $this->assertSame(['unknown', 'sent'], $statuses);
        $this->assertSame('Message-ID: <test@invalid>', BroadcastQueue::getRecipients($first)[0]['smtp_response']);
    }

    public function testResolveUnknownRecipientsIndividually(): void
    {
        $this->createDomain($this->domains[0]);
        $jobId = BroadcastQueue::createJob(
            'admin@example.test',
            'noreply@example.test',
            'PostfixAdmin',
            'Review',
            'Body',
            [$this->domains[0]],
            true,
            ["one@{$this->domains[0]}", "two@{$this->domains[0]}"]
        );
        $recipientTable = table_by_key('broadcast_recipient');
        $jobTable = table_by_key('broadcast_job');
        db_execute("UPDATE $recipientTable SET status = 'unknown', smtp_response = 'Message-ID: <test@invalid>' WHERE job_id = :job_id", ['job_id' => $jobId]);
        db_execute("UPDATE $jobTable SET status = 'finished' WHERE id = :id", ['id' => $jobId]);
        $recipients = BroadcastQueue::getUnknownRecipients($jobId);
        $this->assertCount(2, $recipients);

        $this->assertFalse(BroadcastQueue::resolveUnknown($jobId, (int)$recipients[0]['id'], 'retry', [], false, 'other@example.test'));
        $this->assertTrue(BroadcastQueue::resolveUnknown($jobId, (int)$recipients[0]['id'], 'retry', $this->domains, false, 'admin@example.test'));
        $this->assertSame('pending', BroadcastQueue::getJob($jobId)['status']);
        $this->assertTrue(BroadcastQueue::resolveUnknown($jobId, (int)$recipients[1]['id'], 'discard', $this->domains, false, 'admin@example.test'));
        $this->assertSame(0, BroadcastQueue::getUnknownCount($jobId));
        $this->assertSame(1, (int)BroadcastQueue::getJob($jobId)['cancelled_count']);

        $result = BroadcastQueue::processNext(1, true, $jobId);
        $this->assertSame('finished', $result['status']);
        $this->assertSame(['sent', 'cancelled'], array_column(BroadcastQueue::getRecipients($jobId), 'status'));
    }

    public function testWorkerModeControlsDefaultProcessing(): void
    {
        $this->createDomain($this->domains[0]);
        $jobId = BroadcastQueue::createJob(
            'admin@example.test',
            'noreply@example.test',
            'PostfixAdmin',
            'Mode subject',
            'Mode body',
            [$this->domains[0]],
            true,
            ["one@{$this->domains[0]}"]
        );

        BroadcastQueue::setWorkerMode(BroadcastQueue::MODE_DRY_RUN);
        $this->assertSame(BroadcastQueue::MODE_DRY_RUN, BroadcastQueue::workerMode());

        $result = BroadcastQueue::processNext(10);
        $this->assertSame($jobId, $result['job_id']);
        $this->assertSame('finished', $result['status']);

        $recipients = BroadcastQueue::getRecipients($jobId);
        $this->assertSame('sent', $recipients[0]['status']);
        $this->assertStringStartsWith('dry-run; Message-ID: <broadcast-', $recipients[0]['smtp_response']);

        BroadcastQueue::setWorkerMode(BroadcastQueue::MODE_LIVE);
        $this->assertSame(BroadcastQueue::MODE_LIVE, BroadcastQueue::workerMode());
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
