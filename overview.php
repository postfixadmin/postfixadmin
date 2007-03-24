<?php
//
// File: overview.php
//
// Template File: overview.tpl
//
// Template Variables:
//
// tAlias
// tMailbox
//
// Form POST \ GET Variables:
//
// fDomain
//
require ("./config.inc.php");
require ("./functions.inc.php");
include ("./languages/" . $CONF['language'] . ".lang");

$SESSID_USERNAME = check_session();
$list_domains = list_domains_for_admin ($SESSID_USERNAME);

if ($_SERVER['REQUEST_METHOD'] == "GET")
{

   $fDomain = $_GET['domain'];

   if (check_owner ($SESSID_USERNAME, $fDomain))
   {
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
      $template = "overview.tpl";
   }
   else
   {
      $template = "overview-get.tpl";
   }

   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/$template");
   include ("./templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   if (check_owner ($SESSID_USERNAME, $_POST['fDomain']))
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
   }

   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/overview.tpl");
   include ("./templates/footer.tpl");
}
?>
