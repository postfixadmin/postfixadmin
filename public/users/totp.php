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
 * File: totp.php
 * Used by users to change their mailbox (and login) totp.
 * Template File: totp.tpl
 *
 *
 * Form POST \ GET Variables:
 *
 * fPassword_current
 * fTOTP_code
 * fTOTP_secret
 */

require_once('../common.php');

$smarty = PFASmarty::getInstance();
$smarty->configureTheme('../');

$username                                    = authentication_get_username();
$pPassword_password_current_text             = "";
$pTOTP_code_text                             = "";
$pTOTP_secret_text                           = "";
$pTOTP_now                                   = "";
$pPassword_password_text                     = "";
$pQR_raw               	                     = "";

if (authentication_has_role('admin')) {
    $totppf = new TotpPf('admin');
    $login  = new Login('admin');
    $admin = true;
} else {
    $totppf = new TotpPf('mailbox');
    $login  = new Login('mailbox');
    $admin = false;
}


// Create new OTP-object
// Generate random secret and resulting QR code
list($pTOTP_secret, $pQR_raw) = $totppf->generate($username);

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    if (safepost('token') != $_SESSION['PFA_token']) {
        die('Invalid token!');
    }

    if (isset($_POST['fCancel'])) {
        header("Location: main.php");
        exit(0);
    }

    $fPassword_current  = $_POST['fPassword_current'];
    $fTOTP_secret       = $_POST['fTOTP_secret'];
    $fTOTP_code         = $_POST['fTOTP_code'];
    $secreterror        = 0;

    if (!$login->login($username, $fPassword_current)) {
        $secreterror += 1;
        $pPassword_password_current_text = $PALANG['pPassword_password_current_text_error'];
    }

    // Does entered code from 2FA-app match the secret
    if ($fTOTP_code == '') {
        $code_checks_out    = true;
        $fTOTP_secret       = null;
    } else {
        $code_checks_out = $totppf->checkTOTP($fTOTP_secret,  $username, $fTOTP_code);
    }

    // Check that user has successfully generated a TOTP with external device
    if (!$code_checks_out) {
        $secreterror += 1;
        flash_error($PALANG['pTOTP_code_mismatch']);
    }

    // If TOTP checks out -> store secret in DB
    if ($secreterror == 0) {
        try {
            if ($totppf->changeTOTP_secret($username, $fTOTP_secret, $fPassword_current)) {
                flash_info($PALANG['pTotp_stored']);
            } else {
                flash_error(Config::Lang_f('pTOTP_secret_result_error', $username));
            }
        } catch (\Exception $e) {
            flash_error($e->getMessage());
        }
    }
}

if ($totppf->usesTOTP($username)) {
    $smarty->assign('show_form', 'hidden');
} else {
    $smarty->assign('show_form', 'visible');
}
$smarty->assign('SESSID_USERNAME', $username);
$smarty->assign('admin', $admin);
$smarty->assign('pPassword_password_current_text', $pPassword_password_current_text, false);
$smarty->assign('pTOTP_code_text', $pTOTP_code_text, false);
$smarty->assign('pTOTP_secret_text', $pTOTP_secret_text, false);
$smarty->assign('pPassword_password_text', $pPassword_password_text, false);
$smarty->assign('pQR_raw', $pQR_raw, false);
$smarty->assign('pTOTP_secret', $pTOTP_secret, false);
$smarty->assign('pTOTP_now', $pTOTP_now, false);
$smarty->assign('smarty_template', 'totp');
$smarty->display('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
