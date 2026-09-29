<?php

class TotpexceptionHandlerTest extends \PHPUnit\Framework\TestCase
{
    protected function tearDown(): void
    {
        db_query('DELETE FROM totp_exception_address');
        db_query('DELETE FROM domain_admins');
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

    private function addDomain(string $domain, string $adminUsername): void
    {
        $this->asSuperadmin();

        $dh = new DomainHandler(1, $adminUsername, true);
        $dh->init($domain);
        $ret = $dh->set([
            'domain' => $domain,
            'description' => 'test domain',
            'aliases' => 10,
            'mailboxes' => 10,
            'active' => 1,
            'backupmx' => 0,
            'default_aliases' => 0,
        ]);
        $this->assertTrue($ret, json_encode($dh->errormsg));
        $this->assertTrue($dh->save());

        db_insert('domain_admins', ['username' => $adminUsername, 'domain' => $domain, 'active' => 1], ['created'], true);
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

        $handler = new TotpexceptionHandler(1, '', 1);
        $this->assertTrue($handler->init(''));

        $this->assertTrue($handler->set([
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

        $this->assertTrue($handler->set([
            'username' => 'alice@example.com',
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

        $this->assertFalse($handler->set([
            'username' => '',
            'ip' => '10.0.0.4',
            'description' => 'global attempt',
        ]));
        $this->assertArrayHasKey('username', $handler->errormsg);
        $this->assertEmpty($this->storedUsernames());
    }

    public function testSuperadminCanCreateExceptionForAnyUsername(): void
    {
        $this->asSuperadmin();

        $handler = new TotpexceptionHandler(1, '', 1);
        $this->assertTrue($handler->init(''));

        $this->assertTrue($handler->set([
            'username' => 'someone@example.com',
            'ip' => '10.0.0.5',
            'description' => 'superadmin set',
        ]), json_encode($handler->errormsg));
        $this->assertTrue($handler->save(), json_encode($handler->errormsg));

        $this->assertSame(['someone@example.com'], $this->storedUsernames());
    }
}
