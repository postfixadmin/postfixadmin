<?php
/** 
 * Postfix Admin 
 * 
 * LICENSE 
 * This source file is subject to the GPL license that is bundled with  
 * this package in the file LICENSE.TXT. 
 * 
 * Further details on the project are available at : 
 *     http://www.postfixadmin.com or http://postfixadmin.sf.net 
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

require_once('common.php');

if($CONF['configured'] !== true) {
    print "Installation not yet configured; please edit config.inc.php";
    exit;
}


if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    $fUsername = safepost('fUsername');
    $fPassword = safepost('fPassword');
    $lang = safepost('lang');

    if ( $lang != check_language(0) ) { # only set cookie if language selection was changed
        setcookie('lang', $lang, time() + 60*60*24*30); # language cookie, lifetime 30 days
        # (language preference cookie is processed even if username and/or password are invalid)
    }

    $h = new AdminHandler;
    if ( $h->login($fUsername, $fPassword) ) {
        session_regenerate_id();
        $_SESSION['sessid'] = array();
        $_SESSION['sessid']['username'] = $fUsername;
        $_SESSION['sessid']['roles'] = array();
        $_SESSION['sessid']['roles'][] = 'admin';

        // they've logged in, so see if they are a domain admin, as well.
        # TODO: use AdminHandler and the superadmin flag
        $result = db_query ("SELECT * FROM $table_domain_admins WHERE username='$fUsername' AND domain='ALL' AND active='1'");
        if ($result['rows'] == 1)
        {
            $_SESSION['sessid']['roles'][] = 'global-admin';
            #            header("Location: admin/list-admin.php");
            #            exit(0);
        }
        header("Location: main.php");
        exit(0);
    } else {
        flash_error($PALANG['pLogin_failed']);
    }
}

$smarty->assign ('language_selector', language_selector(), false);
$smarty->assign ('logintype', 'admin');
$smarty->assign ('smarty_template', 'login');
$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
