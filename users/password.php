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
 * File: password.php
 * Used by users to change their mailbox (and login) password.
 * Template File: password.tpl
 *
 * Template Variables:
 *
 * none
 *
 * Form POST \ GET Variables:
 *
 * fPassword_current
 * fPassword
 * fPassword2
 */

require_once('../common.php');

authentication_require_role('user');
$username = authentication_get_username();

$pPassword_password_text = "";
$pPassword_password_current_text = "";

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    if(isset($_POST['fCancel'])) {
        header("Location: main.php");
        exit(0);
    }

    $fPassword_current = $_POST['fPassword_current'];
    $fPassword = $_POST['fPassword'];
    $fPassword2 = $_POST['fPassword2'];

    $error = 0;

    $validpass = validate_password($fPassword);
    if(count($validpass) > 0) {
        flash_error($validpass[0]); # TODO: honor all error messages, not only the first one
        $error += 1;
    }
  
    if(!MailboxHandler::login($username, $fPassword_current)) {
        $error += 1;
        $pPassword_password_current_text = $PALANG['pPassword_password_current_text_error'];
    }
    if (empty ($fPassword) or ($fPassword != $fPassword2))
    {
        $error += 1;
        $pPassword_password_text = $PALANG['pPassword_password_text_error'];
    }

    if ($error == 0)
    {
        $mh = new MailboxHandler();
        $mh->init($username); # TODO: error handling
        if($mh->change_pw($fPassword, $fPassword_current) ) {
            flash_info($PALANG['pPassword_result_success']);
            header("Location: main.php");
            exit(0);
        }
        else
        {
            flash_error($PALANG['pPassword_result_error']);
        }
    }
}

$smarty->assign ('SESSID_USERNAME', $username);
$smarty->assign ('pPassword_password_current_text', $pPassword_password_current_text, false);
$smarty->assign ('pPassword_password_text', $pPassword_password_text, false);

$smarty->assign ('smarty_template', 'password');
$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
