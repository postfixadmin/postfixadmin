<?php
// 
// Postfix Admin 
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// License Info: http://www.postfixadmin.com/?file=LICENSE.TXT
//
// File: edit-admin.php
//
// Template File: admin_edit-admin.tpl
//
// Template Variables:
//
// tDescription
// tAliases
// tMailboxes
// tMaxquota
// tActive
//
// Form POST \ GET Variables:
//
// fDescription
// fAliases
// fMailboxes
// fMaxquota
// fActive
//
require ("../variables.inc.php");
require ("../config.inc.php");
require ("../functions.inc.php");
include ("../languages/" . check_language () . ".lang");

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   $username = escape_string ($_GET['username']);

   $list_domains = list_domains ();
   $tDomains = list_domains_for_admin ($username);

   $result = db_query ("SELECT * FROM admin WHERE username='$username'");
   if ($result['rows'] == 1)
   {
      $row = db_array ($result['result']);
      $tActive = $row['active'];
   }

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/admin_edit-admin.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $username = escape_string ($_GET['username']);
   
   $fPassword = escape_string ($_POST['fPassword']);
   $fPassword2 = escape_string ($_POST['fPassword2']);
   $fActive = escape_string ($_POST['fActive']);
   if (isset ($_POST['fDomains'])) $fDomains = $_POST['fDomains'];


   $list_domains = list_domains ();
   
	if ($fPassword != $fPassword2)
	{
	   $error = 1;
      $tActive = escape_string ($_POST['fActive']);
      $tDomains = escape_string ($_POST['fDomains']);
      $pAdminEdit_admin_password_text = $PALANG['pAdminEdit_admin_password_text_error'];
   }

   if ($error != 1)
   {

      if (empty ($fPassword) and empty ($fPassword2))
      {
         if ($fActive == "on") $fActive = 1;
         $result = db_query ("UPDATE admin SET modified=NOW(),active='$fActive' WHERE username='$username'");
      }
      else
      {
         $password = pacrypt ($fPassword);
         if ($fActive == "on") $fActive = 1;
         $result = db_query ("UPDATE admin SET password='$password',modified=NOW(),active='$fActive' WHERE username='$username'");
      }

      if (sizeof ($fDomains) > 0)
      {
         for ($i = 0; $i < sizeof ($fDomains); $i++)
         {
            $domain = $fDomains[$i];
            $result_domains = db_query ("INSERT INTO domain_admins (username,domain,created) VALUES ('$username','$domain',NOW())");
         }
      }

   	if ($result['rows'] == 1)
   	{
         if (isset ($fDomains[0]))
         {
            $result = db_query ("DELETE FROM domain_admins WHERE username='$username'");
            for ($i = 0; $i < sizeof ($fDomains); $i++)
            {
               $domain = $fDomains[$i];
               $result = db_query ("INSERT INTO domain_admins (username,domain,created) VALUES ('$username','$domain',NOW())");
            }
         }
         header ("Location: list-admin.php");
   	}
   	else
   	{
   	   $tMessage = $PALANG['pAdminEdit_admin_result_error'];
   	}
	}
	
   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/admin_edit-admin.tpl");
   include ("../templates/footer.tpl");
}
?>
