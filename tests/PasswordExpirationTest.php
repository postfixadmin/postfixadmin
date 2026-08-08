<?php

class PasswordExpirationTest extends \PHPUnit\Framework\TestCase
{
    private $domain;

    protected function setUp(): void
    {
        $this->domain = 'password-expiry-helper-' . uniqid() . '.example';
        db_insert(
            'domain',
            ['domain' => $this->domain, 'description' => 'test', 'transport' => '', 'password_expiry' => 0]
        );
        Config::write('password_expiration', 'YES');
    }

    protected function tearDown(): void
    {
        Config::write('password_expiration', 'YES');
        db_delete('domain', 'domain', $this->domain);
    }

    public function testZeroMeansNoExpiration(): void
    {
        $this->assertSame(PASSWORD_EXPIRATION_NEVER, get_mailbox_password_expiry($this->domain, 0));
    }

    public function testPositiveValueProducesExpectedDate(): void
    {
        db_update('domain', 'domain', $this->domain, ['password_expiry' => 30], []);
        $base = strtotime('2026-01-01 00:00');

        $this->assertSame(
            date('Y-m-d H:i', strtotime('+30 days', $base)),
            get_mailbox_password_expiry($this->domain, $base)
        );
    }

    public function testInvalidStoredValuesFailSafe(): void
    {
        db_update('domain', 'domain', $this->domain, ['password_expiry' => -1], []);
        $this->assertSame(PASSWORD_EXPIRATION_NEVER, get_mailbox_password_expiry($this->domain, 0));

        db_update(
            'domain',
            'domain',
            $this->domain,
            ['password_expiry' => PASSWORD_EXPIRATION_MAX_DAYS + 1],
            []
        );
        $this->assertSame(PASSWORD_EXPIRATION_NEVER, get_mailbox_password_expiry($this->domain, 0));
    }

    public function testDisabledFeatureUsesSameFallbackForEveryChangePath(): void
    {
        Config::write('password_expiration', 'NO');
        $base = strtotime('2026-01-01 00:00');

        $this->assertSame(
            date('Y-m-d H:i', strtotime('+365 days', $base)),
            get_mailbox_password_expiry($this->domain, $base)
        );
    }

    public function testNoExpirationDisplayUsesPolicyAndStoredSentinel(): void
    {
        $this->assertTrue(mailbox_password_expiration_is_never('2026-12-31 00:00:00', 0));
        $this->assertTrue(mailbox_password_expiration_is_never(PASSWORD_EXPIRATION_NEVER, 30));
        $this->assertTrue(mailbox_password_expiration_is_never('2026-12-31 00:00:00', -1));
        $this->assertFalse(mailbox_password_expiration_is_never('2026-12-31 00:00:00', 30));
    }
}
