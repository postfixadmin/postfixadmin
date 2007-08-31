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

if (!isset($incpath)) $incpath = '.';

require ("$incpath/variables.inc.php");
require ("$incpath/config.inc.php");
require ("$incpath/functions.inc.php");
include ("$incpath/languages/" . check_language () . ".lang");

$SESSID_USERNAME = check_session ();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   if (isset ($_GET['username'])) $fUsername = escape_string ($_GET['username']);
   if (isset ($_GET['alias'])) $fAlias = escape_string ($_GET['alias']); else $fAlias = escape_string ($_GET['username']);
   if (isset ($_GET['domain'])) $fDomain = escape_string ($_GET['domain']);
   if (isset ($_GET['return'])) $fReturn = escape_string ($_GET['return']);

   if (! (check_owner ($SESSID_USERNAME, $fDomain) || check_admin($SESSID_USERNAME) ) )
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
      if ( preg_match( "/^list-virtual.php.*/", $fReturn ) || 
           preg_match( "/^overview.php.*/", $fReturn )  ||
           preg_match( "/^search.php.*/", $fReturn )    )
      {
         //$fReturn appears OK, jump there
         header ("Location: $fReturn");
      }
      else
      {
         if (check_admin($SESSID_USERNAME)) {
            header ("Location: list-virtual.php?domain=$fDomain");
         } else {
            header ("Location: overview.php?domain=$fDomain");
         }
      }
      exit;
   }
}

include ("$incpath/templates/header.tpl");

if (check_admin($SESSID_USERNAME)) {
   include ("$incpath/templates/admin_menu.tpl");
} else {
   include ("$incpath/templates/menu.tpl");
}

include ("$incpath/templates/message.tpl");
include ("$incpath/templates/footer.tpl");
/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
