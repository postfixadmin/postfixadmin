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
 * File: password-recover.php
 * Used by users and admins to recover their forgotten login password.
 * Template File: password-recover.tpl
 *
 * Template Variables:
 *
 * none
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

if ($context === 'admin' && !Config::read('forgotten_admin_password_reset') || $context === 'users' && !Config::read('forgotten_user_password_reset')) {
    die('Password reset is disabled by configuration option: forgotten_admin_password_reset');
}

function sendCodebyEmail($to, $username, $code) {
    $url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/password-change.php?username=' . urlencode($username) . '&code=' . $code;

    return smtp_mail($to, Config::read('admin_email'), Config::Lang('pPassword_welcome'), Config::lang_f('pPassword_recovery_email_body', $url));
}

function sendCodebySMS($to, $username, $code) {
    $text = Config::lang_f('pPassword_recovery_sms_body', $code);

    if (Config::read('sms_send_function') && is_callable(Config::read('sms_send_function'))) {
        $result = call_user_func(Config::read('sms_send_function'), $to, $text);
        return $result !== false;
    }

    return false;
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $start_time = microtime(true);
    $tUsername = escape_string(safepost('fUsername'));
    $handler = $context === 'admin' ? new AdminHandler : new MailboxHandler;
    $token = $handler->getPasswordRecoveryCode($tUsername);
    if ($token !== false) {
        $table = table_by_key($context === 'users' ? 'mailbox' : 'admin');
        $result = db_query("SELECT * FROM $table WHERE username='$tUsername'");
        $row = db_assoc($result['result']);

        $email_other = trim($row['email_other']);
        $phone = trim($row['phone']);

        if ($email_other) {
            sendCodeByEmail($email_other, $tUsername, $token);
        }

        if ($phone) {
            sendCodeBySMS($phone, $tUsername, $token);
        }

        if ($email_other || $phone) {
            header("Location: password-change.php?username=" . $tUsername);
            exit(0);
        }
    }

    // throttle password reset requests to prevent brute force attack
    $elapsed_time = microtime(true) - $start_time;
    if ($elapsed_time < 2 * pow(10, 6)) {
        usleep(2 * pow(10, 6) - $elapsed_time);
    }

    flash_info(Config::Lang('pPassword_recovery_processed'));
}

$smarty->assign('language_selector', language_selector(), false);
$smarty->assign('smarty_template', 'password-recover');
$smarty->display('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
