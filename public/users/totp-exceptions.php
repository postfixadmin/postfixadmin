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
 * File: totp-exceptions.php
 * Used by users to view and change their totp exception addresses.
 * Template File: totp-exception.tpl
 *
 *
 * Form POST \ GET Variables:
 *
 * fPassword_current
 * fIp
 * fDesc
 * fUser
 * fId
 *
 */

require_once('common.php');

$smarty = PFASmarty::getInstance();
$smarty->configureTheme('../');

$username                   = authentication_get_username();
list($local_part, $domain)  = explode('@', $username);
$pPassword_text             = "";
$pUser_text                 = '';
$pUser                      = '';

$username   = authentication_get_username();

if (authentication_has_role('global-admin')) {
    $login = new Login('admin');
    $totppf = new TotpPf('admin');
    $admin = 2;
} elseif (authentication_has_role('admin')) {
    $login = new Login('admin');
    $totppf = new TotpPf('admin');
    $admin = 1;
} else {
    $login = new Login('mailbox');
    $totppf = new TotpPf('mailbox');
    $admin = 0;
}


if ($_SERVER['REQUEST_METHOD'] == "POST") {
    if (safepost('token') != $_SESSION['PFA_token']) {
        die('Invalid token!');
    }

    if (isset($_POST['fCancel'])) {
        header("Location: main.php");
        exit(0);
    }

    if (isset($_POST['fPassword_current']) && $_POST['fPassword_current'] != '') {
        $fPass      = $_POST['fPassword_current'];
        $fIp        = $_POST['fIp'];
        $fDesc      = $_POST['fDesc'];
        $fUser      = $_POST['fUser'];
        add_exception($username, $fPass, $fIp, $fDesc, $fUser, $totppf, $PALANG);
    }
    if (isset($_POST['fId']) && $_POST['fId'] != '' && is_numeric($_POST['fId'])) {
        $fId        = $_POST['fId'];
        revoke_exception($username, $fId, $totppf, $PALANG);
    }
}


// Generate list of existing exceptions

if ($admin==2) {
    $exceptions    = $totppf->getAllExceptions();
} else {
    $exceptions            = $totppf->getExceptionsFor($username);
}

// User can revoke exceptions for own username
// Admins can revoke exceptions for own domain
// Global-admin can revoke all exceptions
foreach ($exceptions as $n => $ex) {
    if ($ex['username'] == $username) {
        $exceptions[$n]['edit'] = 1;
    }
    if ($admin == 2) {
        $exceptions[$n]['edit'] = 1;
    }
    if ($admin==1 && $ex['username'] == $domain) {
        $exceptions[$n]['edit'] = 1;
    }
}



$smarty->assign('SESSID_USERNAME', $username);
$smarty->assign('pPassword_text', $pPassword_text, false);
$smarty->assign('pUser_text', $pUser_text, false);
$smarty->assign('pUser', $pUser, false);
#$smarty->assign('', $, false);
$smarty->assign('pExceptions', $exceptions, false);
$smarty->assign('smarty_template', 'totp-exceptions');
$smarty->display('index.tpl');


/**
 * @param string $username - current user from $_SESSION
 * @param string $fPassword_current - password for current user (to prevent user spoofing?)
 * @param string $fException_ip - IP address
 * @param string $fException_desc
 * @param string $fException_user
 * @param TotpPf $totppf
 * @param array $PALANG
 * @return void
 */
function add_exception(string $username, string $fPassword_current, string $fException_ip, string $fException_desc, string $fException_user, TotpPf $totppf,array $PALANG)
{
    try {
        if ($totppf->addException($username, $fPassword_current, $fException_ip, $fException_user, $fException_desc)) {
            flash_info($PALANG['pTotp_exception_result_success']);
            header("Location: totp-exceptions.php");
            exit(0);
        } else {
            flash_error($PALANG['pTotp_exception_result_error']);
        }
    } catch (\Exception $e) {
        flash_error($e->getMessage());
    }
}

function revoke_exception(string $username, int $id, TotpPf $totppf, array $PALANG)
{
    // No extra password check by design, user might be in a hurry
    $result = $totppf->deleteException($username, $id);
    if ($result) {
        flash_info($PALANG['pTotp_exceptions_revoked']);
    }
}


/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
