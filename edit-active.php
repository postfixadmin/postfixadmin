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
// fReturn
//
require ("./variables.inc.php");
require ("./config.inc.php");
require ("./functions.inc.php");
include ("./languages/" . check_language () . ".lang");

$SESSID_USERNAME = check_session();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   if (isset ($_GET['username'])) $fUsername = escape_string ($_GET['username']);
   if (isset ($_GET['alias'])) $fAlias = escape_string ($_GET['alias']); else $fAlias = escape_string ($_GET['username']);
   if (isset ($_GET['domain'])) $fDomain = escape_string ($_GET['domain']);
   if (isset ($_GET['return'])) $fReturn = escape_string ($_GET['return']);
   
   if (!check_owner ($SESSID_USERNAME, $fDomain))
   {
      $error = 1;
      $tMessage = $PALANG['pEdit_mailbox_domain_error'] . "<b>$fDomain</b>!</font>";
   }
   else
   {
      $setSql=('pgsql'==$CONF['database_type']) ? 'active=NOT active' : 'active=1-active';
      if ($fUsername != '')
      {
         $result = db_query ("UPDATE $table_mailbox SET $setSql WHERE username='$fUsername' AND domain='$fDomain'");
         if ($result['rows'] != 1)
         {
            $error = 1;
            $tMessage = $PALANG['pEdit_mailbox_result_error'];
         }
         else
         {
            db_log ($SESSID_USERNAME, $fDomain, "edit active", $fUsername);
         }
      }
      if ($fAlias != '')
      {
         $result = db_query ("UPDATE $table_alias SET $setSql WHERE address='$fAlias' AND domain='$fDomain'");
         if ($result['rows'] != 1)
         {
            $error = 1;
            $tMessage = $PALANG['pEdit_mailbox_result_error'];
         }
         else
         {
            db_log ($SESSID_USERNAME, $fDomain, "edit alias active", $fAlias);
         }
      }
   }
   
   if ($error != 1)
   {
      if ( $fReturn != "" )
      {
        header ("Location: $fReturn");
      }
      else
      {
        header ("Location: overview.php?domain=$fDomain");
      }
      exit;
   }
   
   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/message.tpl");
   include ("./templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/message.tpl");
   include ("./templates/footer.tpl");
}
/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
