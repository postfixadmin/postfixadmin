<?php

class DatabaseUpgradeTest extends \PHPUnit\Framework\TestCase
{
    private $orphan;
    private $quota2;
    private $vacation;
    private $vacation_notification;

    public function setUp(): void
    {
        if (!db_mysql()) {
            $this->markTestSkipped('MySQL/MariaDB-specific migration test');
        }

        $this->orphan = 'upgrade-retry-' . uniqid() . '@example.invalid';
        $this->quota2 = table_by_key('quota2');
        $this->vacation = table_by_key('vacation');
        $this->vacation_notification = table_by_key('vacation_notification');

        $this->removeOrphan();
        $this->ensureForeignKeyExists();
    }

    public function tearDown(): void
    {
        if (!db_mysql()) {
            return;
        }

        $this->removeOrphan();
        $this->ensureForeignKeyExists();
        db_query("ALTER TABLE $this->quota2 MODIFY username varchar(100) COLLATE latin1_general_ci NOT NULL");
    }

    public function testReconcilesSchemaWhenForeignKeyExists(): void
    {
        db_query("ALTER TABLE $this->quota2 MODIFY username varchar(255) COLLATE latin1_general_ci NOT NULL");

        upgrade_1855_mysql();

        $this->assertTrue(
            _mysql_foreign_key_exists($this->vacation_notification, 'vacation_notification_pkey')
        );
        $this->assertSame(100, $this->quota2UsernameLength());
    }

    public function testLeavesAlreadyCorrectSchemaUntouched(): void
    {
        $before = db_query_one("SHOW CREATE TABLE $this->vacation_notification");

        upgrade_1855_mysql();

        $this->assertSame($before, db_query_one("SHOW CREATE TABLE $this->vacation_notification"));
        $this->assertSame(100, $this->quota2UsernameLength());
    }

    public function testRetriesReconciliationAfterForeignKeyFailure(): void
    {
        db_query("ALTER TABLE $this->vacation_notification DROP FOREIGN KEY vacation_notification_pkey");
        db_query("ALTER TABLE $this->quota2 MODIFY username varchar(255) COLLATE latin1_general_ci NOT NULL");
        db_query(
            "INSERT INTO $this->vacation_notification (on_vacation, notified) VALUES (:on_vacation, :notified)",
            [
                'on_vacation' => $this->orphan,
                'notified' => $this->orphan,
            ]
        );

        try {
            upgrade_1855_mysql();
            $this->fail('The orphaned notification should prevent the foreign key from being restored');
        } catch (Exception $e) {
            $this->assertStringContainsString('vacation_notification_pkey', $e->getMessage());
        }

        $this->assertFalse(
            _mysql_foreign_key_exists($this->vacation_notification, 'vacation_notification_pkey')
        );
        $this->assertSame(255, $this->quota2UsernameLength());

        $this->removeOrphan();
        upgrade_1855_mysql();

        $this->assertTrue(
            _mysql_foreign_key_exists($this->vacation_notification, 'vacation_notification_pkey')
        );
        $this->assertSame(100, $this->quota2UsernameLength());
    }

    private function ensureForeignKeyExists(): void
    {
        if (!_mysql_foreign_key_exists($this->vacation_notification, 'vacation_notification_pkey')) {
            db_query(
                "ALTER TABLE $this->vacation_notification
                ADD CONSTRAINT vacation_notification_pkey
                FOREIGN KEY (`on_vacation`) REFERENCES $this->vacation(email) ON DELETE CASCADE"
            );
        }
    }

    private function removeOrphan(): void
    {
        db_query(
            "DELETE FROM $this->vacation_notification WHERE on_vacation = :on_vacation",
            ['on_vacation' => $this->orphan]
        );
    }

    private function quota2UsernameLength(): int
    {
        $column = db_query_one(
            "SELECT CHARACTER_MAXIMUM_LENGTH AS character_maximum_length
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND COLUMN_NAME = 'username'",
            ['table' => trim($this->quota2, '`')]
        );

        return (int) (array_values($column ?? [])[0] ?? 0);
    }
}
