<?php

/**
 * Attempts to change a user's/admin's forgotten password via a recovery code.
 *
 * Never wipes the recovery code unless the new password was actually validated
 * and persisted - callers must only create a session (via init_session()) when
 * $result['success'] is true.
 */
class PasswordChangeAttempt
{
    /**
     * @param MailboxHandler|AdminHandler $handler - the only two PFAHandler subclasses
     *        that represent an account with a login/recovery code; any other handler
     *        wouldn't have a meaningful checkPasswordRecoveryCode()/save() flow here.
     * @return array{success: bool, errors: string[]}
     */
    public static function run(
        MailboxHandler|AdminHandler $handler,
        TotpPf $totppf,
        string $username,
        string $code,
        string $totpCode,
        string $password,
        string $password2
    ): array {
        if (!$handler->checkPasswordRecoveryCode($username, $code)) {
            return ['success' => false, 'errors' => [Config::lang('pPassword_code_text_error')]];
        }

        if ($totppf->usesTOTP($username) && !$totppf->checkUserTOTP($username, $totpCode)) {
            return ['success' => false, 'errors' => [Config::lang('pTotp_failed')]];
        }

        if (!$handler->init($username)) {
            return ['success' => false, 'errors' => $handler->errormsg];
        }

        $values = $handler->result;
        $values['password'] = $password;
        $values['password2'] = $password2;

        if (!($handler->set($values) && $handler->save())) {
            return ['success' => false, 'errors' => $handler->errormsg];
        }

        $handler->wipePasswordRecoveryCode($username);

        // $handler->errormsg will most likely be an empty array
        return ['success' => true, 'errors' => $handler->errormsg];
    }
}
