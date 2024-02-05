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
 * File: app-passwords.php
 * Used by users to view and change their app passwords.
 * Template File: app-passwords.tpl
 *
 *
 * Form POST \ GET Variables:
 *
 * fPassword_current
 * fAppDesc
 * fAppPass
 * fAppId
 *
 */

require_once('../common.php');

$smarty = PFASmarty::getInstance();
$smarty->configureTheme('../');

$username = authentication_get_username();
$pPassword_text = "";
$pUser_text = '';
$pUser = '';

if (authentication_has_role('global-admin')) {
    $login = new Login('admin');
    $admin = 2;
} elseif (authentication_has_role('admin')) {
    $login = new Login('admin');
    $admin = 1;
} else {
    $login = new Login('mailbox');
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

    if (isset($_POST['fAppPass'])) {
        $fPass = $_POST['fPassword_current'];
        $fAppDesc = $_POST['fAppDesc'];
        $fAppPass = $_POST['fAppPass'];
        addAppPassword($username, $fPass, $fAppPass, $fAppDesc, $login, $PALANG);
    }

    if (isset($_POST['fAppId']) && is_numeric($_POST['fAppId'])) {
        $fAppId = (int)$_POST['fAppId'];
        revokeAppPassword($username, $fAppId, $PALANG);
    }
}

if ($admin == 2) {
    $passwords = getAllAppPasswords();
} else {
    $passwords = getAppPasswordsFor($username);
}

foreach ($passwords as $n => $pass) {
    if ($pass['username'] == $username) {
        $passwords[$n]['edit'] = 1;
    }
    if ($admin == 2) {
        $passwords[$n]['edit'] = 1;
    }
}


$smarty->assign('SESSID_USERNAME', $username);
$smarty->assign('pPassword_text', $pPassword_text, false);
$smarty->assign('pUser_text', $pUser_text, false);
$smarty->assign('pUser', $pUser, false);
$smarty->assign('pPasswords', $passwords, false);
$smarty->assign('smarty_template', 'app-passwords');
$smarty->display('index.tpl');


/**
 * @param string $username - postfixadmin username
 * @param string $fPass - postfixadmin password for username
 * @param string $fAppPass - application password
 * @param string $fAppDesc - application description
 * @param Login $login
 * @param array $PALANG
 * @return void
 */
function addAppPassword(string $username, string $fPass, string $fAppPass, string $fAppDesc, Login $login, array $PALANG)
{
    try {
        if ($login->addAppPassword($username, $fPass, $fAppDesc, $fAppPass)) {
            flash_info($PALANG['pAppPassAdd_result_success']);
            header("Location: app-passwords.php");
            exit(0);
        } else {
            flash_error(Config::Lang_f('pAppPassAdd_result_error', $username));
        }
    } catch (\Exception $e) {
        flash_error($e->getMessage());
    }
}

/**
 * @todo Why pass Login here? it's not used?
 */
function revokeAppPassword(string $username, int $fAppId, array $PALANG)
{
    // $username should be from $_SESSION and not modifiable by the end user
    // we don't want someone to be able to delete someone else's app password by guessing an id...
    $row = db_query_one('SELECT id FROM mailbox_app_password WHERE id = :id AND username = :username', ['username' => $username, 'id' => $fAppId]);
    if (is_array($row) && isset($row['id'])) {
        $result = db_delete('mailbox_app_password', 'id', $row['id']);
        if ($result == 1) {
            flash_info($PALANG['pTotp_exceptions_revoked']);
            return;
        }
    }
    flash_error($PALANG['pPassword_result_error']);
}

/**
 * @return array
 */
function getAllAppPasswords()
{
    return db_query_all("SELECT * FROM mailbox_app_password");
}

/**
 * @param string $username
 * @return array
 */
function getAppPasswordsFor($username)
{
    return db_query_all("SELECT * FROM mailbox_app_password WHERE username = :username", ['username' => $username]);
}


/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
