<?php
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
require ("../config.inc.php");
require ("../functions.inc.php");
include ("../languages/" . $CONF['language'] . ".lang");

$list_domains = list_domains ();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   $fDomain = $list_domains[0];

   if ($error != 1)
   {
      $result = db_query ("SELECT * FROM log WHERE domain='$fDomain' ORDER BY timestamp DESC LIMIT 10");
      if ($result['rows'] > 0)
      {
         while ($row = mysql_fetch_array ($result['result']))
         {
            $tLog[] = $row;
         }
      }
   }
   
   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/viewlog.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $fDomain = $_POST['fDomain'];
   
   $result = db_query ("SELECT * FROM log WHERE domain='$fDomain' ORDER BY timestamp DESC LIMIT 10");
   if ($result['rows'] > 0)
   {
      while ($row = mysql_fetch_array ($result['result']))
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
