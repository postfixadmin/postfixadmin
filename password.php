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
 * Allows admins to change their own password.
 * Template File: password.php
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

require_once('common.php');

authentication_require_role('admin');

$SESSID_USERNAME = authentication_get_username();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
    include ("./templates/header.php");
    include ("./templates/menu.php");
    include ("./templates/password.php");
    include ("./templates/footer.php");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    if (isset ($_POST['fPassword_current'])) $fPassword_current = escape_string ($_POST['fPassword_current']);
    if (isset ($_POST['fPassword'])) $fPassword = escape_string ($_POST['fPassword']);
    if (isset ($_POST['fPassword2'])) $fPassword2 = escape_string ($_POST['fPassword2']);

    $username = $SESSID_USERNAME;

    $result = db_query ("SELECT * FROM $table_admin WHERE username='$username'");
    if ($result['rows'] == 1)
    {
        $row = db_array ($result['result']);
        $checked_password = pacrypt ($fPassword_current, $row['password']);

        $result = db_query ("SELECT * FROM $table_admin WHERE username='$username' AND password='$checked_password'");
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
        $result = db_query ("UPDATE $table_admin SET password='$password',modified=NOW() WHERE username='$username'");
        if ($result['rows'] == 1)
        {
            $tMessage = $PALANG['pPassword_result_success'];
        }
        else
        {
            $tMessage = $PALANG['pPassword_result_error'];
        }
    }

    include ("./templates/header.php");
    include ("./templates/menu.php");
    include ("./templates/password.php");
    include ("./templates/footer.php");
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
