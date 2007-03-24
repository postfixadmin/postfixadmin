<?php
// 
// Postfix Admin 
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// Licensed under GPL for more info check GPL-LICENSE.TXT
//
// File: viewlog.php
//
// Template File: viewlog.tpl
//
// Template Variables:
//
// tMessage
// tLog
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

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   if ((is_array ($list_domains) and sizeof ($list_domains) > 0)) $fDomain = $list_domains[0];
}
else
{
   if (isset ($_POST['fDomain'])) $fDomain = escape_string ($_POST['fDomain']);
}

$query = "SELECT timestamp,username,domain,action,substring(data from 1 for 36) as data FROM $table_log WHERE domain='$fDomain' ORDER BY timestamp DESC LIMIT 10";
if ('pgsql'==$CONF['database_type'])
{
   $query = "SELECT extract(epoch from timestamp) as timestamp,username,domain,action,substring(data from 1 for 36) as data FROM $table_log WHERE domain='$fDomain' ORDER BY timestamp DESC LIMIT 10";
}

$result = db_query ($query);
if ($result['rows'] > 0)
{
   while ($row = db_array ($result['result']))
   {
      if ('pgsql'==$CONF['database_type'])
      {
         $row['timestamp']=gmstrftime('%c %Z',$row['timestamp']);
      }
      $tLog[] = $row;
   }
}

include ("../templates/header.tpl");
include ("../templates/admin_menu.tpl");
include ("../templates/viewlog.tpl");
include ("../templates/footer.tpl");
/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
