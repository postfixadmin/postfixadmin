<?php
//
// Postfix Admin
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// Licensed under GPL for more info check GPL-LICENSE.TXT
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
// fGo
// fDomain
//
require ("./variables.inc.php");
require ("./config.inc.php");
require ("./functions.inc.php");
include ("./languages/" . check_language () . ".lang");

$SESSID_USERNAME = check_session();
if (!check_admin($SESSID_USERNAME))
{
   $list_domains = list_domains_for_admin ($SESSID_USERNAME);
}
else
{
   $list_domains = list_domains ();
}


$tAlias = array();
$tMailbox = array();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   if (isset ($_GET['search'])) $fSearch = escape_string ($_GET['search']);

   if ($CONF['alias_control_admin'] == "YES")
   {
      $query = "SELECT $table_alias.address,$table_alias.goto,$table_alias.modified,$table_alias.domain,$table_alias.active FROM $table_alias WHERE $table_alias.address LIKE '%$fSearch%' OR $table_alias.goto LIKE '%$fSearch%' ORDER BY $table_alias.address";
   }
   else
   {
      $query = "SELECT $table_alias.address,$table_alias.goto,$table_alias.modified,$table_alias.domain,$table_alias.active FROM $table_alias LEFT JOIN $table_mailbox ON $table_alias.address=$table_mailbox.username WHERE $table_alias.address LIKE '%$fSearch%' AND $table_mailbox.maildir IS NULL ORDER BY $table_alias.address";
   }

   $result = db_query ($query);
   if ($result['rows'] > 0)
   {
      while ($row = db_array ($result['result']))
      {
         if (check_owner ($SESSID_USERNAME, $row['domain']))
         {
            if ('pgsql'==$CONF['database_type'])
            {
               $row['modified']=gmstrftime('%c %Z',$row['modified']);
               $row['active']=('t'==$row['active']) ? 1 : 0;
            }         	
            $tAlias[] = $row;
         }
      }
   }

   if ($CONF['vacation_control_admin'] == 'YES')
   {
      $query = ("SELECT $table_mailbox.*, $table_vacation.active AS v_active FROM $table_mailbox LEFT JOIN $table_vacation ON $table_mailbox.username=$table_vacation.email WHERE $table_mailbox.username LIKE '%$fSearch%' OR $table_mailbox.name LIKE '%$fSearch%' ORDER BY $table_mailbox.username");
   }
   else
   {
      $query = "SELECT * FROM $table_mailbox WHERE username LIKE '%$fSearch%' OR name LIKE '%$fSearch%' ORDER BY username";
   }

   $result = db_query ($query);
   if ($result['rows'] > 0)
   {
      while ($row = db_array ($result['result']))
      {
         if (check_owner ($SESSID_USERNAME, $row['domain']))
         {
            if ('pgsql'==$CONF['database_type'])
            {
               $row['modified']=gmstrftime('%c %Z',$row['modified']);
               $row['active']=('t'==$row['active']) ? 1 : 0;
            }         	
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
   if (isset ($_POST['fGo'])) $fGo = escape_string ($_POST['fGo']);
   if (isset ($_POST['fDomain'])) $fDomain = escape_string ($_POST['fDomain']);

   if (empty ($fSearch) && !empty ($fGo))
   {
      if (check_admin($SESSID_USERNAME))
      {
        header("Location: list-virtual.php?domain=" . $fDomain ) && exit;
      }
      else
      {
        header("Location: overview.php?domain=" . $fDomain ) && exit;
      }
   }


   if ($CONF['alias_control_admin'] == "YES")
   {
      $query = "SELECT $table_alias.address,$table_alias.goto,$table_alias.modified,$table_alias.domain,$table_alias.active FROM $table_alias WHERE $table_alias.address LIKE '%$fSearch%' OR $table_alias.goto LIKE '%$fSearch%' ORDER BY $table_alias.address";
   }
   else
   {
      $query = "SELECT $table_alias.address,$table_alias.goto,$table_alias.modified,$table_alias.domain,$table_alias.active FROM $table_alias LEFT JOIN $table_mailbox ON $table_alias.address=$table_mailbox.username WHERE $table_alias.address LIKE '%$fSearch%' AND $table_mailbox.maildir IS NULL ORDER BY $table_alias.address";
   }

   $result = db_query ($query);
   if ($result['rows'] > 0)
   {
      while ($row = db_array ($result['result']))
      {
         if (check_owner ($SESSID_USERNAME, $row['domain']))
         {
            if ('pgsql'==$CONF['database_type'])
            {
               $row['modified']=gmstrftime('%c %Z',$row['modified']);
               $row['active']=('t'==$row['active']) ? 1 : 0;
            }
            $tAlias[] = $row;
         }
      }
   }

   if ($CONF['vacation_control_admin'] == 'YES')
   {
      $query = ("SELECT $table_mailbox.*, $table_vacation.active AS v_active FROM $table_mailbox LEFT JOIN $table_vacation ON $table_mailbox.username=$table_vacation.email WHERE $table_mailbox.username LIKE '%$fSearch%' OR $table_mailbox.name LIKE '%$fSearch%' ORDER BY $table_mailbox.username");
   }
   else
   {
      $query = "SELECT * FROM $table_mailbox WHERE username LIKE '%$fSearch%' OR name LIKE '%$fSearch%' ORDER BY username";
   }

   $result = db_query ("$query");
   if ($result['rows'] > 0)
   {
      while ($row = db_array ($result['result']))
      {
         if (check_owner ($SESSID_USERNAME, $row['domain']))
         {
            if ('pgsql'==$CONF['database_type'])
            {
               $row['modified']=gmstrftime('%c %Z',$row['modified']);
               $row['active']=('t'==$row['active']) ? 1 : 0;
            }         	
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
