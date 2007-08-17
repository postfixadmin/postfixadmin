<?php
// 
// Postfix Admin 
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// Licensed under GPL for more info check GPL-LICENSE.TXT
//
// File: delete.php
//
// Template File: message.tpl
//
// Template Variables:
//
// tMessage
//
// Form POST \ GET Variables:
//
// fDelete
// fDomain
//
require ("./variables.inc.php");
require ("./config.inc.php");
require ("./functions.inc.php");
include ("./languages/" . check_language () . ".lang");

$SESSID_USERNAME = check_session();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   if (isset ($_GET['delete'])) $fDelete = escape_string ($_GET['delete']);
   if (isset ($_GET['domain'])) $fDomain = escape_string ($_GET['domain']);

   if (!check_owner ($SESSID_USERNAME, $fDomain))
   {
      $error = 1;
      $tMessage = $PALANG['pDelete_domain_error'] . "<b>$fDomain</b>!</span>";
   }
   elseif (!check_alias_owner ($SESSID_USERNAME, $fDelete))
   {
      $error = 1;
      $tMessage = $PALANG['pDelete_alias_error'] . "<b>$fDelete</b>!</span>";
   }
   else
   {
      if ($CONF['database_type'] == "pgsql") db_query('BEGIN');
      $result = db_query ("DELETE FROM $table_alias WHERE address='$fDelete' AND domain='$fDomain'");
      if ($result['rows'] != 1)
      {
         $error = 1;
         $tMessage = $PALANG['pDelete_delete_error'] . "<b>$fDelete</b> (alias)!</span>";
      }
      else
      {
         db_log ($SESSID_USERNAME, $fDomain, "delete alias", $fDelete);
      }

      $result = db_query ("SELECT * FROM $table_mailbox WHERE username='$fDelete' AND domain='$fDomain'");
      if ($result['rows'] == 1)
      {
         $result = db_query ("DELETE FROM $table_mailbox WHERE username='$fDelete' AND domain='$fDomain'");
         $postdel_res = mailbox_postdeletion($fDelete,$fDomain);
         if ($result['rows'] != 1 || !$postdel_res)
         {
            $error = 1;
            $tMessage = $PALANG['pDelete_delete_error'] . "<b>$fDelete</b> (";
            if ($result['rows']!=1)
            {
               $tMessage.='mailbox';
               if (!$postdel_res) $tMessage.=', ';
            }
            if (!$postdel_res) $tMessage.='post-deletion';
            $tMessage.=')</span>';
         }
         else
         {
            db_query ("DELETE FROM $table_vacation WHERE email='$fDelete' AND domain='$fDomain'");
            db_log ($SESSID_USERNAME, $fDomain, "delete mailbox", $fDelete);
         }
      }
   }

   if ($error != 1)
   {
      if ($CONF['database_type'] == "pgsql") db_query('COMMIT');
      header ("Location: overview.php?domain=$fDomain");
      exit;
   } else {
      $tMessage = $PALANG['pDelete_delete_error'] . "<b>$fDelete</b> (physical mail)!</span>";
      if ($CONF['database_type'] == "pgsql") db_query('ROLLBACK');
   }
}

include ("./templates/header.tpl");
include ("./templates/menu.tpl");
include ("./templates/message.tpl");
include ("./templates/footer.tpl");

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
