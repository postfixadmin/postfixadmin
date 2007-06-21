<?php
//
// Postfix Admin
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// Licensed under GPL for more info check GPL-LICENSE.TXT
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
   $fDisplay = 0;
   $page_size = $CONF['page_size'];

   if (isset ($_GET['domain'])) $fDomain = escape_string ($_GET['domain']);
   if (isset ($_GET['limit'])) $fDisplay = escape_string ($_GET['limit']);

   $limit = get_domain_properties ($fDomain);

   $limitSql=('pgsql'==$CONF['database_type']) ? "$page_size OFFSET $fDisplay" : "$fDisplay, $page_size";

   if (check_owner ($SESSID_USERNAME, $fDomain))
   {
         $query = "SELECT $table_alias.address,$table_alias.goto,$table_alias.modified,$table_alias.active FROM $table_alias LEFT JOIN $table_mailbox ON $table_alias.address=$table_mailbox.username WHERE $table_alias.domain='$fDomain' AND $table_mailbox.maildir IS NULL ORDER BY $table_alias.address LIMIT $limitSql";
         if ('pgsql'==$CONF['database_type'])
         {
            $query = "SELECT address,goto,extract(epoch from modified) as modified,active FROM $table_alias WHERE domain='$fDomain' AND NOT EXISTS(SELECT 1 FROM $table_mailbox WHERE username=$table_alias.address) ORDER BY address LIMIT $limitSql";
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

      if ($CONF['vacation_control_admin'] == 'YES')
      {
         $query = ("SELECT $table_mailbox.*, $table_vacation.active AS v_active FROM $table_mailbox LEFT JOIN $table_vacation ON $table_mailbox.username=$table_vacation.email WHERE $table_mailbox.domain='$fDomain' ORDER BY $table_mailbox.username LIMIT $limitSql");
         if ('pgsql'==$CONF['database_type'])
         {
            //FIXME: postgres query needs to be rewrited
            $query = "SELECT *,extract(epoch from created) as uts_created,extract(epoch from modified) as uts_modified FROM $table_mailbox WHERE domain='$fDomain' ORDER BY username LIMIT $limitSql";
         }
      }
      else
      {
         $query = "SELECT * FROM $table_mailbox WHERE domain='$fDomain' ORDER BY username LIMIT $limitSql";
         if ('pgsql'==$CONF['database_type'])
         {
            $query = "SELECT *,extract(epoch from created) as uts_created,extract(epoch from modified) as uts_modified FROM $table_mailbox WHERE domain='$fDomain' ORDER BY username LIMIT $limitSql";
         }
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
               $row['v_active']=('t'==$row['v_active']) ? 1 : 0;
               unset($row['uts_created']);
               unset($row['uts_modified']);
            }
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
   if (isset ($_POST['domain'])) $fDomain = escape_string ($_POST['fDomain']);

   if (check_owner ($SESSID_USERNAME, escape_string ($_POST['fDomain'])))
   {
      $limitSql=('pgsql'==$CONF['database_type']) ? "$page_size OFFSET $fDisplay" : "$fDisplay, $page_size";

      if ($CONF['alias_control_admin'] == "YES")
      {
         $query = "SELECT address,goto,modified,active FROM alias WHERE domain='$fDomain' ORDER BY address LIMIT $limitSql";
         if ('pgsql'==$CONF['database_type'])
         {
            $query = "SELECT address,goto,extract(epoch from modified) as modified,active FROM alias WHERE domain='$fDomain' ORDER BY address LIMIT $limitSql";
         }
      }
      else
      {
         $query = "SELECT $table_alias.address,$table_alias.goto,$table_alias.modified,$table_alias.active FROM $table_alias LEFT JOIN $table_mailbox ON $table_alias.address=$table_mailbox.username WHERE $table_alias.domain='$fDomain' AND $table_mailbox.maildir IS NULL ORDER BY $table_alias.address LIMIT $limitSql";
         if ('pgsql'==$CONF['database_type'])
         {
            $query="SELECT address,goto,extract(epoch from modified) as modified,active FROM $table_alias WHERE domain='$fDomain' AND NOT EXISTS(SELECT 1 FROM $table_mailbox WHERE username=$table_alias.address) ORDER BY address LIMIT $limitSql";
         }
      }

      $result = db_query ("$query");
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

      $query = "SELECT * FROM $table_mailbox WHERE domain='$fDomain' ORDER BY username LIMIT $limitSql";
      if ('pgsql'==$CONF['database_type'])
      {
         $query = "SELECT *,,extract(epoch from created) as uts_created,extract(epoch from modified) as uts_modified FROM $table_mailbox WHERE domain='$fDomain' ORDER BY username LIMIT $limitSql";
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
/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
