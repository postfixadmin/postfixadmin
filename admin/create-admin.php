<?php
// 
// Postfix Admin 
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// Licensed under GPL for more info check GPL-LICENSE.TXT
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

require_once('../common.php');

authentication_require_role('global-admin');

$list_domains = list_domains ();
$tDomains = array();

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
   if (isset ($_POST['fUsername'])) $fUsername = escape_string ($_POST['fUsername']);
   if (isset ($_POST['fPassword'])) $fPassword = escape_string ($_POST['fPassword']);
   if (isset ($_POST['fPassword2'])) $fPassword2 = escape_string ($_POST['fPassword2']);
   $fDomains = array();
   if (!empty ($_POST['fDomains'])) $fDomains = $_POST['fDomains'];

   if (!check_email ($fUsername))
   {
      $error = 1;
      if (isset ($_POST['fUsername'])) $tUsername = escape_string ($_POST['fUsername']);
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
      
   if (empty ($fPassword) or empty ($fPassword2) or ($fPassword != $fPassword2))
   {
      if (empty ($fPassword) and empty ($fPassword2) and $CONF['generate_password'] == "YES")
      {
			$fPassword = generate_password ();
      }
      else
      {
			$error = 1;
			if (isset ($_POST['fUsername'])) $tUsername = escape_string ($_POST['fUsername']);
			if (isset ($_POST['fDomains'])) $tDomains = $_POST['fDomains'];
			$pAdminCreate_admin_username_text = $PALANG['pAdminCreate_admin_username_text'];
			$pAdminCreate_admin_password_text = $PALANG['pAdminCreate_admin_password_text_error'];
      }
   }

   if ($error != 1)
   {
   	$password = pacrypt($fPassword);
      $pAdminCreate_admin_username_text = $PALANG['pAdminCreate_admin_username_text'];


      $result = db_query ("INSERT INTO $table_admin (username,password,created,modified) VALUES ('$fUsername','$password',NOW(),NOW())");
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
               $result = db_query ("INSERT INTO $table_domain_admins (username,domain,created) VALUES ('$fUsername','$domain',NOW())");
            }
         }
			$tMessage = $PALANG['pAdminCreate_admin_result_succes'] . "<br />($fUsername";
			if ($CONF['generate_password'] == "YES")
			{
				$tMessage .= " / $fPassword)</br />";
			}
			else
			{
				if ($CONF['show_password'] == "YES")
				{
					$tMessage .= " / $fPassword)</br />";
				}
				else
				{
					$tMessage .= ")</br />";
				}
			}
		}
	}

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/admin_create-admin.tpl");
   include ("../templates/footer.tpl");
}
/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
