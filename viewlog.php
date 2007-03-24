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
require ("./config.inc.php");
require ("./functions.inc.php");
include ("./languages/" . $CONF['language'] . ".lang");

$SESSID_USERNAME = check_session();
$list_domains = list_domains_for_admin ($SESSID_USERNAME);

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   $fDomain = $list_domains[0];

   if (!check_owner ($SESSID_USERNAME, $fDomain))
   {
      $error = 1;
      $tMessage = $LANG['pViewlog_error'];
   }

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
   
   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/viewlog.tpl");
   include ("./templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $fDomain = $_POST['fDomain'];
   
   if (!check_owner ($SESSID_USERNAME, $fDomain))
   {
      $error = 1;
      $tMessage = $LANG['pViewlog_error'];
   }

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

   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/viewlog.tpl");
   include ("./templates/footer.tpl");
}
?>
