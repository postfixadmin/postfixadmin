<?php

class MfaStatusTest extends \PHPUnit\Framework\TestCase
{
    private array $savedConfig;

    protected function setUp(): void
    {
        $this->savedConfig = Config::getInstance()->getAll();
        Config::write('totp', 'YES');
        foreach (['mfa-visible.example', 'mfa-hidden.example', 'mfa-empty.example'] as $domain) {
            db_insert('domain', ['domain' => $domain, 'description' => '', 'transport' => 'virtual']);
        }
        db_insert('domain_admins', ['username' => 'mfa-owner', 'domain' => 'mfa-visible.example', 'active' => true], ['created'], true);
        db_insert('domain_admins', ['username' => 'mfa-owner', 'domain' => 'mfa-empty.example', 'active' => true], ['created'], true);
        foreach (['yes' => 'test-secret', 'empty' => '', 'none' => null] as $name => $secret) {
            db_insert('admin', ['username' => $name . '@mfa-visible.example', 'password' => 'unused', 'totp_secret' => $secret, 'active' => false]);
            db_insert('mailbox', ['username' => $name . '@mfa-visible.example', 'local_part' => $name,
                'domain' => 'mfa-visible.example', 'password' => 'unused', 'name' => '', 'maildir' => '',
                'totp_secret' => $secret, 'active' => false]);
        }
        db_insert('mailbox', ['username' => 'yes@mfa-hidden.example', 'local_part' => 'yes',
            'domain' => 'mfa-hidden.example', 'password' => 'unused', 'name' => '', 'maildir' => '', 'totp_secret' => 'hidden-secret']);
    }

    protected function tearDown(): void
    {
        foreach (['mfa-visible.example', 'mfa-hidden.example', 'mfa-empty.example'] as $domain) {
            db_delete('mailbox', 'domain', $domain);
            db_delete('domain_admins', 'domain', $domain);
            db_delete('domain', 'domain', $domain);
        }
        foreach (['yes', 'empty', 'none'] as $name) {
            db_delete('admin', 'username', $name . '@mfa-visible.example');
        }
        foreach ($this->savedConfig as $key => $value) {
            Config::write($key, $value);
        }
        unset($_SESSION['sessid']);
    }

    public function testAdministratorStatusIncludesInactiveAccountsWithoutExposingSecrets(): void
    {
        $handler = new AdminHandler();
        self::assertTrue($handler->getList(''));
        $rows = array_column($handler->result(), null, 'username');
        self::assertSame(1, (int)$rows['yes@mfa-visible.example']['mfa']);
        self::assertSame(0, (int)$rows['empty@mfa-visible.example']['mfa']);
        self::assertSame(0, (int)$rows['none@mfa-visible.example']['mfa']);
        self::assertArrayNotHasKey('totp_secret', $rows['yes@mfa-visible.example']);
        $keys = array_keys($handler->getStruct());
        self::assertSame(array_search('active', $keys) + 1, array_search('mfa', $keys));
    }

    public function testDomainIndicatorRespectsAdministratorScope(): void
    {
        $_SESSION['sessid']['roles'] = ['admin'];
        $handler = new DomainHandler(0, 'mfa-owner');
        self::assertTrue($handler->getList(''));
        $rows = array_column($handler->result(), null, 'domain');
        self::assertSame(1, (int)$rows['mfa-visible.example']['mfa']);
        self::assertSame(0, (int)$rows['mfa-empty.example']['mfa']);
        self::assertArrayNotHasKey('mfa-hidden.example', $rows);
    }

    public function testResetActionDefaultsToEnabledAndCanBeDisabled(): void
    {
        foreach ([AdminHandler::class, MailboxHandler::class] as $class) {
            self::assertArrayHasKey('totp_action', (new $class())->getStruct());
            Config::write('totp_allow_admin_reset', 'NO');
            self::assertArrayNotHasKey('totp_action', (new $class())->getStruct());
            Config::write('totp_allow_admin_reset', 'YES');
            db_update($class === AdminHandler::class ? 'admin' : 'mailbox', 'username', 'yes@mfa-visible.example', ['active' => true]);
            $handler = new $class();
            self::assertTrue($handler->init('yes@mfa-visible.example'));
            self::assertStringContainsString('name="reset_totp"', $handler->result()['totp_action']);
            self::assertArrayNotHasKey('totp_action', (new $class(1))->getStruct());
            Config::write('totp_allow_admin_reset', 'YES');
        }
    }

    public function testCliResetWorksWithWebResetAndTotpDisabled(): void
    {
        Config::write('totp_allow_admin_reset', 'NO');
        Config::write('totp', 'NO');
        foreach ([AdminHandler::class => 'admin', MailboxHandler::class => 'mailbox'] as $class => $table) {
            $dispatch = (object)['params' => [], 'args' => ['YES@mfa-visible.example'], 'shell' => $table, 'shellCommand' => 'resetmfa'];
            $shell = new class ($dispatch) extends CliResetmfa {
                public function out($string, $newline = true)
                {
                }
                public function err($string)
                {
                }
            };
            $shell->handler_to_use = $class;
            self::assertSame(0, $shell->execute());
            self::assertNull(db_query_one('SELECT totp_secret FROM ' . table_by_key($table) . ' WHERE username = :username', ['username' => 'yes@mfa-visible.example'])['totp_secret']);
            self::assertSame(0, $shell->execute());
            $dispatch->args = ['missing@mfa-visible.example'];
            self::assertSame(1, $shell->execute());
            $dispatch->args = [];
            self::assertSame(1, $shell->execute());
            $dispatch->args = ['none@mfa-visible.example', 'yes@mfa-visible.example'];
            self::assertSame(1, $shell->execute());
        }
    }
}
