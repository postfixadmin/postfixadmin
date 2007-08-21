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

if (!isset($incpath)) $incpath = '.';

require ("$incpath/variables.inc.php");
require ("$incpath/config.inc.php");
require ("$incpath/functions.inc.php");
include ("$incpath/languages/" . check_language () . ".lang");

$SESSID_USERNAME = check_session();
if (!check_admin($SESSID_USERNAME))
{
   $list_domains = list_domains_for_admin ($SESSID_USERNAME);
}
else
{
   $list_domains = list_domains ();
}

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   if ((is_array ($list_domains) and sizeof ($list_domains) > 0)) $fDomain = $list_domains[0];
} elseif ($_SERVER['REQUEST_METHOD'] == "POST") {
   if (isset ($_POST['fDomain'])) $fDomain = escape_string ($_POST['fDomain']);
} else {
   die('Unknown request method');
}

if (! (check_owner ($SESSID_USERNAME, $fDomain) || check_admin($SESSID_USERNAME)) )
{
   $error = 1;
   $tMessage = $PALANG['pViewlog_result_error'];
}

if ($error != 1)
{
   $query = "SELECT timestamp,username,domain,action,substring(data from 1 for 36) as data FROM $table_log WHERE domain='$fDomain' ORDER BY timestamp DESC LIMIT 10";
   if ('pgsql'==$CONF['database_type'])
   {
      $query = "SELECT extract(epoch from timestamp) as timestamp,username,domain,action,substring(data from 1 for 36) as data FROM $table_log WHERE domain='$fDomain' ORDER BY timestamp DESC LIMIT 10";
   }
   $result=db_query($query);
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
}

include ("$incpath/templates/header.tpl");

if (check_admin($SESSID_USERNAME)) {
   include ("$incpath/templates/admin_menu.tpl");
} else {
   include ("$incpath/templates/menu.tpl");
}

include ("$incpath/templates/viewlog.tpl");
include ("$incpath/templates/footer.tpl");

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
