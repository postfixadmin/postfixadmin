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
 * File: login.php
 * Used to authenticate want-to-be users.
 * Template File: login.tpl
 *
 * Template Variables:
 *
 *  tUsername
 *
 * Form POST \ GET Variables:
 *
 *  fUsername
 *  fPassword
 *  token
 *  lang
 */

$rel_path = '../';
require_once("../common.php");

$smarty = PFASmarty::getInstance();
$smarty->configureTheme('../');

check_db_version(); # check if the database layout is up to date (and error out if not)

$error = null;

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    if (safepost('token') != $_SESSION['PFA_token']) {
        die('Invalid token!');
    }

    $lang = safepost('lang');
    $fUsername = trim(safepost('fUsername'));
    $fPassword = safepost('fPassword');

    if ($lang != check_language(false)) { # only set cookie if language selection was changed
      setcookie('lang', $lang, time() + 60*60*24*30); # language cookie, lifetime 30 days
      # (language preference cookie is processed even if username and/or password are invalid)
    }

    $login = new Login('mailbox');
    ;
    if ($login->login($fUsername, $fPassword)) {
        init_session($fUsername, false);
        header("Location: main.php");
        exit;
    } else {
        error_log("PostfixAdmin user login failed (username: $fUsername, ip_address: {$_SERVER['REMOTE_ADDR']})");
        $error = $PALANG['pLogin_failed'];
    }
}

session_unset();
session_destroy();
session_start();

if ($error) {
    flash_error($error);
}
$_SESSION['PFA_token'] = md5(random_bytes(8) . uniqid('pfa', true));

$smarty->assign('language_selector', language_selector(), false);
$smarty->assign('smarty_template', 'login');
$smarty->assign('logintype', 'user');
$smarty->assign('forgotten_password_reset', Config::read('forgotten_user_password_reset'));
$smarty->display('index.tpl');

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
