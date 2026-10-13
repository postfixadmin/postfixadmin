<?php

class MailboxnameHandlerTest extends \PHPUnit\Framework\TestCase
{
    private array $configuration;
    private array $session;

    public function setUp(): void
    {
        $this->configuration = Config::read('all');
        $this->session = $_SESSION ?? [];
        Config::write('edit_mailbox_name', 'YES');
        Config::write('emailcheck_resolve_domain', 'NO');
        Config::write('mailbox_postedit_script', '');
        Config::write('logging', 'NO');
        self::assertSame(1, db_insert('domain', ['domain' => 'name.example', 'description' => '', 'transport' => 'virtual', 'active' => 1]));
        foreach (['alice', 'bob'] as $local) {
            $username = $local . '@name.example';
            self::assertSame(1, db_insert('mailbox', [
                'username' => $username, 'password' => 'unchanged hash', 'name' => 'Original',
                'maildir' => 'name.example/' . $local . '/', 'quota' => 10485760,
                'local_part' => $local, 'domain' => 'name.example', 'active' => 1,
            ]));
            self::assertSame(1, db_insert('alias', ['address' => $username, 'goto' => $username, 'domain' => 'name.example', 'active' => 1]));
        }
        $_SESSION = ['sessid' => ['username' => 'alice@name.example', 'roles' => ['user']]];
    }

    public function tearDown(): void
    {
        db_delete('alias', 'domain', 'name.example');
        db_delete('mailbox', 'domain', 'name.example');
        db_delete('domain_admins', 'domain', 'name.example');
        db_delete('domain', 'domain', 'name.example');
        foreach ($this->configuration as $key => $value) {
            Config::write($key, $value);
        }
        $_SESSION = $this->session;
    }

    private function handler(bool $new = false): MailboxnameHandler
    {
        return new MailboxnameHandler($new, 'alice@name.example', false);
    }

    public function testPolicyUsesExplicitDomainMembership(): void
    {
        foreach (['NO', [], ['other.example'], true, 2] as $policy) {
            Config::write('edit_mailbox_name', $policy);
            $this->assertFalse(MailboxnameHandler::userCanEditName('alice@name.example'));
        }
        foreach (['YES', ['name.example']] as $policy) {
            Config::write('edit_mailbox_name', $policy);
            $this->assertTrue(MailboxnameHandler::userCanEditName('ALICE@NAME.EXAMPLE'));
        }
        $this->assertFalse(MailboxnameHandler::userCanEditName(''));
    }

    public function testOwnNameChangesWithoutOtherMailboxOrAliasWrites(): void
    {
        $before = db_query_one('SELECT * FROM ' . table_by_key('mailbox') . ' WHERE username = ?', ['alice@name.example']);
        $alias = db_query_one('SELECT * FROM ' . table_by_key('alias') . ' WHERE address = ?', ['alice@name.example']);
        $handler = $this->handler();
        $this->assertTrue($handler->init('alice@name.example'));
        $this->assertTrue($handler->set([
            'name' => "Renée O'Connor <Accounts>", 'username' => 'bob@name.example',
            'password' => 'forged', 'quota' => 1, 'active' => 0, 'maildir' => 'forged',
        ]));
        $this->assertTrue($handler->save(), json_encode($handler->errormsg));
        $after = db_query_one('SELECT * FROM ' . table_by_key('mailbox') . ' WHERE username = ?', ['alice@name.example']);
        $this->assertSame("Renée O'Connor <Accounts>", $after['name']);
        unset($before['name'], $before['modified'], $after['name'], $after['modified']);
        $this->assertSame($before, $after);
        $this->assertSame($alias, db_query_one('SELECT * FROM ' . table_by_key('alias') . ' WHERE address = ?', ['alice@name.example']));
        $this->assertSame('Original', db_query_one('SELECT name FROM ' . table_by_key('mailbox') . ' WHERE username = ?', ['bob@name.example'])['name']);
    }

    public function testOwnershipCreationDeletionAndActivationAreDenied(): void
    {
        $this->assertFalse($this->handler()->init('bob@name.example'));
        $this->assertFalse($this->handler(true)->init('new@name.example'));
        $handler = $this->handler();
        $this->assertTrue($handler->init('alice@name.example'));
        $this->assertFalse($handler->delete());
        $this->assertFalse($handler->set(['active' => 0]));
        $admin = new MailboxnameHandler(false, 'admin', true);
        $this->assertFalse($admin->init('alice@name.example'));
    }

    public function testPolicyIsRecheckedAtSave(): void
    {
        $handler = $this->handler();
        $this->assertTrue($handler->init('alice@name.example'));
        $this->assertTrue($handler->set(['name' => 'Proposed']));
        Config::write('edit_mailbox_name', 'NO');
        $this->assertFalse($handler->save());
        $this->assertSame('Original', db_query_one('SELECT name FROM ' . table_by_key('mailbox') . ' WHERE username = ?', ['alice@name.example'])['name']);
    }

    public function testListIsScopedAndContainsNoCredentials(): void
    {
        $handler = $this->handler();
        $this->assertTrue($handler->getList(''));
        $this->assertSame(['alice@name.example'], array_keys($handler->result()));
        $row = $handler->result()['alice@name.example'];
        $this->assertArrayNotHasKey('password', $row);
        $this->assertArrayNotHasKey('token', $row);
        $this->assertEquals(0, $row['_can_delete']);
    }

    public function testNamesRespectUnicodeColumnCapacityAndControls(): void
    {
        $handler = $this->handler();
        $this->assertTrue($handler->init('alice@name.example'));
        $this->assertTrue($handler->set(['name' => str_repeat('é', 255)]));
        foreach ([str_repeat('é', 256), "Bad\nName", ["array"], "\xff"] as $name) {
            $invalid = $this->handler();
            $this->assertTrue($invalid->init('alice@name.example'));
            $this->assertFalse($invalid->set(['name' => $name]));
        }
        $this->assertTrue($handler->set(['name' => '']));
    }

    public function testAdministrativeNameValidationOnCreateAndEdit(): void
    {
        $_SESSION = ['sessid' => ['username' => 'admin', 'roles' => ['global-admin']]];
        self::assertSame(1, db_insert('domain_admins', ['username' => 'admin', 'domain' => 'name.example', 'active' => 1], ['created'], true));
        foreach ([false, true] as $new) {
            foreach ([str_repeat('é', 256), "Bad\nName", ["array"], "\xff"] as $name) {
                $handler = new MailboxHandler($new, 'admin', true);
                self::assertTrue($handler->init($new ? 'new@name.example' : 'alice@name.example'), json_encode($handler->errormsg));
                self::assertFalse($handler->set(['name' => $name]));
                self::assertArrayHasKey('name', $handler->errormsg);
            }
            foreach (['', str_repeat('é', 255)] as $name) {
                $handler = new MailboxHandler($new, 'admin', true);
                self::assertTrue($handler->init($new ? 'new@name.example' : 'alice@name.example'), json_encode($handler->errormsg));
                // Creation may require other fields; the supplied name must still pass validation.
                $handler->set(['name' => $name]);
                self::assertArrayNotHasKey('name', $handler->errormsg);
            }
        }
    }

    public function testAdministrativePartialEditPreservesStoredQuotaForScript(): void
    {
        $_SESSION = ['sessid' => ['username' => 'admin', 'roles' => ['global-admin']]];
        self::assertSame(1, db_insert('domain_admins', ['username' => 'admin', 'domain' => 'name.example', 'active' => 1], ['created'], true));
        $output = tempnam(sys_get_temp_dir(), 'admin-name-hook-');
        $script = tempnam(sys_get_temp_dir(), 'admin-name-script-');
        file_put_contents($script, "#!/bin/sh\nprintf '%s\n' \"$@\" > " . escapeshellarg($output) . "\nexit 0\n");
        chmod($script, 0700);
        Config::write('mailbox_postedit_script', $script);
        try {
            foreach ([['name' => 'Partial update'], ['name' => 'Quota update', 'quota' => 0]] as $values) {
                $handler = new MailboxHandler(false, 'admin', true);
                self::assertTrue($handler->init('alice@name.example'), json_encode($handler->errormsg));
                self::assertTrue($handler->set($values), json_encode($handler->errormsg));
                self::assertTrue($handler->save(), json_encode($handler->errormsg));
                $quota = isset($values['quota']) ? '0' : '10485760';
                self::assertSame(['alice@name.example', 'name.example', 'name.example/alice/', $quota], file($output, FILE_IGNORE_NEW_LINES));
                self::assertEquals($quota, db_query_one('SELECT quota FROM ' . table_by_key('mailbox') . ' WHERE username = ?', ['alice@name.example'])['quota']);
            }
        } finally {
            unlink($script);
            unlink($output);
        }
    }

    public function testPostEditScriptReceivesStoredQuotaAndFailureIsWarning(): void
    {
        $output = tempnam(sys_get_temp_dir(), 'name-hook-');
        $script = tempnam(sys_get_temp_dir(), 'name-script-');
        file_put_contents($script, "#!/bin/sh\nprintf '%s\n' \"\$@\" > " . escapeshellarg($output) . "\nexit 1\n");
        chmod($script, 0700);
        Config::write('mailbox_postedit_script', $script);
        try {
            $handler = $this->handler();
            $this->assertTrue($handler->init('alice@name.example'));
            $this->assertTrue($handler->set(['name' => 'Updated']));
            $this->assertTrue($handler->save());
            $this->assertNotEmpty($handler->errormsg);
            $this->assertSame(['alice@name.example', 'name.example', 'name.example/alice/', '10485760'], file($output, FILE_IGNORE_NEW_LINES));
            $this->assertSame('Updated', db_query_one('SELECT name FROM ' . table_by_key('mailbox') . ' WHERE username = ?', ['alice@name.example'])['name']);
        } finally {
            unlink($script);
            unlink($output);
        }
    }
}
