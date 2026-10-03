<?php

/**
 * Postfix Admin
 *
 * LICENSE
 * This source file is subject to the GPL license that is bundled with
 * this package in the file LICENSE.TXT.
 *
 * Further details on the project are available at https://github.com/postfixadmin/postfixadmin
 *
 * @version $Id$
 * @license GNU GPL v2 or later.
 *
 * File: password-change.php
 * Used by users and admins to change their forgotten login password.
 * Template File: password-change.tpl
 *
 * Template Variables:
 *
 * tUsername
 * tCode
 *
 * Form POST \ GET Variables:
 *
 * fUsername
 */


if (preg_match('/\/users\//', $_SERVER['REQUEST_URI'])) {
    $rel_path = '../';
    $context = 'users';
} else {
    $rel_path = './';
    $context = 'admin';
}
require_once($rel_path . 'common.php');

$smarty = PFASmarty::getInstance();

$smarty->configureTheme($rel_path);

$tCode = null;
$tUsername = null;

if ($context === 'admin' && !Config::read('forgotten_admin_password_reset')) {
    throw new \InvalidArgumentException('Password change is disabled by configuration option: forgotten_admin_password_reset or mailbox_postpassword_script');
}

if ($context === 'users' && (!Config::read('forgotten_user_password_reset') || Config::read('mailbox_postpassword_script'))) {
    throw new \InvalidArgumentException('Password change is disabled by configuration option: forgotten_user_password_reset or mailbox_postpassword_script');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $tUsername = safeget('username');
    $tCode = safeget('code');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (safepost('fCancel')) {
        header('Location: main.php');
        exit(0);
    }

    $fPassword = safepost('fPassword');
    $fPassword2 = safepost('fPassword2');

    $tUsername = safepost('fUsername');
    $tCode = trim(safepost('fCode'));

    if (empty($fPassword) or ($fPassword != $fPassword2)) {
        flash_error(Config::lang('pPassword_password_text_error'));
    } else {
        $handler = $context === 'admin' ? new AdminHandler() : new MailboxHandler();
        $totppf = new TotpPf($context === 'admin' ? 'admin' : 'mailbox', new Login($context === 'admin' ? 'admin' : 'mailbox'));

        $result = PasswordChangeAttempt::run($handler, $totppf, $tUsername, $tCode, safepost('fTOTP_code'), $fPassword, $fPassword2);

        if ($result['success']) {
            // only reachable once the new password has actually been validated and
            // persisted, and the recovery code invalidated - see security report on
            // init_session() being called too early.
            init_session($tUsername, $context === 'admin', true);
            flash_info(Config::lang_f('pPassword_result_success', $tUsername));
            header('Location: main.php');
            exit(0);
        } else {
            foreach ($result['errors'] as $msg) {
                flash_error($msg);
            }
        }
    }
}

$totppf = new TotpPf($context === 'admin' ? 'admin' : 'mailbox', new Login($context === 'admin' ? 'admin' : 'mailbox'));
$tTotpRequired = $totppf->usesTOTP($tUsername);
$smarty->assign('tTotpRequired', $tTotpRequired);
$smarty->assign('language_selector', language_selector(), false);
$smarty->assign('tUsername', $tUsername);
$smarty->assign('tCode', $tCode);
$smarty->assign('smarty_template', 'password-change');
$smarty->display('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
