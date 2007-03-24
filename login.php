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
require ("./variables.inc.php");
require ("./config.inc.php");
require ("./functions.inc.php");
include ("./languages/" . check_language () . ".lang");

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   include ("./templates/header.tpl");
   include ("./templates/login.tpl");
   include ("./templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
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
      session_start();
      session_register("sessid");
      $_SESSION['sessid']['username'] = $fUsername;

      $result = db_query ("SELECT * FROM $table_domain_admins WHERE username='$fUsername' AND domain='ALL' AND active='1'");
      if ($result['rows'] == 1)
      {
         $row = db_array ($result['result']);
         if ($fUsername == $row['username'])
         {
            header("Location: admin/index.php");
            exit;
         }
      }
      header("Location: main.php");
      exit;
   }

   include ("./templates/header.tpl");
   include ("./templates/login.tpl");
   include ("./templates/footer.tpl");
}
?>
