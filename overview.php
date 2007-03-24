<?php
// 
// Postfix Admin 
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// License Info: http://www.postfixadmin.com/?file=LICENSE.TXT
//
// File: overview.php
//
// Template File: overview.tpl
//
// Template Variables:
//
// tAlias
// tDomain
// tMailbox
// tDisplay_back
// tDisplay_next
//
// Form POST \ GET Variables:
//
// domain
// fDomain
// limit
//
require ("./variables.inc.php");
require ("./config.inc.php");
require ("./functions.inc.php");
include ("./languages/" . check_language () . ".lang");

$SESSID_USERNAME = check_session();
$list_domains = list_domains_for_admin ($SESSID_USERNAME);

$tAlias = array();
$tMailbox = array();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   $fDisplay = 0;
   $page_size = $CONF['page_size'];
   
   if (isset ($_GET['domain'])) $fDomain = escape_string ($_GET['domain']);
   if (isset ($_GET['limit'])) $fDisplay = escape_string ($_GET['limit']);

   if (check_owner ($SESSID_USERNAME, $fDomain))
   {
      $limit = get_domain_properties ($fDomain);
   
      if ($CONF['alias_control'] == "YES")
      {
         $query = "SELECT alias.address,alias.goto,alias.modified FROM alias WHERE alias.domain='$fDomain' ORDER BY alias.address LIMIT $fDisplay, $page_size";
      }
      else
      {
         $query = "SELECT alias.address,alias.goto,alias.modified FROM alias LEFT JOIN mailbox ON alias.address=mailbox.username WHERE alias.domain='$fDomain' AND mailbox.maildir IS NULL ORDER BY alias.address LIMIT $fDisplay, $page_size";
      }

      $result = db_query ("$query");
      if ($result['rows'] > 0)
      {
         while ($row = db_array ($result['result']))
         {
            $tAlias[] = $row;
         }
      }

      $result = db_query ("SELECT * FROM mailbox WHERE domain='$fDomain' ORDER BY username LIMIT $fDisplay, $page_size");
      if ($result['rows'] > 0)
      {
         while ($row = db_array ($result['result']))
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

   $tDomain = $fDomain;

   if (isset ($limit))
   {
      if ($fDisplay >= $page_size)
      {
         $tDisplay_back_show = 1;
         $tDisplay_back = $fDisplay - $page_size;
      }
      if (($limit['alias_count'] > $page_size) or ($limit['mailbox_count'] > $page_size))
      {
         $tDisplay_up_show = 1;
      }      
      if ((($fDisplay + $page_size) < $limit['alias_count']) or (($fDisplay + $page_size) < $limit['mailbox_count']))
      {
         $tDisplay_next_show = 1;
         $tDisplay_next = $fDisplay + $page_size;
      }
   }
   
   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/$template");
   include ("./templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $fDisplay = 0;
   $page_size = $CONF['page_size'];
   
   if (isset ($_POST['limit'])) $fDisplay = escape_string ($_POST['limit']);
   
   if (check_owner ($SESSID_USERNAME, escape_string ($_POST['fDomain'])))
   {
      $fDomain = escape_string ($_POST['fDomain']);   

      $limit = get_domain_properties ($fDomain);
   
      if ($CONF['alias_control'] == "YES")
      {
         $query = "SELECT alias.address,alias.goto,alias.modified FROM alias WHERE alias.domain='$fDomain' ORDER BY alias.address LIMIT $fDisplay, $page_size";
      }
      else
      {
         $query = "SELECT alias.address,alias.goto,alias.modified FROM alias LEFT JOIN mailbox ON alias.address=mailbox.username WHERE alias.domain='$fDomain' AND mailbox.maildir IS NULL ORDER BY alias.address LIMIT $fDisplay, $page_size";
      }

      $result = db_query ("$query");
      if ($result['rows'] > 0)
      {
         while ($row = db_array ($result['result']))
         {
            $tAlias[] = $row;
         }
      }

      $result = db_query ("SELECT * FROM mailbox WHERE domain='$fDomain' ORDER BY username LIMIT $fDisplay, $page_size");
      if ($result['rows'] > 0)
      {
         while ($row = db_array ($result['result']))
         {
            $tMailbox[] = $row;
         }
      }
   }

   if (isset ($limit))
   {
      if ($fDisplay >= $page_size)
      {
         $tDisplay_back_show = 1;
         $tDisplay_back = $fDisplay - $page_size;
      }
      if (($limit['alias_count'] > $page_size) or ($limit['mailbox_count'] > $page_size))
      {
         $tDisplay_up_show = 1;
      }
      if ((($fDisplay + $page_size) < $limit['alias_count']) or (($fDisplay + $page_size) < $limit['mailbox_count']))
      {
         $tDisplay_next_show = 1;
         $tDisplay_next = $fDisplay + $page_size;
      }
   }

   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/overview.tpl");
   include ("./templates/footer.tpl");
}
?>
