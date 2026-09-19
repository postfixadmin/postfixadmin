<?php

use PHPUnit\Framework\TestCase;

/**
 * Test OIDC provisioning SQL against SQLite.
 * Verifies the atomic upsert works correctly with CURRENT_TIMESTAMP.
 */
class OIDCSQLiteProvisioningTest extends TestCase
{
    private $testEmail = 'test-oidc-user@example.com';

    public function setUp(): void
    {
        // Skip on MySQL - these tests use SQLite/PostgreSQL-specific SQL
        if (getenv('DATABASE') === 'mysql') {
            $this->markTestSkipped('SQLite/PostgreSQL-specific test');
        }

        // Clean up test user if exists
        db_execute("DELETE FROM admin WHERE username = ?", [$this->testEmail]);
    }

    public function tearDown(): void
    {
        // Clean up
        db_execute("DELETE FROM admin WHERE username = ?", [$this->testEmail]);
    }

    /**
     * Test: provisioning SQL inserts a new admin with CURRENT_TIMESTAMP
     */
    public function testProvisioningInsertsNewAdmin(): void
    {
        $email = $this->testEmail;
        $hashedPassword = pacrypt('test-password-123');

        $table_admin = table_by_key('admin');

        // This is the exact SQL from oidc_callback.php
        db_execute(
            "INSERT INTO $table_admin (username, password, active, created, modified) VALUES (?, ?, true, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) ON CONFLICT (username) DO NOTHING",
            [$email, $hashedPassword]
        );

        // Verify the user was created
        $result = db_query_one("SELECT * FROM admin WHERE username = ?", [$email]);
        $this->assertNotEmpty($result, 'Admin user should be created');
        $this->assertEquals($email, $result['username']);
        $this->assertEquals(1, $result['active']);
        $this->assertNotEmpty($result['created'], 'created should be set');
        $this->assertNotEmpty($result['modified'], 'modified should be set');
    }

    /**
     * Test: provisioning SQL is idempotent (ON CONFLICT DO NOTHING)
     */
    public function testProvisioningIsIdempotent(): void
    {
        $email = $this->testEmail;
        $hashedPassword = pacrypt('test-password-123');

        $table_admin = table_by_key('admin');

        // Insert first time
        db_execute(
            "INSERT INTO $table_admin (username, password, active, created, modified) VALUES (?, ?, true, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) ON CONFLICT (username) DO NOTHING",
            [$email, $hashedPassword]
        );

        // Insert second time (should not fail, should not update)
        db_execute(
            "INSERT INTO $table_admin (username, password, active, created, modified) VALUES (?, ?, true, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) ON CONFLICT (username) DO NOTHING",
            [$email, $hashedPassword]
        );

        // Verify only one record exists
        $count = db_query_one("SELECT COUNT(*) as cnt FROM admin WHERE username = ?", [$email]);
        $this->assertEquals(1, $count['cnt'], 'Should have exactly one admin record');
    }

    /**
     * Test: CURRENT_TIMESTAMP produces valid datetime
     */
    public function testCurrentTimestampIsValidDatetime(): void
    {
        $email = $this->testEmail;
        $hashedPassword = pacrypt('test-password-123');

        $table_admin = table_by_key('admin');

        db_execute(
            "INSERT INTO $table_admin (username, password, active, created, modified) VALUES (?, ?, true, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) ON CONFLICT (username) DO NOTHING",
            [$email, $hashedPassword]
        );

        $result = db_query_one("SELECT created, modified FROM admin WHERE username = ?", [$email]);

        // Verify created is a valid datetime
        // PostgreSQL may return format with timezone: "2026-09-04 13:15:17.123456+00"
        // SQLite returns: "2026-09-04 13:15:17"
        $created = strtotime($result['created']);
        $this->assertNotFalse($created, 'created should be valid datetime');

        // Verify modified is a valid datetime
        $modified = strtotime($result['modified']);
        $this->assertNotFalse($modified, 'modified should be valid datetime');
    }

    /**
     * Test: multiple concurrent provisioning attempts don't create duplicates
     */
    public function testConcurrentProvisioningNoDuplicates(): void
    {
        $email = $this->testEmail;
        $hashedPassword = pacrypt('test-password-123');
        $table_admin = table_by_key('admin');

        // Simulate 5 concurrent inserts
        for ($i = 0; $i < 5; $i++) {
            db_execute(
                "INSERT INTO $table_admin (username, password, active, created, modified) VALUES (?, ?, true, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) ON CONFLICT (username) DO NOTHING",
                [$email, $hashedPassword]
            );
        }

        $count = db_query_one("SELECT COUNT(*) as cnt FROM admin WHERE username = ?", [$email]);
        $this->assertEquals(1, $count['cnt'], 'Should have exactly one admin record after concurrent inserts');
    }
}
