<?php
// 
// Postfix Admin 
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// Licensed under GPL for more info check GPL-LICENSE.TXT
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
require ("../variables.inc.php");
require ("../config.inc.php");
require ("../functions.inc.php");
include ("../languages/" . check_language () . ".lang");

$SESSID_USERNAME = check_session ();
(!check_admin($SESSID_USERNAME) ? header("Location: " . $CONF['postfix_admin_url'] . "/main.php") && exit : '1');

$list_domains = list_domains ();

$tAlias = array();
$tMailbox = array();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   $fDisplay = 0;
   $page_size = $CONF['page_size'];
   
   if (isset ($_GET['domain'])) $fDomain = escape_string ($_GET['domain']);
   if (isset ($_GET['limit'])) $fDisplay = intval ($_GET['limit']);

   if ((is_array ($list_domains) and sizeof ($list_domains) > 0)) if (empty ($fDomain)) $fDomain = $list_domains[0];
   
   $limit = get_domain_properties ($fDomain);

   if ((is_array ($list_domains) and sizeof ($list_domains) > 0)) if (empty ($fDomain)) $fDomain = $list_domains[1];
   
   $query = "SELECT $table_alias.address,$table_alias.goto,$table_alias.modified,$table_alias.active FROM $table_alias LEFT JOIN $table_mailbox ON $table_alias.address=$table_mailbox.username WHERE $table_alias.domain='$fDomain' AND $table_mailbox.maildir IS NULL ORDER BY $table_alias.address LIMIT $fDisplay, $page_size";
   if ('pgsql'==$CONF['database_type'])
   {
      $query = "SELECT address,goto,extract(epoch from modified) as modified,active FROM $table_alias WHERE domain='$fDomain' AND NOT EXISTS(SELECT 1 FROM $table_mailbox WHERE username=$table_alias.address) ORDER BY address LIMIT $page_size OFFSET $fDisplay";
   }

   $result = db_query ($query);
   if ($result['rows'] > 0)
   {
      while ($row = db_array ($result['result']))
      {
         if ('pgsql'==$CONF['database_type'])
         {
            $row['modified']=gmstrftime('%c %Z',$row['modified']);
            $row['active']=('t'==$row['active']) ? 1 : 0;
         }
         $tAlias[] = $row;
      }
   }

   $query = "SELECT * FROM $table_mailbox WHERE domain='$fDomain' ORDER BY username LIMIT $fDisplay, $page_size";
   if ('pgsql'==$CONF['database_type'])
   {
      $query = "SELECT *,extract(epoch from created) as uts_created,extract(epoch from modified) as uts_modified FROM $table_mailbox WHERE domain='$fDomain' ORDER BY username LIMIT $page_size OFFSET $fDisplay";
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
            $row['active']=('t'==$row['active']) ? 1 : 0;
         }
         $tMailbox[] = $row;
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

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/admin_list-virtual.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $fDisplay = 0;
   $page_size = $CONF['page_size'];

   if (isset ($_POST['fDomain'])) $fDomain = escape_string ($_POST['fDomain']);
   if (isset ($_POST['limit'])) $fDisplay = intval ($_POST['limit']);

   $limit = get_domain_properties ($fDomain);

   if ($CONF['alias_control'] == "YES")
   {
      $query = "SELECT address,goto,modified,active FROM alias WHERE domain='$fDomain' ORDER BY address LIMIT $fDisplay, $page_size";
      if ('pgsql'==$CONF['database_type'])
      {
         $query = "SELECT address,goto,extract(epoch from modified) as modified,active FROM alias WHERE domain='$fDomain' ORDER BY alias.address LIMIT $page_size OFFSET $fDisplay";
      }
   }
   else
   {
      $query = "SELECT $table_alias.address,$table_alias.goto,$table_alias.modified,$table_alias.active FROM $table_alias LEFT JOIN $table_mailbox ON $table_alias.address=$table_mailbox.username WHERE $table_alias.domain='$fDomain' AND $table_mailbox.maildir IS NULL ORDER BY $table_alias.address LIMIT $fDisplay, $page_size";
      if ('pgsql'==$CONF['database_type'])
      {
         $query = "SELECT $table_alias.address,$table_alias.goto,extract(epoch from $table_alias.modified) as modified,$table_alias.active FROM $table_alias LEFT JOIN $table_mailbox ON $table_alias.address=$table_mailbox.username WHERE $table_alias.domain='$fDomain' AND $table_mailbox.maildir IS NULL ORDER BY $table_alias.address LIMIT $page_size OFFSET $fDisplay";
      }
   }

   $result = db_query ($query);
   if ($result['rows'] > 0)
   {
      while ($row = db_array ($result['result']))
      {
         if ('pgsql'==$CONF['database_type'])
         {
            $row['modified']=gmstrftime('%c %Z',$row['modified']);
            $row['active']=('t'==$row['active']) ? 1 : 0;
         }
         $tAlias[] = $row;
      }
   }

   $query="SELECT * FROM $table_mailbox WHERE domain='$fDomain' ORDER BY username LIMIT $fDisplay, $page_size";
   if ('pgsql'==$CONF['database_type'])
   {
      $query="SELECT *,extract(epoch from created) as uts_created,extract(epoch from modified) as uts_modified FROM $table_mailbox WHERE domain='$fDomain' ORDER BY username LIMIT $page_size OFFSET $fDisplay";
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
            $row['active']=('t'==$row['active']) ? 1 : 0;
         }
         $tMailbox[] = $row;
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

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/admin_list-virtual.tpl");
   include ("../templates/footer.tpl");
/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
}
?>
