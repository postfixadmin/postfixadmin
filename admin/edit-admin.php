<?php
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
include ("../languages/" . $CONF['language'] . ".lang");

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   $username = $_GET['username'];

   $list_domains = list_domains ();
   $tDomains = list_domains_for_admin ($username);

   $result = db_query ("SELECT * FROM admin WHERE username='$username'");
   if ($result['rows'] == 1)
   {
      $row = mysql_fetch_array ($result['result']);
      $tActive = $row['active'];
   }

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/admin_edit-admin.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $username = $_GET['username'];
   
   $fPassword = $_POST['fPassword'];
   $fPassword2 = $_POST['fPassword2'];
   $fActive = $_POST['fActive'];
   $fDomains = $_POST['fDomains'];

   $list_domains = list_domains ();
   
	if ($fPassword != $fPassword2)
	{
	   $error = 1;
      $tActive = $_POST['fActive'];
      $tDomains = $_POST['fDomains'];
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
         if (sizeof ($fDomains) > 0)
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
