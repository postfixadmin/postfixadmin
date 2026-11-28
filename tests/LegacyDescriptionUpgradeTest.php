<?php

/**
 * upgrade_1859() decodes HTML-escaped legacy descriptions, which Smarty auto-escaping
 * would otherwise display literally (e.g. "R&amp;amp;D").
 */
class LegacyDescriptionUpgradeTest extends \PHPUnit\Framework\TestCase
{
    private const USERNAME = 'legacy-desc@upgrade-1859.example.com';

    protected function tearDown(): void
    {
        foreach (['mailbox_app_password', 'totp_exception_address'] as $table) {
            db_execute('DELETE FROM ' . table_by_key($table) . ' WHERE username = :username', ['username' => self::USERNAME]);
        }
    }

    public function testDecodesLegacyEscapedDescriptionsOnly(): void
    {
        $cases = [
            'Legacy &lt;tag&gt; &amp; text' => 'Legacy <tag> & text',
            'O&#039;Brien &quot;laptop&quot;' => 'O\'Brien "laptop"',
            'Plain R&D text' => 'Plain R&D text',
            '<b>raw & unescaped</b>' => '<b>raw & unescaped</b>',
        ];
        $i = 0;
        foreach (array_keys($cases) as $stored) {
            db_insert('mailbox_app_password', ['username' => self::USERNAME, 'description' => $stored, 'password_hash' => 'x'], []);
            db_insert('totp_exception_address', ['username' => self::USERNAME, 'description' => $stored, 'ip' => '198.51.100.' . (++$i)], []);
        }

        upgrade_1859();
        upgrade_1859(); // must be safe to run again

        foreach (['mailbox_app_password', 'totp_exception_address'] as $table) {
            $rows = db_query_all('SELECT description FROM ' . table_by_key($table) . ' WHERE username = :username ORDER BY id', ['username' => self::USERNAME]);
            $this->assertSame(array_values($cases), array_column($rows, 'description'), $table);
        }
    }
}
