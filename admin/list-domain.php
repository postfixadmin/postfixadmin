<?php
// 
// Postfix Admin 
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// Licensed under GPL for more info check GPL-LICENSE.TXT
//
// File: list-domain.php
//
// Template File: admin_list-domain.tpl
//
// Template Variables:
//
// -none-
//
// Form POST \ GET Variables:
//
// fUsername
//

require_once('../common.php');

authentication_require_role('global-admin');

$list_admins = list_admins ();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   if (isset ($_GET['username']))
   {
      $fUsername = escape_string ($_GET['username']);

      $list_domains = list_domains_for_admin ($fUsername);
      if ($list_domains != 0)
      {
         for ($i = 0; $i < sizeof ($list_domains); $i++)
         {
            $domain_properties[$i] = get_domain_properties ($list_domains[$i]);
         }
      }
   }
   else
   {
      $list_domains = list_domains ();
      if ((is_array ($list_domains) and sizeof ($list_domains) > 0))
         for ($i = 0; $i < sizeof ($list_domains); $i++)
         {
            $domain_properties[$i] = get_domain_properties ($list_domains[$i]);
         }
      }

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/admin_list-domain.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   if (isset ($_POST['fUsername']))
   {
      $fUsername = escape_string ($_POST['fUsername']);
      $list_domains = list_domains_for_admin ($fUsername);
   }

   if (!empty ($list_domains))
   {
      for ($i = 0; $i < sizeof ($list_domains); $i++)
      {
         $domain_properties[$i] = get_domain_properties ($list_domains[$i]);
      }
   }

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/admin_list-domain.tpl");
   include ("../templates/footer.tpl");
}
?>
