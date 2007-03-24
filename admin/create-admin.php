<?php
// 
// Postfix Admin 
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// License Info: http://www.postfixadmin.com/?file=LICENSE.TXT
//
// File: create-admin.php
//
// Template File: admin_create-admin.tpl
//
//
// Template Variables:
//
// tMessage
// tUsername
// tDomains
//
// Form POST \ GET Variables:
//
// fUsername
// fPassword
// fPassword2
// fDomains
//
require ("../variables.inc.php");
require ("../config.inc.php");
require ("../functions.inc.php");
include ("../languages/" . check_language () . ".lang");

$list_domains = list_domains ();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   $pAdminCreate_admin_username_text = $PALANG['pAdminCreate_admin_username_text'];
   $tDomains = array ();

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/admin_create-admin.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
	$fUsername = escape_string ($_POST['fUsername']);
	$fPassword = escape_string ($_POST['fPassword']);
	$fPassword2 = escape_string ($_POST['fPassword2']);
	if (!empty ($_POST['fDomains'])) $fDomains = $_POST['fDomains'];

   if (!check_email ($fUsername))
   {
      $error = 1;
      $tUsername = escape_string ($_POST['fUsername']);
      if (isset ($_POST['fDomains'])) $tDomains = $_POST['fDomains'];
      $pAdminCreate_admin_username_text = $PALANG['pAdminCreate_admin_username_text_error1'];
   }

   if (empty ($fUsername) or admin_exist ($fUsername))
   {
      $error = 1;
      $tUsername = escape_string ($_POST['fUsername']);
      if (isset ($_POST['fDomains'])) $tDomains = $_POST['fDomains'];
      $pAdminCreate_admin_username_text = $PALANG['pAdminCreate_admin_username_text_error2'];
   }
      
   if (empty ($fPassword) or ($fPassword != $fPassword2))
	{
      $error = 1;
      $tUsername = escape_string ($_POST['fUsername']);
      if (isset ($_POST['fDomains'])) $tDomains = $_POST['fDomains'];
      $pAdminCreate_admin_username_text = $PALANG['pAdminCreate_admin_username_text'];
      $pAdminCreate_admin_password_text = $PALANG['pAdminCreate_admin_password_text_error'];
	}

   if ($error != 1)
   {
   	$password = pacrypt("$fPassword");
      $pAdminCreate_admin_username_text = $PALANG['pAdminCreate_admin_username_text'];


      $result = db_query ("INSERT INTO admin (username,password,created,modified) VALUES ('$fUsername','$password',NOW(),NOW())");
      if ($result['rows'] != 1)
      {
         $tMessage = $PALANG['pAdminCreate_admin_result_error'] . "<br />($fUsername)<br />";
      }
      else
      {
         if (!empty ($fDomains[0]))
         {
            for ($i = 0; $i < sizeof ($fDomains); $i++)
            {
               $domain = $fDomains[$i];
               $result = db_query ("INSERT INTO domain_admins (username,domain,created) VALUES ('$fUsername','$domain',NOW())");
            }
         }
         $tMessage = $PALANG['pAdminCreate_admin_result_succes'] . "<br />($fUsername)</br />";
      }
   }

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/admin_create-admin.tpl");
   include ("../templates/footer.tpl");
}
?>
