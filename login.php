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
 *  lang
 */

define('POSTFIXADMIN_LOGOUT', 1);
require_once('common.php');

if($CONF['configured'] !== true) {
    print "Installation not yet configured; please edit config.inc.php or write your settings to config.local.php";
    exit;
}

check_db_version(); # check if the database layout is up to date (and error out if not)

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    $lang = safepost('lang');
    $fUsername = trim(safepost('fUsername'));
    $fPassword = safepost('fPassword');

    if ( $lang != check_language(0) ) { # only set cookie if language selection was changed
        setcookie('lang', $lang, time() + 60*60*24*30); # language cookie, lifetime 30 days
        # (language preference cookie is processed even if username and/or password are invalid)
    }

    $h = new AdminHandler;
    if ( $h->login($fUsername, $fPassword) ) {
        session_regenerate_id();
        $_SESSION['sessid'] = array();
        $_SESSION['sessid']['roles'] = array();
        $_SESSION['sessid']['roles'][] = 'admin';
        $_SESSION['sessid']['username'] = $fUsername;

        $_SESSION['PFA_token'] = md5(uniqid(rand(), true));

        # they've logged in, so see if they are a domain admin, as well.

        if (!$h->init($fUsername)) {
            flash_error($PALANG['pLogin_failed']);
        }

        if (!$h->view()) {
            flash_error($PALANG['pLogin_failed']);
        }

        $adminproperties = $h->result();

        if ($adminproperties['superadmin'] == 1) {
            $_SESSION['sessid']['roles'][] = 'global-admin';
        }

        header("Location: main.php");
        exit(0);

    } else { # $h->login failed
        error_log("PostfixAdmin login failed (username: $fUsername)");
        flash_error($PALANG['pLogin_failed']);
    }
}

$smarty->assign ('language_selector', language_selector(), false);
$smarty->assign ('smarty_template', 'login');
$smarty->assign ('logintype', 'admin');
$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
