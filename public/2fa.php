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
 * File: 2fa.php
 * Displays 2fa form and check validity, redirecting to main (menu/home page)
 * Template File: 2fa.tpl
 *
 * Template Variables: -none-
 *
 * Form POST \ GET Variables: -none-
 */

require_once('common.php');

$checking_2fa_code = true;
$SESSID_USERNAME = authentication_get_username($checking_2fa_code);

$row = db_query_one("SELECT * FROM admin WHERE username= :username", array('username' => $SESSID_USERNAME));
$is_active_2fa = isset($row['x_2fa_active']) ? (bool)$row['x_2fa_active'] : null;

authentication_require_role('admin');

check_db_version(); # check if the database layout is up to date (and error out if not)

if ($_SERVER['REQUEST_METHOD'] == "POST" && $is_active_2fa) {
    if (!isset($_SESSION['PFA_token'])) {
        die("Invalid token (session timeout; refresh the page and try again?)");
    }

    if (safepost('token') != $_SESSION['PFA_token']) {
        die('Invalid token! (CSRF check failed)');
    }

    // we check code here
    $fCode = safepost('fCode');

    $ga = new PHPGangsta_GoogleAuthenticator();
    if($ga->verifyCode( $row['x_2fa_secret'], $fCode, 2)) {     // 2 = 2*30sec clock tolerance

        // ---- ToDo need some implementation in functions.php and login.php :-(
        // // set cookie don't ask for 2fa next 30 days
        // $f30DaysRemember = safepost('f30DaysRemember');
        // if(safepost('f30DaysRemember') == true) {
        //     setcookie('2fa_remember', true, time() + 60*60*24*30); # language cookie, lifetime 30 days
        // }

        // to check 2fa because user can change manually url to main page (or list or something else)
        $_SESSION['2fa_logged'] = true;

        header("Location: main.php");
        exit(0);        
    }
    else {
        session_unset();
        session_destroy();
        session_start();

        // to show error in login page
        $_SESSION['2fa_error'] = $PALANG['2fa_auth_failed'];

        header("Location: login.php");
        exit(0);  
    }
}


$smarty->assign('language_selector', language_selector(), false);
$smarty->assign('logintype', 'admin');

$smarty->display('2fa.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
