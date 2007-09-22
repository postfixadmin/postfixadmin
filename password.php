<?php
//
// Postfix Admin
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// Licensed under GPL for more info check GPL-LICENSE.TXT
//
// File: password.php
//
// Template File: password.tpl
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

require_once('common.php');

authentication_require_role('admin');

$SESSID_USERNAME = authentication_get_username();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
    include ("./templates/header.tpl");
    include ("./templates/menu.tpl");
    include ("./templates/password.tpl");
    include ("./templates/footer.tpl");
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
            $tMessage = $PALANG['pPassword_result_succes'];
        }
        else
        {
            $tMessage = $PALANG['pPassword_result_error'];
        }
    }

    include ("./templates/header.tpl");
    include ("./templates/menu.tpl");
    include ("./templates/password.tpl");
    include ("./templates/footer.tpl");
}
?>
