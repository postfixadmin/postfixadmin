<?php
// 
// Postfix Admin 
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// Licensed under GPL for more info check GPL-LICENSE.TXT
//
// File: edit-active.php
//
// Template File: message.tpl
//
// Template Variables:
//
// tMessage
//
// Form POST \ GET Variables:
//
// fUsername
// fDomain
//
require ("../variables.inc.php");
require ("../config.inc.php");
require ("../functions.inc.php");
include ("../languages/" . check_language () . ".lang");

$SESSID_USERNAME = check_session ();
(!check_admin($SESSID_USERNAME) ? header("Location: " . $CONF['postfix_admin_url'] . "/main.php") && exit : '1');

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   if (isset ($_GET['username'])) $fUsername = escape_string ($_GET['username']);
   if (isset ($_GET['alias'])) $fAlias = escape_string ($_GET['alias']); else $fAlias = escape_string ($_GET['username']);
   if (isset ($_GET['domain'])) $fDomain = escape_string ($_GET['domain']);

   if ($fUsername != '')
   {
      $query = "UPDATE $table_mailbox SET active=1-active WHERE username='$fUsername' AND domain='$fDomain'";
      if ('pgsql'==$CONF['database_type'])
      {
         $query = "UPDATE $table_mailbox SET active=NOT active WHERE username='$fUsername' AND domain='$fDomain'";
      }
      $result = db_query ($query);
      if ($result['rows'] != 1)
      {
         $error = 1;
         $tMessage = $PALANG['pEdit_mailbox_result_error'];
      }
      else
      {
         db_log ($CONF['admin_email'], $fDomain, "edit active", $fUsername);
      }
   }

   if ($fAlias != '')
   {
      $query = "UPDATE $table_alias SET active=1-active WHERE address='$fAlias' AND domain='$fDomain'";
      if ('pgsql'==$CONF['database_type'])
      {
         $query = "UPDATE $table_alias SET active=NOT active WHERE address='$fAlias' AND domain='$fDomain'";
      }
      $result = db_query ($query);
      if ($result['rows'] != 1)
      {
         $error = 1;
         $tMessage = $PALANG['pEdit_mailbox_result_error'] . " alias" . $result['rows'];
      }
      else
      {
         db_log ($CONF['admin_email'], $fDomain, "edit alias active", $fAlias);
      }
   }

   if ($error != 1)
   {
      header ("Location: list-virtual.php?domain=$fDomain");
      exit;
   }

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/message.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/message.tpl");
   include ("../templates/footer.tpl");
}
/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
