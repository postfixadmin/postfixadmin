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
//
require ("../variables.inc.php");
require ("../config.inc.php");
require ("../functions.inc.php");
include ("../languages/" . check_language () . ".lang");

$SESSID_USERNAME = check_session ();
(!check_admin($SESSID_USERNAME) ? header("Location: " . $CONF['postfix_admin_url'] . "/main.php") && exit : '1');

$tAlias = array();
$tMailbox = array();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   if (isset ($_GET['search'])) $fSearch = escape_string ($_GET['search']);

   if ($CONF['alias_control'] == "YES")
   {
      $query = "SELECT address,goto,modified,domain FROM alias WHERE address LIKE '%$fSearch%' OR goto LIKE '%$fSearch%' ORDER BY address";
      if ('pgsql'==$CONF['database_type'])
      {
         $query = "SELECT address,goto,extract (epoch from modified) as modified,domain FROM alias WHERE address LIKE '%$fSearch%' OR goto LIKE '%$fSearch%' ORDER BY address";
      }
   }
   else
   {
      $query = "SELECT $table_alias.address,$table_alias.goto,$table_alias.modified,$table_alias.domain FROM $table_alias LEFT JOIN $table_mailbox ON $table_alias.address=$table_mailbox.username WHERE $table_alias.address LIKE '%$fSearch%' AND $table_mailbox.maildir IS NULL ORDER BY $table_alias.address";
      if ('pgsql'==$CONF['database_type'])
      {
         $query = "SELECT address,goto,extract(epoch from modified) as modified,domain FROM $table_alias WHERE address LIKE '%$fSearch%' AND NOT EXISTS(SELECT 1 FROM $table_mailbox WHERE username=$table_alias.address) ORDER BY address";
      }
   }

   $result = db_query ("$query");

   if ($result['rows'] > 0)
   {
      while ($row = db_array ($result['result']))
      {
         if ('pgsql'==$CONF['database_type'])
         {
            $row['modified'] = gmstrftime('%c %Z',$row['modified']);
         }
         $tAlias[] = $row;
      }
   }

   $query = "SELECT * FROM $table_mailbox WHERE username LIKE '%$fSearch%' ORDER BY username";
   if ('pgsql'==$CONF['database_type'])
   {
      $query = "SELECT *,extract(epoch from created) as uts_created,extract(epoch from modified) as uts_modified FROM $table_mailbox WHERE username LIKE '%$fSearch%' ORDER BY username";
   }
   $result = db_query ($query);
   if ($result['rows'] > 0)
   {
      while ($row = db_array ($result['result']))
      {
         if ('pgsql'==$CONF['database_type'])
         {
            $row['created']=gmstrftime('%c %Z',$row['uts_created']);
            $row['modified']=gmstrftime('%c %Z',$row['uts_modified']);
            unset($row['uts_created']);
            unset($row['uts_modified']);
         }
         $tMailbox[] = $row;
      }
   }

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/admin_search.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   if (isset ($_POST['search'])) $fSearch = escape_string ($_POST['search']);

   if ($CONF['alias_control'] == "YES")
   {
      $query = "SELECT address,goto,modified,domain FROM alias WHERE address LIKE '%$fSearch%' OR goto LIKE '%$fSearch%' ORDER BY address";
      if ('pgsql'==$CONF['database_type'])
      {
         $query = "SELECT address,goto,extract (epoch from modified) as modified,domain FROM alias WHERE address LIKE '%$fSearch%' OR goto LIKE '%$fSearch%' ORDER BY address";
      }
   }
   else
   {
      $query = "SELECT $table_alias.address,$table_alias.goto,$table_alias.modified,$table_alias.domain FROM $table_alias LEFT JOIN $table_mailbox ON $table_alias.address=$table_mailbox.username WHERE $table_alias.address LIKE '%$fSearch%' AND $table_mailbox.maildir IS NULL ORDER BY $table_alias.address";
      if ('pgsql'==$CONF['database_type'])
      {
         $query = "SELECT $table_alias.address,$table_alias.goto,extract(epoch from $table_alias.modified) as $table_modified,$table_alias.domain FROM $table_alias LEFT JOIN $table_mailbox ON $table_alias.address=$table_mailbox.username WHERE $table_alias.address LIKE '%$fSearch%' AND $table_mailbox.maildir IS NULL ORDER BY $table_alias.address";
      }
   }

   $result = db_query ("$query");

   if ($result['rows'] > 0)
   {
      while ($row = db_array ($result['result']))
      {
         if ('pgsql'==$CONF['database_type'])
         {
            $row['modified'] = gmstrftime('%c %Z',$row['modified']);
         }
         $tAlias[] = $row;
      }
   }

   $query = "SELECT * FROM $table_mailbox WHERE username LIKE '%$fSearch%' ORDER BY username";
   if ('pgsql'==$CONF['database_type'])
   {
      $query = "SELECT *,extract(epoch from created) as uts_created,extract(epoch from modified) as uts_modified FROM $table_mailbox WHERE username LIKE '%$fSearch%' ORDER BY username";
   }
   $result = db_query ("$query");
   if ($result['rows'] > 0)
   {
      while ($row = db_array ($result['result']))
      {
         if ('pgsql'==$CONF['database_type'])
         {
            $row['created']=gmstrftime('%c %Z',$row['uts_created']);
            $row['modified']=gmstrftime('%c %Z',$row['uts_modified']);
            unset($row['uts_created']);
            unset($row['uts_modified']);
         }
         $tMailbox[] = $row;
      }
   }

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/admin_search.tpl");
   include ("../templates/footer.tpl");
}
/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
