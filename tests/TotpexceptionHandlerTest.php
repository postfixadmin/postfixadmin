<?php

class TotpexceptionHandlerTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        db_insert('domain', ['domain' => 'example.com', 'description' => 'test',
            'transport' => 'virtual', 'active' => true, 'backupmx' => false]);
        db_insert('admin', ['username' => 'global@example.com', 'password' => pacrypt('current-password'),
            'active' => true, 'superadmin' => true]);
        db_insert('admin', ['username' => 'admin@example.com', 'password' => pacrypt('admin-password'),
            'active' => true, 'superadmin' => false]);
        db_insert('mailbox', ['username' => 'alice@example.com', 'password' => pacrypt('current-password'),
            'name' => 'Alice', 'maildir' => 'example.com/alice/', 'local_part' => 'alice', 'domain' => 'example.com', 'active' => true]);
    }

    protected function tearDown(): void
    {
        db_query('DELETE FROM totp_exception_address');
        db_query('DELETE FROM mailbox');
        db_query('DELETE FROM domain_admins');
        db_query('DELETE FROM admin');
        db_query('DELETE FROM domain');
        $_SESSION = [];

        parent::tearDown();
    }

    private function asSuperadmin(): void
    {
        $_SESSION = ['sessid' => ['roles' => ['global-admin']]];
    }

    private function asAdmin(): void
    {
        $_SESSION = ['sessid' => ['roles' => ['admin']]];
    }

    private function asUser(): void
    {
        $_SESSION = ['sessid' => ['roles' => ['user']]];
    }

    /**
     * @return array<int, ?string> the 'username' column of every stored totp_exception_address row
     */
    private function storedUsernames(): array
    {
        $rows = db_query_all('SELECT username FROM totp_exception_address');
        return array_column($rows, 'username');
    }

    public function testSuperadminCreatingGlobalExceptionNormalisesEmptyUsernameToNull(): void
    {
        $this->asSuperadmin();

        $handler = new TotpexceptionHandler(1, 'global@example.com', 1);
        $this->assertTrue($handler->init(''));

        $this->assertTrue($handler->set([
            'password_current' => 'current-password',
            'username' => '',
            'ip' => '10.0.0.1',
            'description' => 'global exception',
        ]), json_encode($handler->errormsg));
        $this->assertTrue($handler->save(), json_encode($handler->errormsg));

        $this->assertSame([null], $this->storedUsernames());
    }

    public function testUserCannotCreateExceptionForAnotherUser(): void
    {
        $this->asUser();

        // alice is logged in, but tries to submit bob's username.
        $handler = new TotpexceptionHandler(1, 'alice@example.com', 0);
        $this->assertTrue($handler->init(''));

        $this->assertTrue($handler->set([
            'password_current' => 'current-password',
            'username' => 'bob@example.com',
            'ip' => '10.0.0.2',
            'description' => 'attempted takeover',
        ]), json_encode($handler->errormsg));
        $this->assertTrue($handler->save(), json_encode($handler->errormsg));

        // the stored row must be scoped to alice, never to the submitted (attacker-controlled) value.
        $this->assertSame(['alice@example.com'], $this->storedUsernames());
    }

    public function testUserCanCreateExceptionForSelf(): void
    {
        $this->asUser();

        $handler = new TotpexceptionHandler(1, 'alice@example.com', 0);
        $this->assertTrue($handler->init(''));

        $this->assertSame(0, $handler->getStruct()['username']['display_in_form']);
        $this->assertTrue($handler->set([
            'password_current' => 'current-password',
            'ip' => '10.0.0.3',
            'description' => 'own exception',
        ]), json_encode($handler->errormsg));
        $this->assertTrue($handler->save(), json_encode($handler->errormsg));

        $this->assertSame(['alice@example.com'], $this->storedUsernames());
    }

    public function testUserCannotCreateGlobalException(): void
    {
        $this->asUser();

        $handler = new TotpexceptionHandler(1, 'alice@example.com', 0);
        $this->assertTrue($handler->init(''));

        $this->assertTrue($handler->set([
            'password_current' => 'current-password',
            'username' => '',
            'ip' => '10.0.0.4',
            'description' => 'global attempt',
        ]));
        $this->assertTrue($handler->save());
        $this->assertSame(['alice@example.com'], $this->storedUsernames());
    }

    public function testSuperadminCanCreateExceptionForAnyUsername(): void
    {
        $this->asSuperadmin();

        $handler = new TotpexceptionHandler(1, 'global@example.com', 1);
        $this->assertTrue($handler->init(''));

        $this->assertTrue($handler->set([
            'password_current' => 'current-password',
            'username' => 'someone@example.com',
            'ip' => '10.0.0.5',
            'description' => 'superadmin set',
        ]), json_encode($handler->errormsg));
        $this->assertTrue($handler->save(), json_encode($handler->errormsg));

        $this->assertSame(['someone@example.com'], $this->storedUsernames());
    }

    public function testGenericFormRequiresPasswordConfirmation(): void
    {
        $this->asAdmin();
        $handler = new TotpexceptionHandler(1, 'admin@example.com', true);
        $formconf = $handler->webformConfig();
        $this->assertEmpty($formconf['disable_edit_form'] ?? null);
        $this->assertSame('username', $formconf['user_hardcoded_field']);
        $this->assertSame('pass', $handler->getStruct()['password_current']['type']);
        $this->assertSame(1, $handler->getStruct()['username']['display_in_form']);
        $this->assertTrue($handler->init(''));
        foreach ([[], ['password_current' => ''], ['password_current' => 'wrong'], ['password_current' => ['invalid']]] as $password) {
            $this->assertFalse($handler->set($password + ['username' => 'admin@example.com', 'ip' => '10.0.0.6', 'description' => 'test']));
            $this->assertFalse($handler->save());
        }
        $this->assertSame([], $this->storedUsernames());
    }

    public function testPasswordConfirmationCoversEditsAndAuthenticatesTheActor(): void
    {
        $this->asSuperadmin();
        $handler = new TotpexceptionHandler(1, 'global@example.com', true);
        $this->assertTrue($handler->init(''));
        $this->assertTrue($handler->set(['password_current' => 'current-password',
            'username' => 'alice@example.com', 'ip' => '10.0.0.7', 'description' => 'before']));
        $this->assertTrue($handler->save());
        $row = db_query_one('SELECT id FROM totp_exception_address');
        $edit = new TotpexceptionHandler(0, 'global@example.com', true);
        $this->assertTrue($edit->init((string)$row['id']));
        $this->assertFalse($edit->set(['description' => 'bypassed']));
        $this->assertFalse($edit->save());
        $edit = new TotpexceptionHandler(0, 'global@example.com', true);
        $this->assertTrue($edit->init((string)$row['id']));
        $this->assertTrue($edit->set(['password_current' => 'current-password', 'description' => 'after']));
        $this->assertTrue($edit->save());
        $this->assertFalse($edit->set(['description' => 'reused confirmation']));
        $this->assertFalse($edit->save());
        $this->assertSame('after', db_query_one('SELECT description FROM totp_exception_address')['description']);
    }

    public function testDomainAdminCannotTargetUnmanagedDomainWithValidPassword(): void
    {
        db_insert('domain_admins', ['username' => 'admin@example.com',
            'domain' => 'example.com', 'active' => true], ['created'], true);
        $this->asAdmin();
        $handler = new TotpexceptionHandler(1, 'admin@example.com', true);
        $this->assertTrue($handler->init(''));
        $this->assertFalse($handler->set(['password_current' => 'admin-password',
            'username' => 'victim@other.example', 'ip' => '10.0.0.8', 'description' => 'outside']));
        $this->assertSame([], $this->storedUsernames());
        $handler = new TotpexceptionHandler(1, 'admin@example.com', true);
        $this->assertTrue($handler->init(''));
        $this->assertTrue($handler->set(['password_current' => 'admin-password',
            'username' => 'user@example.com', 'ip' => '10.0.0.8', 'description' => 'inside']));
        $this->assertTrue($handler->save());
        $this->assertSame(['user@example.com'], $this->storedUsernames());
    }
    public function testTargetPasswordDoesNotAuthorizeTheActor(): void
    {
        $this->asSuperadmin();
        $handler = new TotpexceptionHandler(1, 'global@example.com', true);
        $this->assertTrue($handler->init(''));
        $this->assertFalse($handler->set(['password_current' => 'admin-password',
            'username' => 'admin@example.com', 'ip' => '10.0.0.9', 'description' => 'target password']));
        $this->assertFalse($handler->save());
        $this->assertSame([], $this->storedUsernames());
    }

}
