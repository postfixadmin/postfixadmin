<?php
//
// Postfix Admin
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// Licensed under GPL for more info check GPL-LICENSE.TXT
//
// File: login.php
//
// Template File: login.tpl
//
// Template Variables:
//
//  tMessage
//  tUsername
//
// Form POST \ GET Variables:
//
//  fUsername
//  fPassword
//

require_once('common.php');
if ($_SERVER['REQUEST_METHOD'] == "GET")
{
    include ("./templates/header.tpl");
    include ("./templates/login.tpl");
    include ("./templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    $fUsername = '';
    $fPassword = '';
    if (isset ($_POST['fUsername'])) $fUsername = escape_string ($_POST['fUsername']);
    if (isset ($_POST['fPassword'])) $fPassword = escape_string ($_POST['fPassword']);

    $result = db_query ("SELECT password FROM $table_admin WHERE username='$fUsername' AND active='1'");
    if ($result['rows'] == 1)
    {
        $row = db_array ($result['result']);
        $password = pacrypt ($fPassword, $row['password']);
        $result = db_query ("SELECT * FROM $table_admin WHERE username='$fUsername' AND password='$password' AND active='1'");
        if ($result['rows'] != 1)
        {
            $error = 1;
            $tMessage = $PALANG['pLogin_password_incorrect'];
            $tUsername = $fUsername;
        }
    }
    else
    {
        $error = 1;
        $tMessage = $PALANG['pLogin_username_incorrect'];
    }

    if ($error != 1)
    {
        session_regenerate_id();
        $_SESSION['sessid'] = array();
        $_SESSION['sessid']['username'] = $fUsername;
        $_SESSION['sessid']['roles'] = array();
        $_SESSION['sessid']['roles'][] = 'admin';

        // they've logged in, so see if they are a domain admin, as well.
        $result = db_query ("SELECT * FROM $table_domain_admins WHERE username='$fUsername' AND domain='ALL' AND active='1'");
        if ($result['rows'] == 1)
        {
            $_SESSION['sessid']['roles'][] = 'global-admin';
            header("Location: admin/list-admin.php");
            exit(0);
        }
        header("Location: main.php");
        exit(0);
    }

    include ("./templates/header.tpl");
    include ("./templates/login.tpl");
    include ("./templates/footer.tpl");
}
?>
