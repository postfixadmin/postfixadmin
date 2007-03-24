<?php
//
// File: list-virtual.php
//
// Template File: admin_list-virtual.tpl
//
// Template Variables:
//
// tMessage
// tAlias
// tMailbox
//
// Form POST \ GET Variables:
//
// fDomain
//
require ("../config.inc.php");
require ("../functions.inc.php");
include ("../languages/" . $CONF['language'] . ".lang");

$list_domains = list_domains ();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   $fDomain = $_GET['domain'];
   
   if (empty ($fDomain)) $fDomain = $list_domains[0];
   
   $limit = get_domain_properties ($fDomain);

   if (empty ($fDomain)) $fDomain = $list_domains[1];
   
   if ($CONF['alias_control'] == "YES")
   {
      $query = "SELECT alias.address,alias.goto,alias.modified FROM alias WHERE alias.domain='$fDomain' ORDER BY alias.address";
   }
   else
   {
      $query = "SELECT alias.address,alias.goto,alias.modified FROM alias LEFT JOIN mailbox ON alias.address=mailbox.username WHERE alias.domain='$fDomain' AND mailbox.maildir IS NULL ORDER BY alias.address";
   }

   $result = db_query ("$query");
   if ($result['rows'] > 0)
   {
      while ($row = mysql_fetch_array ($result['result']))
      {
         $tAlias[] = $row;
      }
   }

   $result = db_query ("SELECT * FROM mailbox WHERE domain='$fDomain' ORDER BY username");
   if ($result['rows'] > 0)
   {
      while ($row = mysql_fetch_array ($result['result']))
      {
         $tMailbox[] = $row;
      }
   }

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/admin_list-virtual.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $fDomain = $_POST['fDomain'];   

   $limit = get_domain_properties ($fDomain);

   if ($CONF['alias_control'] == "YES")
   {
      $query = "SELECT alias.address,alias.goto,alias.modified FROM alias WHERE alias.domain='$fDomain' ORDER BY alias.address";
   }
   else
   {
      $query = "SELECT alias.address,alias.goto,alias.modified FROM alias LEFT JOIN mailbox ON alias.address=mailbox.username WHERE alias.domain='$fDomain' AND mailbox.maildir IS NULL ORDER BY alias.address";
   }

   $result = db_query ("$query");
   if ($result['rows'] > 0)
   {
      while ($row = mysql_fetch_array ($result['result']))
      {
         $tAlias[] = $row;
      }
   }

   $result = db_query ("SELECT * FROM mailbox WHERE domain='$fDomain' ORDER BY username");
   if ($result['rows'] > 0)
   {
      while ($row = mysql_fetch_array ($result['result']))
      {
         $tMailbox[] = $row;
      }
   }

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/admin_list-virtual.tpl");
   include ("../templates/footer.tpl");
}
?>
