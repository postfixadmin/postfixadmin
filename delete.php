<?php
/** 
 * Postfix Admin 
 * 
 * LICENSE 
 * This source file is subject to the GPL license that is bundled with  
 * this package in the file LICENSE.TXT. 
 * 
 * Further details on the project are available at : 
 *     http://www.postfixadmin.com or http://postfixadmin.sf.net 
 * 
 * @version $Id$ 
 * @license GNU GPL v2 or later. 
 * 
 * File: delete.php
 * Responsible for allowing for the deletion of domains; note if 
 * a domain is deleted, all mailboxes and aliases belonging to the 
 * domain are also removed.
 *
 * @version $Id$
 * @license GNU GPL v2 or later.
 *
 * Template Variables:
 *
 * Template File: message.tpl
 *
 * Template Variables:
 *
 * tMessage
 *
 * Form POST \ GET Variables:
 *
 * fDelete
 * fDomain
 */

require_once('common.php');

authentication_require_role('admin');

$SESSID_USERNAME = authentication_get_username();

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
         db_log ($SESSID_USERNAME, $fDomain, 'delete_alias', $fDelete);
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
            db_log ($SESSID_USERNAME, $fDomain, 'delete_mailbox', $fDelete);
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
