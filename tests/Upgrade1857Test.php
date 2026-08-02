<?php

use PHPUnit\Framework\TestCase;

class Upgrade1857Test extends TestCase
{
    private const INDEXES = [
        'idx_broadcast_job_status' => ['broadcast_job', 'status'],
        'idx_broadcast_job_domain' => ['broadcast_job_domain', 'domain, job_id'],
        'idx_broadcast_recipient_job_status' => ['broadcast_recipient', 'job_id, status'],
    ];

    protected function tearDown(): void
    {
        $this->runIndexUpgrade();
    }

    public function testIndexUpgradeRecoversFromPartialState(): void
    {
        foreach (self::INDEXES as $index => [$tableKey]) {
            $this->dropIndex($tableKey, $index);
        }

        [$tableKey, $columns] = self::INDEXES['idx_broadcast_job_status'];
        $table = table_by_key($tableKey);
        db_query("CREATE INDEX idx_broadcast_job_status ON $table ($columns)");

        $this->runIndexUpgrade();

        foreach (self::INDEXES as $index => [$indexTableKey]) {
            self::assertTrue($this->indexExists($indexTableKey, $index));
        }
    }

    public function testIndexUpgradeCanBeRetried(): void
    {
        $this->runIndexUpgrade();
        $this->runIndexUpgrade();

        foreach (self::INDEXES as $index => [$tableKey]) {
            self::assertTrue($this->indexExists($tableKey, $index));
        }
    }

    private function runIndexUpgrade(): void
    {
        global $CONF;

        if ($CONF['database_type'] === 'pgsql') {
            upgrade_1857_pgsql();
        } elseif (db_sqlite()) {
            upgrade_1857_sqlite();
        } else {
            upgrade_1857_mysql();
        }
    }

    private function indexExists(string $tableKey, string $index): bool
    {
        global $CONF;

        if ($CONF['database_type'] === 'pgsql') {
            return _pgsql_object_exists($index);
        }

        if (db_sqlite()) {
            $result = db_query_one(
                "SELECT name FROM sqlite_master WHERE type = 'index' AND name = :index",
                ['index' => $index]
            );
            return !empty($result);
        }

        $table = table_by_key($tableKey);
        $result = db_query_one("SHOW INDEX FROM $table WHERE Key_name = '$index'");
        return !empty($result);
    }

    private function dropIndex(string $tableKey, string $index): void
    {
        global $CONF;

        if (!$this->indexExists($tableKey, $index)) {
            return;
        }

        if ($CONF['database_type'] === 'pgsql' || db_sqlite()) {
            db_query("DROP INDEX $index");
            return;
        }

        $table = table_by_key($tableKey);
        db_query("ALTER TABLE $table DROP INDEX $index");
    }
}
