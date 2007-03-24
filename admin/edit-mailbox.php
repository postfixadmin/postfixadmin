<?php
// 
// Postfix Admin 
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// License Info: http://www.postfixadmin.com/?file=LICENSE.TXT
//
// File: edit-mailbox.php
//
// Template File: edit-mailbox.tpl
//
// Template Variables:
//
// tMessage
// tName
// tQuota
//
// Form POST \ GET Variables:
//
// fUsername
// fDomain
// fPassword
// fPassword2
// fName
// fQuota
// fActive
//
require ("../variables.inc.php");
require ("../config.inc.php");
require ("../functions.inc.php");
include ("../languages/" . check_language () . ".lang");

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   $fUsername = escape_string ($_GET['username']);
   $fDomain = escape_string ($_GET['domain']);

   $result = db_query ("SELECT * FROM mailbox WHERE username='$fUsername' AND domain='$fDomain'");
   if ($result['rows'] == 1)
   {
      $row = db_array ($result['result']);
      $tName = $row['name'];
      $tQuota = $row['quota'] / $CONF['quota_multiplier'];
      $tActive = $row['active'];
   }
   else
   {
      $tMessage = $PALANG['pEdit_mailbox_login_error'];
   }
   
   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/edit-mailbox.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $pEdit_mailbox_password_text = $PALANG['pEdit_mailbox_password_text_error'];
   $pEdit_mailbox_quota_text = $PALANG['pEdit_mailbox_quota_text'];
   
   $fUsername = escape_string ($_GET['username']);
   $fUsername = strtolower ($fUsername);
   $fDomain = escape_string ($_GET['domain']);
   
   $fPassword = escape_string ($_POST['fPassword']);
   $fPassword2 = escape_string ($_POST['fPassword2']);
   $fName = escape_string ($_POST['fName']);
   if (isset ($_POST['fQuota'])) $fQuota = escape_string ($_POST['fQuota']);
   if (isset ($_POST['fActive'])) $fActive = escape_string ($_POST['fActive']);
  
	if ($fPassword != $fPassword2)
	{
	   $error = 1;
      $tName = $fName;
      $tQuota = $fQuota;
      $tActive = $fActive;
      $pEdit_mailbox_password_text = $PALANG['pEdit_mailbox_password_text_error'];
   }

   if ($CONF['quota'] == "YES")
   {
      if (!check_quota ($fQuota, $fDomain))
      {
         $error = 1;
         $tName = $fName;
         $tQuota = $fQuota;
         $tActive = $fActive;
         $pEdit_mailbox_quota_text = $PALANG['pEdit_mailbox_quota_text_error'];
      }
   }

   if ($error != 1)
   {
      if (!empty ($fQuota))
      {
         $quota = $fQuota * $CONF['quota_multiplier'];
      }
      else
      {
         $quota = 0;
      }
      
      if ($fActive == "on")
      {
         $fActive = 1;
      }
      else
      {
         $fActive = 0;
      }
      
      if (empty ($fPassword) and empty ($fPassword2))
      {
         $result = db_query ("UPDATE mailbox SET name='$fName',quota='$quota',modified=NOW(),active='$fActive' WHERE username='$fUsername' AND domain='$fDomain'");
      }
      else
      {
         $password = pacrypt ($fPassword);
         $result = db_query ("UPDATE mailbox SET password='$password',name='$fName',quota='$quota',modified=NOW(),active='$fActive' WHERE username='$fUsername' AND domain='$fDomain'");
      }

      if ($result['rows'] != 1)
      {
         $tMessage = $PALANG['pEdit_mailbox_result_error'];
      }
      else
      {
         db_log ($CONF['admin_email'], $fDomain, "edit mailbox", $fUsername);
         
         header ("Location: list-virtual.php?domain=$fDomain");
         exit;
      }
   }
   
   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/edit-mailbox.tpl");
   include ("../templates/footer.tpl");
}
?>
