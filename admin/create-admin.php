<?php
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
include ("../languages/" . $CONF['language'] . ".lang");

$list_domains = list_domains ();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   $pAdminCreate_admin_username_text = $PALANG['pAdminCreate_admin_username_text'];
   $tDomains[] = "";

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/admin_create-admin.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
	$fUsername = $_POST['fUsername'];
	$fPassword = $_POST['fPassword'];
	$fPassword2 = $_POST['fPassword2'];
	$fDomains = $_POST['fDomains'];

   if (!check_email ($fUsername))
   {
      $error = 1;
      $tUsername = $_POST['fUsername'];
      $tDomains = $_POST['fDomains'];
      $pAdminCreate_admin_username_text = $PALANG['pAdminCreate_admin_username_text_error1'];
   }

   if (empty ($fUsername) or admin_exist ($fUsername))
   {
      $error = 1;
      $tUsername = $_POST['fUsername'];
      $tDomains = $_POST['fDomains'];
      $pAdminCreate_admin_username_text = $PALANG['pAdminCreate_admin_username_text_error2'];
   }
      
   if (empty ($fPassword) or ($fPassword != $fPassword2))
	{
      $error = 1;
      $tUsername = $_POST['fUsername'];
      $tDomains = $_POST['fDomains'];
      $pAdminCreate_admin_username_text = $PALANG['pAdminCreate_admin_username_text'];
      $pAdminCreate_admin_password_text = $PALANG['pAdminCreate_admin_password_text_error'];
	}

   if ($error != 1)
   {
   	$password = md5crypt("$fPassword");

      $result = db_query ("INSERT INTO admin (username,password,created,modified) VALUES ('$fUsername','$password',NOW(),NOW())");
      if ($result['rows'] != 1)
      {
         $tMessage = $PALANG['pAdminCreate_admin_result_error'] . "<br />($fUsername)<br />";
      }
      else
      {
         if (sizeof ($fDomains) > 0)
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
