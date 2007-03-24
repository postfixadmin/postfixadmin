<?php
//
// Postfix Admin
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// Licensed under GPL for more info check GPL-LICENSE.TXT
//
// File: edit-vacation.php
//
// Template File: edit-vacation.tpl
//
// Template Variables:
//
// tMessage
// tName
// tQuota
//
// Form POST \ GET Variables:
//
// fUsername
// fDomain
// fPassword
// fPassword2
// fName
// fQuota
// fActive
//
require ("./variables.inc.php");
require ("./config.inc.php");
require ("./functions.inc.php");
include ("./languages/" . check_language () . ".lang");

$SESSID_USERNAME = check_session ();
(($CONF['vacation'] == 'NO') ? header("Location: " . $CONF['postfix_admin_url'] . "/main.php") && exit : '1');
$tmp = preg_split ('/@/', $SESSID_USERNAME);
$USERID_DOMAIN = $tmp[1];

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   if (isset ($_GET['username'])) $fUsername = escape_string ($_GET['username']);
   if (isset ($_GET['domain'])) $fDomain = escape_string ($_GET['domain']);

   $result = db_query("SELECT * FROM $table_vacation WHERE email='$fUsername'");
   if ($result['rows'] == 1)
   {
      $row = db_array($result['result']);
      $tMessage = '';
   }

   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/edit-vacation.tpl");
   include ("./templates/footer.tpl");
}
?>
