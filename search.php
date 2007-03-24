<?php
// 
// Postfix Admin 
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// License Info: http://www.postfixadmin.com/?file=LICENSE.TXT
//
// File: search.php
//
// Template File: search.tpl
//
// Template Variables:
//
// tAlias
// tMailbox
//
// Form POST \ GET Variables:
//
// fSearch
//
require ("./variables.inc.php");
require ("./config.inc.php");
require ("./functions.inc.php");
include ("./languages/" . check_language () . ".lang");

$SESSID_USERNAME = check_session();

$tAlias = array();
$tMailbox = array();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   if (isset ($_GET['search'])) $fSearch = escape_string ($_GET['search']);

   if ($CONF['alias_control'] == "YES")
   {
      $query = "SELECT alias.address,alias.goto,alias.modified,alias.domain FROM alias WHERE alias.address LIKE '%$fSearch%' OR alias.goto LIKE '%$fSearch%' ORDER BY alias.address";
   }
   else
   {
      $query = "SELECT alias.address,alias.goto,alias.modified,alias.domain FROM alias LEFT JOIN mailbox ON alias.address=mailbox.username WHERE alias.address LIKE '%$fSearch%' AND mailbox.maildir IS NULL ORDER BY alias.address";
   }

   $result = db_query ("$query");
   
   if ($result['rows'] > 0)
   {
      while ($row = db_array ($result['result']))
      {
         if (check_owner ($SESSID_USERNAME, $row['domain']))
         {
            $tAlias[] = $row;
         }
      }
   }

   $result = db_query ("SELECT * FROM mailbox WHERE username LIKE '%$fSearch%' ORDER BY username");
   if ($result['rows'] > 0)
   {
      while ($row = db_array ($result['result']))
      {
         if (check_owner ($SESSID_USERNAME, $row['domain']))
         {
            $tMailbox[] = $row;
         }
      }
   }

   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/search.tpl");
   include ("./templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   if (isset ($_POST['search'])) $fSearch = escape_string ($_POST['search']);

   if ($CONF['alias_control'] == "YES")
   {
      $query = "SELECT alias.address,alias.goto,alias.modified,alias.domain FROM alias WHERE alias.address LIKE '%$fSearch%' OR alias.goto LIKE '%$fSearch%' ORDER BY alias.address";
   }
   else
   {
      $query = "SELECT alias.address,alias.goto,alias.modified,alias.domain FROM alias LEFT JOIN mailbox ON alias.address=mailbox.username WHERE alias.address LIKE '%$fSearch%' AND mailbox.maildir IS NULL ORDER BY alias.address";
   }

   $result = db_query ("$query");
   
   if ($result['rows'] > 0)
   {
      while ($row = db_array ($result['result']))
      {
         if (check_owner ($SESSID_USERNAME, $row['domain']))
         {
            $tAlias[] = $row;
         }
      }
   }

   $result = db_query ("SELECT * FROM mailbox WHERE username LIKE '%$fSearch%' ORDER BY username");
   if ($result['rows'] > 0)
   {
      while ($row = db_array ($result['result']))
      {
         if (check_owner ($SESSID_USERNAME, $row['domain']))
         {
            $tMailbox[] = $row;
         }
      }
   }

   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/search.tpl");
   include ("./templates/footer.tpl");
}
?>
