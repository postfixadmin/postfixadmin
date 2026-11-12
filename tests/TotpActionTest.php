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
        $this->assertTrue($enabled['totp_action']);
        $this->assertArrayNotHasKey('totp_secret', $enabled);
        $this->assertFalse($plain['totp_action']);
    }
    #[DataProvider('handlers')]
    public function testInactiveAndEmptySecretsDoNotOfferReset(string $handlerClass): void
    {
        $table = $handlerClass === AdminHandler::class ? 'admin' : 'mailbox';
        $handler = new $handlerClass();
        $username = 'enabled@' . self::DOMAIN;
        foreach ([null, '', '0'] as $secret) {
            db_update($table, 'username', $username, ['totp_secret' => $secret]);
            $this->assertTrue($handler->getList(['username' => $username]));
            $this->assertFalse($handler->result()[$username]['totp_action']);
        }
        db_update($table, 'username', $username, ['totp_secret' => 'JBSWY3DPEHPK3PXP', 'active' => false]);
        $this->assertTrue($handler->getList(['username' => $username]));
        $this->assertFalse($handler->result()[$username]['totp_action']);
    }

    #[DataProvider('handlers')]
    public function testDisabledTotpOmitsAction(string $handlerClass): void
    {
        global $CONF;
        Config::write('totp', 'NO');
        $CONF['totp'] = 'NO';
        $handler = new $handlerClass();
        $username = 'enabled@' . self::DOMAIN;
        $this->assertTrue($handler->getList(['username' => $username]));
        $this->assertArrayNotHasKey('totp_action', $handler->result()[$username]);
    }

    #[DataProvider('handlers')]
    public function testEditTemplateRendersBooleanAction(string $handlerClass): void
    {
        $smarty = new \Smarty\Smarty();
        $smarty->setCompileDir(sys_get_temp_dir());
        $smarty->setTemplateDir(__DIR__ . '/../templates');
        $smarty->registerPlugin('function', 'CSRF_Token', static fn (): string => '<input type="hidden" name="token" value="fixture">');
        $smarty->assign([
            'table' => $handlerClass === AdminHandler::class ? 'admin' : 'mailbox',
            'formtitle' => 'Fixture', 'submitbutton' => 'Save', 'cancel_url' => 'list.php',
            'fielderror' => ['totp_action' => ''],
            'struct' => ['totp_action' => PFAHandler::pacol(0, 1, 0, 'totp', '', '')],
        ]);
        foreach (['en', 'es'] as $language) {
            $PALANG = [];
            require __DIR__ . '/../languages/' . $language . '.lang';
            $PALANG['pUsersMenu_reset_totp'] = '<Reset & confirm>';
            $PALANG['pUsersMenu_no_totp_set'] = '<Not configured & safe>';
            $smarty->assign('PALANG', PFASmarty::getInstance()->sanitise($PALANG));
            foreach ([true, false] as $hasTotp) {
                $smarty->assign('value_totp_action', $hasTotp);
                $html = $smarty->fetch('editform.tpl');
                $this->assertStringContainsString('name="token"', $html);
                if ($hasTotp) {
                    $this->assertStringContainsString('name="reset_totp" value="1"', $html);
                    $this->assertStringContainsString('&lt;Reset &amp; confirm&gt;', $html);
                } else {
                    $this->assertStringNotContainsString('name="reset_totp"', $html);
                    $this->assertStringContainsString('&lt;Not configured &amp; safe&gt;', $html);
                }
                $this->assertStringNotContainsString('<Reset', $html);
            }
        }
    }

}
