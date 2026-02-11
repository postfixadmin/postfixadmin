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
 * File: login.php
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

use model\Languages;

require_once('common.php');

$CONF = Config::getInstance()->getAll();
$smarty = PFASmarty::getInstance();

if ($CONF['configured'] !== true) {
    print "Installation not yet configured; please edit config.inc.php or write your settings to config.local.php";
    exit;
}

check_db_version(); # check if the database layout is up to date (and error out if not)

if (authentication_mfa_incomplete()) {
    header("Location: login-mfa.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {

    (new CsrfToken())->assertValid(safepost('CSRF_Token'));

    $lang = safepost('lang');
    $fUsername = trim(safepost('fUsername'));
    $fPassword = safepost('fPassword');

    if ($lang != Languages::check_language(false)) { # only set cookie if language selection was changed
        setcookie('lang', $lang, time() + 60 * 60 * 24 * 30); # language cookie, lifetime 30 days
        # (language preference cookie is processed even if username and/or password are invalid)
    }

    $adminHandler = new AdminHandler();

    $login = new Login('admin');
    if ($login->login($fUsername, $fPassword)) {
        init_session($fUsername, true);

        # they've logged in, so see if they are a domain admin, as well.

        if (!$adminHandler->init($fUsername)) {
            flash_error($PALANG['pLogin_failed']);
        }

        if (!$adminHandler->view()) {
            flash_error($PALANG['pLogin_failed']);
        }

        $adminproperties = $adminHandler->result();

        $totppf = new TotpPf('admin', $login);
        if ($totppf->usesTOTP($fUsername)) {
            init_session($fUsername, true, false);
            header("Location: login-mfa.php");
            exit(0);
        }

        init_session($fUsername, true, true);

        if ($adminproperties['superadmin'] == 1) {
            $_SESSION['sessid']['roles'][] = 'global-admin';
        }

        header("Location: main.php");
        exit(0);
    } else { # $h->login failed
        error_log("PostfixAdmin admin login failed (username: $fUsername, ip_address: {$_SERVER['REMOTE_ADDR']})");
        flash_error($PALANG['pLogin_failed']);
    }
} else {
    session_unset();
    session_destroy();
    session_start();
}

$smarty->assign('language_selector', language_selector(), false);
$smarty->assign('smarty_template', 'login');
$smarty->assign('logintype', 'admin');
$smarty->assign('forgotten_password_reset', Config::bool('forgotten_admin_password_reset'));
$smarty->display('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
