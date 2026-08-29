<?php

use PHPUnit\Framework\TestCase;

class SyncRoundcubemailTotpScriptTest extends TestCase
{
    private string $script;

    protected function setUp(): void
    {
        $this->script = file_get_contents(__DIR__ . '/../scripts/examples/sync-roundcubemail-totp.php');
    }

    public function testUpdateIsLimitedToTheSelectedUser(): void
    {
        $this->assertStringContainsString('UPDATE users SET preferences=? WHERE user_id=?', $this->script);
    }

    public function testSerializedPreferencesCannotInstantiateClasses(): void
    {
        $this->assertStringContainsString("['allowed_classes' => false]", $this->script);
    }
}
