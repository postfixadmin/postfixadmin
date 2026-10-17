<?php

use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the security fix around PasswordChangeAttempt::run():
 * the recovery code must never be invalidated (and the caller must never be
 * told to create a session) unless the new password was actually validated
 * and persisted.
 */
class PasswordChangeAttemptTest extends TestCase
{
    private function mailboxHandler(): MailboxHandler
    {
        return $this->createMock(MailboxHandler::class);
    }

    private function totpPf(bool $usesTotp = false): TotpPf
    {
        $totppf = $this->createMock(TotpPf::class);
        $totppf->method('usesTOTP')->willReturn($usesTotp);
        return $totppf;
    }

    /**
     * run() only makes sense for the two PFAHandler subclasses that represent an
     * account with a login/recovery code. Enforced via the MailboxHandler|AdminHandler
     * parameter type, so any other PFAHandler subclass - e.g. AliasHandler, which has
     * no concept of a recovery code or a password to save - must be rejected by PHP's
     * own type check before run() does anything.
     */
    public function testHandlerMustBeMailboxOrAdminHandler(): void
    {
        $this->expectException(\TypeError::class);

        /** @phpstan-ignore-next-line intentionally wrong type, to prove it is rejected */
        PasswordChangeAttempt::run($this->createMock(AliasHandler::class), $this->totpPf(), 'bob', 'code', '', 'newpass', 'newpass');
    }

    public function testWrongRecoveryCodeFailsWithoutTouchingTheHandlerFurther(): void
    {
        $handler = $this->mailboxHandler();
        $handler->method('checkPasswordRecoveryCode')->willReturn(false);
        $handler->expects($this->never())->method('init');
        $handler->expects($this->never())->method('wipePasswordRecoveryCode');

        $result = PasswordChangeAttempt::run($handler, $this->totpPf(), 'bob', 'wrong-code', '', 'newpass', 'newpass');

        $this->assertFalse($result['success']);
        $this->assertEquals([Config::lang('pPassword_code_text_error')], $result['errors']);
    }

    public function testWrongTotpCodeFailsWithoutSavingThePassword(): void
    {
        $handler = $this->mailboxHandler();
        $handler->method('checkPasswordRecoveryCode')->willReturn(true);
        $handler->expects($this->never())->method('init');
        $handler->expects($this->never())->method('wipePasswordRecoveryCode');

        $totppf = $this->totpPf(true);
        $totppf->method('checkUserTOTP')->willReturn(false);

        $result = PasswordChangeAttempt::run($handler, $totppf, 'bob', 'code', 'wrong-totp', 'newpass', 'newpass');

        $this->assertFalse($result['success']);
        $this->assertEquals([Config::lang('pTotp_failed')], $result['errors']);
    }

    /**
     * The bug: a failing save() (e.g. new password fails complexity rules) must not
     * wipe the recovery code - otherwise it stays usable for another attempt, and the
     * caller would previously have already created an authenticated session for it.
     */
    public function testFailedSaveDoesNotWipeRecoveryCode(): void
    {
        $handler = $this->mailboxHandler();
        $handler->method('checkPasswordRecoveryCode')->willReturn(true);
        $handler->method('init')->willReturn(true);
        $handler->result = ['username' => 'bob'];
        $handler->method('set')->willReturn(true);
        $handler->method('save')->willReturn(false);
        $handler->errormsg = ['password does not meet the rules'];
        $handler->expects($this->never())->method('wipePasswordRecoveryCode');

        $result = PasswordChangeAttempt::run($handler, $this->totpPf(), 'bob', 'code', '', 'bad', 'bad');

        $this->assertFalse($result['success']);
        $this->assertEquals(['password does not meet the rules'], $result['errors']);
    }

    public function testHandlerInitFailureIsReportedWithoutWipingTheCode(): void
    {
        $handler = $this->mailboxHandler();
        $handler->method('checkPasswordRecoveryCode')->willReturn(true);
        $handler->method('init')->willReturn(false);
        $handler->errormsg = ['no such user'];
        $handler->expects($this->never())->method('wipePasswordRecoveryCode');
        $handler->expects($this->never())->method('set');

        $result = PasswordChangeAttempt::run($handler, $this->totpPf(), 'bob', 'code', '', 'newpass', 'newpass');

        $this->assertFalse($result['success']);
        $this->assertEquals(['no such user'], $result['errors']);
    }

    public function testSuccessfulChangeWipesTheRecoveryCodeExactlyOnce(): void
    {
        $handler = $this->mailboxHandler();
        $handler->method('checkPasswordRecoveryCode')->willReturn(true);
        $handler->method('init')->willReturn(true);
        $handler->result = ['username' => 'bob'];
        $handler->method('set')->willReturn(true);
        $handler->method('save')->willReturn(true);
        $handler->expects($this->once())->method('wipePasswordRecoveryCode')->with('bob');

        $result = PasswordChangeAttempt::run($handler, $this->totpPf(), 'bob', 'code', '', 'newpass', 'newpass');

        $this->assertTrue($result['success']);
        $this->assertEquals([], $result['errors']);
    }
}
