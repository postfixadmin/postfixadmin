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
require ("../variables.inc.php");
require ("../config.inc.php");
require ("../functions.inc.php");
include ("../languages/" . check_language () . ".lang");
 
if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   include ("../templates/header.tpl");
   include ("../templates/users_login.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $fUsername = escape_string ($_POST['fUsername']);
   $fPassword = escape_string ($_POST['fPassword']);

   $query = "SELECT password FROM $table_mailbox WHERE username='$fUsername' AND active='1'";
   if ('pgsql'==$CONF['database_type'])
   {
      $query = "SELECT password FROM $table_mailbox WHERE username='$fUsername' AND active=true";
   }
   $result = db_query ($query);
   if ($result['rows'] == 1)
   {
      $row = db_array ($result['result']);
      $password = pacrypt ($fPassword, $row['password']);

      $query = "SELECT * FROM $table_mailbox WHERE username='$fUsername' AND password='$password' AND active='1'";
      if ('pgsql'==$CONF['database_type'])
      {
         $query = "SELECT * FROM $table_mailbox WHERE username='$fUsername' AND password='$password' AND active=true";
      }
      $result = db_query ($query);
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
      session_register("userid");
      $_SESSION['userid']['username'] = $fUsername;

      header("Location: main.php");
      exit;
   }
   
   include ("../templates/header.tpl");
   include ("../templates/users_login.tpl");
   include ("../templates/footer.tpl");
}
/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
