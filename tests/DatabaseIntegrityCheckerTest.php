<?php

class DatabaseIntegrityCheckerTest extends \PHPUnit\Framework\TestCase
{
    private string $orphan_mailbox;
    private string $valid_mailbox;
    private string $domain;
    private string $admin;
    private bool $created_all_domain = false;

    public function setUp(): void
    {
        $suffix = uniqid();
        $this->domain = "integrity-$suffix.example";
        $this->valid_mailbox = "valid@$this->domain";
        $this->orphan_mailbox = "orphan-$suffix@example.invalid";
        $this->admin = "integrity-admin-$suffix";

        db_insert('domain', [
            'domain' => $this->domain,
            'description' => 'integrity test',
            'transport' => '',
        ]);
        db_insert('mailbox', [
            'username' => $this->valid_mailbox,
            'password' => 'test',
            'name' => 'Integrity test',
            'maildir' => $this->domain . '/valid/',
            'local_part' => 'valid',
            'domain' => $this->domain,
        ]);
    }

    public function tearDown(): void
    {
        db_delete('quota2', 'username', $this->valid_mailbox);
        db_delete('quota2', 'username', $this->orphan_mailbox);
        db_delete('domain_admins', 'username', $this->admin);
        if ($this->created_all_domain) {
            db_delete('domain', 'domain', 'ALL');
        }
        db_delete('mailbox', 'username', $this->valid_mailbox);
        db_delete('domain', 'domain', $this->domain);
    }

    public function testReportsOrphanedRowsWithoutChangingData(): void
    {
        db_insert('quota2', [
            'username' => $this->valid_mailbox,
            'bytes' => 10,
            'messages' => 1,
        ], []);
        db_insert('quota2', [
            'username' => $this->orphan_mailbox,
            'bytes' => 20,
            'messages' => 2,
        ], []);

        $results = (new DatabaseIntegrityChecker())->check(100);
        $quota2 = $this->findResult($results, 'quota2', 'username');

        $this->assertGreaterThanOrEqual(1, $quota2['orphan_count']);
        $this->assertContains($this->orphan_mailbox, $quota2['sample_values']);
        $this->assertNotContains($this->valid_mailbox, $quota2['sample_values']);

        $row = db_query_one(
            'SELECT bytes, messages FROM ' . table_by_key('quota2') . ' WHERE username = ?',
            [$this->orphan_mailbox]
        );
        $this->assertSame(20, intval($row['bytes']));
        $this->assertSame(2, intval($row['messages']));
    }

    public function testIgnoresAllAsSpecialDomainAssignment(): void
    {
        $all_domain = db_query_one(
            'SELECT domain FROM ' . table_by_key('domain') . ' WHERE domain = ?',
            ['ALL']
        );
        if (db_pgsql() && $all_domain === null) {
            db_insert('domain', [
                'domain' => 'ALL',
                'description' => 'special domain assignment',
                'transport' => '',
            ]);
            $this->created_all_domain = true;
        }

        db_insert('domain_admins', [
            'username' => $this->admin,
            'domain' => 'ALL',
            'active' => 1,
        ], ['created']);

        $results = (new DatabaseIntegrityChecker())->check(100);
        $domains = $this->findResult($results, 'domain_admins', 'domain');
        $admins = $this->findResult($results, 'domain_admins', 'username');

        $this->assertNotContains('ALL', $domains['sample_values']);
        $this->assertContains($this->admin, $admins['sample_values']);
    }

    public function testChecksAllRelationsProposedByIssue972(): void
    {
        $results = (new DatabaseIntegrityChecker())->check();
        $relations = array_map(function (array $result): string {
            return $result['child_table'] . '.' . $result['child_column'] .
                '->' . $result['parent_table'] . '.' . $result['parent_column'];
        }, $results);

        $this->assertSame([
            'fetchmail.mailbox->mailbox.username',
            'quota.username->mailbox.username',
            'quota2.username->mailbox.username',
            'vacation.email->mailbox.username',
            'domain_admins.domain->domain.domain',
            'domain_admins.username->admin.username',
        ], $relations);
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @return array<string, mixed>
     */
    private function findResult(array $results, string $table, string $column): array
    {
        foreach ($results as $result) {
            if ($result['child_table'] === $table && $result['child_column'] === $column) {
                return $result;
            }
        }

        $this->fail("Missing integrity result for $table.$column");
    }
}
