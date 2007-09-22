<?php
// 
// Postfix Admin 
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// Licensed under GPL for more info check GPL-LICENSE.TXT
//
// File: password.php
//
// Template File: users_password.tpl
//
// Template Variables:
//
// tMessage
//
// Form POST \ GET Variables:
//
// fPassword_current
// fPassword
// fPassword2
//

require_once('../common.php');

authentication_require_role('user');
$USERID_USERNAME = authentication_get_username();

$tmp = preg_split ('/@/', $USERID_USERNAME);     
$USERID_DOMAIN = $tmp[1];

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
    include ("../templates/header.tpl");
    include ("../templates/users_menu.tpl");
    include ("../templates/users_password.tpl");
    include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    $fPassword_current = escape_string ($_POST['fPassword_current']);
    $fPassword = escape_string ($_POST['fPassword']);
    $fPassword2 = escape_string ($_POST['fPassword2']);

    $username = $USERID_USERNAME;

    $result = db_query ("SELECT * FROM $table_mailbox WHERE username='$username'");
    if ($result['rows'] == 1)
    {
        $row = db_array ($result['result']);
        $checked_password = pacrypt ($fPassword_current, $row['password']);

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
            $tMessage = $PALANG['pPassword_result_succes'];
            db_log ($USERID_USERNAME, $USERID_DOMAIN, "change password", "$USERID_USERNAME");
        }
        else
        {
            $tMessage = $PALANG['pPassword_result_error'];
        }
    }

    include ("../templates/header.tpl");
    include ("../templates/users_menu.tpl");
    include ("../templates/users_password.tpl");
    include ("../templates/footer.tpl");
}
?>
