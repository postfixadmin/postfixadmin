<?php
/** 
 * Postfix Admin 
 * 
 * LICENSE 
 * This source file is subject to the GPL license that is bundled with  
 * this package in the file LICENSE.TXT. 
 * 
 * Further details on the project are available at : 
 *     http://www.postfixadmin.com or http://postfixadmin.sf.net 
 * 
 * @version $Id$ 
 * @license GNU GPL v2 or later. 
 * 
 * File: search.php
 * Allows for search by e.g. name, mailbox name etc.
 * Template File: search.tpl
 *
 * Template Variables:
 *
 * tAlias
 * tMailbox
 *
 * Form POST \ GET Variables:
 *
 * search
 * fDomain
 * fGo
 */

require_once('../common.php');

authentication_require_role('global-admin');

$tAlias = array();
$tMailbox = array();
$list_domains = list_domains ();


if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   if (isset ($_GET['search'])) $fSearch = escape_string ($_GET['search']);

   if ($CONF['alias_control'] == "YES")
   {
      $query = "SELECT address,goto,modified,domain,active FROM $table_alias WHERE address LIKE '%$fSearch%' OR goto LIKE '%$fSearch%' ORDER BY address";
      if ('pgsql'==$CONF['database_type'])
      {
         $query = "SELECT address,goto,extract (epoch from modified) as modified,domain,active FROM $table_alias WHERE address LIKE '%$fSearch%' OR goto LIKE '%$fSearch%' ORDER BY address";
      }
   }
   else
   {
      $query = "SELECT $table_alias.address,$table_alias.goto,$table_alias.modified,$table_alias.domain,$table_alias.active FROM $table_alias LEFT JOIN $table_mailbox ON $table_alias.address=$table_mailbox.username WHERE $table_alias.address LIKE '%$fSearch%' AND $table_mailbox.maildir IS NULL ORDER BY $table_alias.address";
      if ('pgsql'==$CONF['database_type'])
      {
         $query = "SELECT address,goto,extract(epoch from modified) as modified,domain,active FROM $table_alias WHERE address LIKE '%$fSearch%' AND NOT EXISTS(SELECT 1 FROM $table_mailbox WHERE username=$table_alias.address) ORDER BY address";
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
            $row['active']=('t'==$row['active']) ? 1 : 0;
         }
         $tAlias[] = $row;
      }
   }

   $query = "SELECT * FROM $table_mailbox WHERE username LIKE '%$fSearch%' OR name LIKE '%$fSearch%' ORDER BY username";
   if ('pgsql'==$CONF['database_type'])
   {
      $query = "SELECT *,extract(epoch from created) as uts_created,extract(epoch from modified) as uts_modified FROM $table_mailbox WHERE username LIKE '%$fSearch%' OR name LIKE '%$fSearch%' ORDER BY username";
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
   if (isset ($_POST['fGo'])) $fGo = escape_string ($_POST['fGo']);
   if (isset ($_POST['fDomain'])) $fDomain = escape_string ($_POST['fDomain']);

   if (empty ($fSearch) && !empty ($fGo))
   {
      header("Location: list-virtual.php?domain=" . $fDomain ) && exit;
   }


   if ($CONF['alias_control'] == "YES")
   {
      $query = "SELECT address,goto,modified,domain,active FROM $table_alias WHERE address LIKE '%$fSearch%' OR goto LIKE '%$fSearch%' ORDER BY address";
      if ('pgsql'==$CONF['database_type'])
      {
         $query = "SELECT address,goto,extract (epoch from modified) as modified,domain,active FROM $table_alias WHERE address LIKE '%$fSearch%' OR goto LIKE '%$fSearch%' ORDER BY address";
      }
   }
   else
   {
      $query = "SELECT $table_alias.address,$table_alias.goto,$table_alias.modified,$table_alias.domain,$table_alias.active FROM $table_alias LEFT JOIN $table_mailbox ON $table_alias.address=$table_mailbox.username WHERE $table_alias.address LIKE '%$fSearch%' AND $table_mailbox.maildir IS NULL ORDER BY $table_alias.address";
      if ('pgsql'==$CONF['database_type'])
      {
         $query = "SELECT $table_alias.address,$table_alias.goto,extract(epoch from $table_alias.modified) as $table_modified,$table_alias.domain,$table_alias.active FROM $table_alias LEFT JOIN $table_mailbox ON $table_alias.address=$table_mailbox.username WHERE $table_alias.address LIKE '%$fSearch%' AND $table_mailbox.maildir IS NULL ORDER BY $table_alias.address";
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
            $row['active']=('t'==$row['active']) ? 1 : 0;
         }
         $tAlias[] = $row;
      }
   }

   $query = "SELECT * FROM $table_mailbox WHERE username LIKE '%$fSearch%' OR name LIKE '%$fSearch%' ORDER BY username";
   if ('pgsql'==$CONF['database_type'])
   {
      $query = "SELECT *,extract(epoch from created) as uts_created,extract(epoch from modified) as uts_modified FROM $table_mailbox WHERE username LIKE '%$fSearch%' OR name LIKE '%$fSearch%' ORDER BY username";
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
            $row['active']=('t'==$row['active']) ? 1 : 0;
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
