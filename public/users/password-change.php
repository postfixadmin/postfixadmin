<?php
/**
 * Postfix Admin
 *
 * LICENSE
 * This source file is subject to the GPL license that is bundled with
 * this package in the file LICENSE.TXT.
 *
 * Further details on the project are available at http://postfixadmin.sf.net
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

$CONF = Config::getInstance()->getAll();

$smarty->configureTheme($rel_path);

if ($context === 'admin' && !Config::read('forgotten_admin_password_reset') || $context === 'users' && !Config::read('forgotten_user_password_reset')) {
    die('Password reset is disabled by configuration option: forgotten_admin_password_reset');
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
        $error = true;
        flash_error(Config::lang('pPassword_password_text_error'));
    } else {
        $handler = $context === 'admin' ? new AdminHandler() : new MailboxHandler();
        if (!$handler->checkPasswordRecoveryCode($tUsername, $tCode)) {
            flash_error(Config::lang('pPassword_code_text_error'));
        } else {
            init_session($tUsername, $context === 'admin');
            if (!$handler->init($tUsername)) {
                flash_error($handler->errormsg);
            } else {
                $values = $handler->result;
                $values['password'] = $fPassword;
                $values['password2'] = $fPassword2;
                if ($handler->set($values) && $handler->save()) {
                    flash_info(Config::lang_f('pPassword_result_success', $tUsername));
                    header('Location: main.php');
                    exit(0);
                } else {
                    foreach ($handler->errormsg as $msg) {
                        flash_error($msg);
                    }
                }
            }
        }
    }
}

$smarty->assign('language_selector', language_selector(), false);
$smarty->assign('tUsername', @$tUsername);
$smarty->assign('tCode', @$tCode);
$smarty->assign('smarty_template', 'password-change');
$smarty->display('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
