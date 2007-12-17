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
 * Template File: users_password.php
 *
 * Template Variables:
 *
 * tMessage
 *
 * Form POST \ GET Variables:
 *
 * fPassword_current
 * fPassword
 * fPassword2
 */

require_once('../common.php');

authentication_require_role('user');
$USERID_USERNAME = authentication_get_username();

$tmp = preg_split ('/@/', $USERID_USERNAME);     
$USERID_DOMAIN = $tmp[1];

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    if(isset($_POST['fCancel'])) {
        header("Location: main.php");
        exit(0);
    }

    $fPassword_current = escape_string ($_POST['fPassword_current']);
    $fPassword = escape_string ($_POST['fPassword']);
    $fPassword2 = escape_string ($_POST['fPassword2']);

    if(strlen($fPassword) < $CONF['min_password_length']) {
        $error = 1;
        flash_error($PALANG['pPassword_password_too_short_error'];
    }
    $username = $USERID_USERNAME;

    $result = db_query ("SELECT * FROM $table_mailbox WHERE username='$username'");
    if ($result['rows'] == 1)
    {
        $row = db_array ($result['result']);
        $checked_password = pacrypt($fPassword_current, $row['password']);

        $result = db_query ("SELECT * FROM $table_mailbox WHERE username='$username' AND password='$checked_password'");      
        if ($result['rows'] != 1)
        {
            $error = 1;
            $pPassword_password_current_text = $PALANG['pPassword_password_current_text_error'];
        }
    }
    else
    {
        $error = 1;
        $pPassword_email_text = $PALANG['pPassword_email_text_error']; 
    }

    if (empty ($fPassword) or ($fPassword != $fPassword2))
    {
        $error = 1;
        $pPassword_password_text = $PALANG['pPassword_password_text_error'];
    }

    if ($error != 1)
    {
        $password = pacrypt ($fPassword);
        $result = db_query ("UPDATE $table_mailbox SET password='$password',modified=NOW() WHERE username='$username'");
        if ($result['rows'] == 1)
        {
            flash_info($PALANG['pPassword_result_success']);
            db_log ($USERID_USERNAME, $USERID_DOMAIN, 'edit_password', "$USERID_USERNAME");
            header("Location: main.php");
            exit(0);
        }
        else
        {
            $tMessage = $PALANG['pPassword_result_error'];
        }
    }
}

include ("../templates/header.php");
include ("../templates/users_menu.php");
include ("../templates/users_password.php");
include ("../templates/footer.php");

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
