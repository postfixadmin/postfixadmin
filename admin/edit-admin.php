<?php
// 
// Postfix Admin 
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// Licensed under GPL for more info check GPL-LICENSE.TXT
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

require_once('../common.php');

authentication_require_role('global-admin');

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   if (isset ($_GET['username'])) $username = escape_string ($_GET['username']);

   $list_domains = list_domains ();
   isset ($_GET['username']) ? $tDomains = list_domains_for_admin ($username) : $tDomains = "";

   $result = db_query ("SELECT * FROM $table_domain_admins WHERE username='$username'");
   if ($result['rows'] == 1)
   {
      $row = db_array ($result['result']);
      if ($row['domain'] == 'ALL') $tSadmin = '1';
   }

   $result = db_query ("SELECT * FROM $table_admin WHERE username='$username'");
   if ($result['rows'] == 1)
   {
      $row = db_array ($result['result']);
      $tActive = $row['active'];
      if ('pgsql'==$CONF['database_type']) $tActive=('t'==$tActive) ? TRUE:FALSE;
   }

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/admin_edit-admin.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   if (isset ($_GET['username'])) $username = escape_string ($_GET['username']);
   
   if (isset ($_POST['fPassword'])) $fPassword = escape_string ($_POST['fPassword']);
   if (isset ($_POST['fPassword2'])) $fPassword2 = escape_string ($_POST['fPassword2']);

   $fActive=(isset($_POST['fActive'])) ? escape_string ($_POST['fActive']) : FALSE;
   $fSadmin=(isset($_POST['fSadmin'])) ? escape_string ($_POST['fSadmin']) : FALSE;

   if (isset ($_POST['fDomains'])) $fDomains = $_POST['fDomains'];

   $list_domains = list_domains ();
   
	if ($fPassword != $fPassword2)
	{
	   $error = 1;
      $tActive = $fActive;
      $tDomains = escape_string ($_POST['fDomains']);
      $pAdminEdit_admin_password_text = $PALANG['pAdminEdit_admin_password_text_error'];
   }

   if ($error != 1)
   {
      if ($fActive == "on") $fActive = 1;
      $sqlActive=$fActive;
      if ('pgsql'==$CONF['database_type']) $sqlActive=($fActive) ? 'true' : 'false';

      if ($fSadmin == "on") $fSadmin = 'ALL';

      if (empty ($fPassword) and empty ($fPassword2))
      {
         $result = db_query ("UPDATE $table_admin SET modified=NOW(),active='$sqlActive' WHERE username='$username'");
      }
      else
      {
         $password = pacrypt ($fPassword);
         $result = db_query ("UPDATE $table_admin SET password='$password',modified=NOW(),active='$sqlActive' WHERE username='$username'");
      }

      if (sizeof ($fDomains) > 0)
      {
         for ($i = 0; $i < sizeof ($fDomains); $i++)
         {
            $domain = $fDomains[$i];
            $result_domains = db_query ("INSERT INTO $table_domain_admins (username,domain,created) VALUES ('$username','$domain',NOW())");
         }
      }

   	if ($result['rows'] == 1)
   	{
         if (isset ($fDomains[0]))
         {
            $result = db_query ("DELETE FROM $table_domain_admins WHERE username='$username'");
            if ($fSadmin == 'ALL')
            {
               $result = db_query ("INSERT INTO $table_domain_admins (username,domain,created) VALUES ('$username','ALL',NOW())");
            }
            else
            {
               if ($fDomains[0] != '')
               for ($i = 0; $i < sizeof ($fDomains); $i++)
               {
                  $domain = $fDomains[$i];
                  $result = db_query ("INSERT INTO $table_domain_admins (username,domain,created) VALUES ('$username','$domain',NOW())");
               }
            }
         }
         header ("Location: list-admin.php");
         exit;
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
/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
