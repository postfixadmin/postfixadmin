<?php
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
require ("../variables.inc.php");
require ("../config.inc.php");
require ("../functions.inc.php");
include ("../languages/" . check_language () . ".lang");

$list_admins = list_admins ();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   if (isset ($_GET['username']))
   {
      $fUsername = $_GET['username'];

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
   $fUsername = $_POST['fUsername'];

   $list_domains = list_domains_for_admin ($fUsername);
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
