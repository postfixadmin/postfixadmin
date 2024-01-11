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
 * File: login-totp.php
 * Authenticates a user, and populates their $_SESSION as appropriate.
 * Template File: login.tpl
 *
 * Template Variables:
 *
 *  none
 *
 * Form POST \ GET Variables:
 *
 *  fUsername
 *  fPassword
 *  token
 *  lang
 */

require_once('common.php');

$CONF = Config::getInstance()->getAll();
$smarty = PFASmarty::getInstance();
$error = '';

if (authentication_has_role("admin")) {
    header("Location: main.php");
    exit(0);
}

if (isset($_GET["abort"]) && $_GET["abort"] == "1" && authentication_mfa_incomplete()) {
    session_unset();
    session_destroy();
    session_start();
    header("Location: login.php");
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    if (!isset($_SESSION['PFA_token'])) {
        die("Invalid token (session timeout; refresh the page and try again?)");
    }

    if (safepost('token') != $_SESSION['PFA_token']) {
        die('Invalid token! (CSRF check failed)');
    }

    $totppf = new TotpPf('admin');
    $fTotp = safepost('fTOTP_code');
    $h = new AdminHandler();

    if (authentication_mfa_incomplete() && $totppf->checkUserTOTP(authentication_get_username(), $fTotp)) {
        init_session(authentication_get_username(), true, true);

        // get superadmin status and store in session
        $h->init(authentication_get_username());
        if ($h->result()['superadmin'] == 1) {
            $_SESSION['sessid']['roles'][] = 'global-admin';
        }
        header("Location: main.php");
        exit(0);
    } else {
        error_log("PostfixAdmin admin second factor login failed (username: " . authentication_get_username() . ", ip_address: {$_SERVER['REMOTE_ADDR']})");
        $error = $PALANG['pTotp_failed'];
        flash_error($PALANG['pTotp_failed']);
    }
}

$_SESSION['PFA_token'] = md5(uniqid("pfa" . rand(), true));

$smarty->assign('pTOPT_code_text', $error);
$smarty->assign('smarty_template', 'login-mfa');
$smarty->assign('language_selector', language_selector(), false);
$smarty->assign('forgotten_password_reset', Config::bool('forgotten_admin_password_reset'));
$smarty->display('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
