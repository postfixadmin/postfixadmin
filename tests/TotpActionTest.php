<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TotpActionTest extends TestCase
{
    private const DOMAIN = 'totp-actions.example';
    private mixed $previousTotp;
    private mixed $previousGlobalTotp;
    private array $previousSession;

    protected function setUp(): void
    {
        global $CONF;
        parent::setUp();
        $this->previousTotp = Config::read('totp');
        $this->previousGlobalTotp = $CONF['totp'];
        $this->previousSession = $_SESSION ?? [];
        Config::write('totp', 'YES');
        $CONF['totp'] = 'YES';
        $_SESSION = ['sessid' => ['roles' => ['admin', 'global-admin']]];
        db_insert('domain', ['domain' => self::DOMAIN, 'description' => 'TOTP action fixture',
            'transport' => 'virtual', 'active' => true, 'backupmx' => false], ['created', 'modified'], true);
        foreach (['enabled' => 'JBSWY3DPEHPK3PXP', 'plain' => ''] as $localPart => $secret) {
            $account = ['username' => $localPart . '@' . self::DOMAIN, 'password' => 'unused-fixture',
                'active' => true, 'totp_secret' => $secret];
            db_insert('admin', $account + ['superadmin' => false], ['created', 'modified'], true);
            db_insert('mailbox', $account + ['name' => $localPart, 'domain' => self::DOMAIN,
                'local_part' => $localPart, 'maildir' => self::DOMAIN . '/' . $localPart . '/'], ['created', 'modified'], true);
        }
    }

    protected function tearDown(): void
    {
        global $CONF;
        db_delete('mailbox', 'domain', self::DOMAIN);
        foreach (['enabled', 'plain'] as $localPart) {
            db_delete('admin', 'username', $localPart . '@' . self::DOMAIN);
        }
        db_delete('domain', 'domain', self::DOMAIN);
        Config::write('totp', $this->previousTotp);
        $CONF['totp'] = $this->previousGlobalTotp;
        $_SESSION = $this->previousSession;
        parent::tearDown();
    }

    public static function handlers(): array
    {
        return ['administrators' => [AdminHandler::class], 'mailboxes' => [MailboxHandler::class]];
    }

    #[DataProvider('handlers')]
    public function testEmptyResultsWithTotpEnabled(string $handlerClass): void
    {
        $handler = new $handlerClass();
        $this->assertTrue($handler->getList(['username' => 'missing@' . self::DOMAIN]));
        $this->assertSame([], $handler->result());
    }

    #[DataProvider('handlers')]
    public function testTotpActionBelongsToEachReturnedAccount(string $handlerClass): void
    {
        $handler = new $handlerClass();
        $this->assertTrue($handler->getList(['username' => '%@' . self::DOMAIN], ['username' => 'LIKE']));
        $rows = $handler->result();
        $this->assertCount(2, $rows);
        $enabled = $rows['enabled@' . self::DOMAIN];
        $plain = $rows['plain@' . self::DOMAIN];
        $this->assertArrayHasKey('totp_action', $enabled);
        $this->assertStringContainsString('name="reset_totp"', $enabled['totp_action']);
        $this->assertSame(htmlspecialchars(Config::lang('pUsersMenu_no_totp_set'), ENT_QUOTES, 'UTF-8'), $plain['totp_action']);
    }
}
