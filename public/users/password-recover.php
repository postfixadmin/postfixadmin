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



/* if in .../users, we need to load a different common.php; not this file is symlinked with public/ */
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


if ($context === 'admin' && !Config::read('forgotten_admin_password_reset') || $context === 'users' && !Config::read('forgotten_user_password_reset')) {
    die('Password reset is disabled by configuration option: forgotten_admin_password_reset');
}

function sendCodebyEmail($to, $username, $code) {
    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http';

    $_SERVER['REQUEST_SCHEME'] = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : $https;

    $url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/password-change.php?username=' . urlencode($username) . '&code=' . $code;

    return smtp_mail($to, Config::read('admin_email'), Config::Lang('pPassword_welcome'), Config::read('admin_smtp_password'), Config::lang_f('pPassword_recovery_email_body', $url));
}

function sendCodebySMS($to, $username, $code) {
    $text = Config::lang_f('pPassword_recovery_sms_body', $code);

    $function = Config::read('sms_send_function');
    if ($function && is_callable($function)) {
        $result = $function($to, $text);
        return $result !== false;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $start_time = microtime(true);

    $username = safepost('fUsername');
    if (empty($username)) {
        die("fUsername field required");
    }

    $tUsername = escape_string($username);

    $table = $context === 'admin' ? 'admin' : 'mailbox';
    $login = new Login($table);

    $token = $login->generatePasswordRecoveryCode($tUsername);

    if ($token !== false) {
        $table = table_by_key($context === 'users' ? 'mailbox' : 'admin');
        $row = db_query_one("SELECT * FROM $table WHERE username= :username", array('username' => $username));

        // $row must exist unless there's a race condition?

        $email_other = isset($row['email_other']) ? trim($row['email_other']) : null;
        $phone = isset($row['phone']) ? trim($row['phone']) : null;

        if ($email_other) {
            sendCodeByEmail($email_other, $tUsername, $token);
        } elseif ($phone) {
            sendCodeBySMS($phone, $tUsername, $token);
        } else {
            error_log(__FILE__ . " - No mechanism configured for password-recovery.");
        }

        if ($email_other || $phone) {
            header("Location: password-change.php?username=" . $tUsername);
            exit(0);
        }
    }

    // throttle password reset requests to prevent brute force attack
    $elapsed_time = microtime(true) - $start_time;
    if ($elapsed_time < 2 * pow(10, 6)) {
        usleep( (int) ( 2 * pow(10, 6) - $elapsed_time ) );
    }

    flash_info(Config::Lang('pPassword_recovery_processed'));
}

$smarty->assign('language_selector', language_selector(), false);
$smarty->assign('smarty_template', 'password-recover');
$smarty->display('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
