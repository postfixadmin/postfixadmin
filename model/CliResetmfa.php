<?php

/** Reset MFA for an existing administrator or mailbox from the local CLI. */
class CliResetmfa extends Shell
{
    public function execute()
    {
        if (!in_array($this->handler_to_use, [AdminHandler::class, MailboxHandler::class], true)
            || count($this->args) !== 1
            || array_diff(array_keys($this->params), ['webroot'])
        ) {
            $this->help();
            return 1;
        }

        $handler = new $this->handler_to_use();
        if (!$handler->init($this->args[0])) {
            $this->err($handler->errormsg);
            return 1;
        }

        $username = $handler->result()[$handler->getId_field()];
        $table = $this->handler_to_use === AdminHandler::class ? 'admin' : 'mailbox';
        try {
            $totp = new TotpPf($table, new Login($table));
            $totp->removeTotpFromUser($username);
        } catch (Exception $e) {
            $this->err(Config::lang_f('pTOTP_reset_failed', $username));
            return 1;
        }

        $this->out(Config::lang_f('pTOTP_reset_success', $username));
        return 0;
    }

    public function help()
    {
        $this->out('Usage: postfixadmin-cli <admin|mailbox> resetmfa <address>');
        $this->out('Remove the MFA secret from an existing account, even when web reset is disabled.');
    }
}
