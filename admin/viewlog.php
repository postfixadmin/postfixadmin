<?php
// 
// Postfix Admin 
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// License Info: http://www.postfixadmin.com/?file=LICENSE.TXT
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

$list_domains = list_domains ();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{

   if ((is_array ($list_domains) and sizeof ($list_domains) > 0)) $fDomain = $list_domains[0];

   $result = db_query ("SELECT * FROM log WHERE domain='$fDomain' ORDER BY timestamp DESC LIMIT 10");
   if ($result['rows'] > 0)
   {
      while ($row = db_array ($result['result']))
      {
         $tLog[] = $row;
      }
   }
   
   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/viewlog.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $fDomain = escape_string ($_POST['fDomain']);
   
   $result = db_query ("SELECT * FROM log WHERE domain='$fDomain' ORDER BY timestamp DESC LIMIT 10");
   if ($result['rows'] > 0)
   {
      while ($row = db_array ($result['result']))
      {
         $tLog[] = $row;
      }
   }

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/viewlog.tpl");
   include ("../templates/footer.tpl");
}
?>
