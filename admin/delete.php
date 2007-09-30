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
 * Used to delete a domain, mailbox or alias.
 *
 * Template File: message.tpl
 *
 * Template Variables:
 *
 * tMessage
 *
 * Form POST \ GET Variables:
 *
 * fTable
 * fWhere
 * fDelete
 * fDomain
 */

require_once('../common.php');

authentication_require_role('global-admin');

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   if (isset ($_GET['table'])) $fTable = escape_string ($_GET['table']);
   if (isset ($_GET['where'])) $fWhere = escape_string ($_GET['where']);
   if (isset ($_GET['delete'])) $fDelete = escape_string ($_GET['delete']);
   if (isset ($_GET['domain'])) $fDomain = escape_string ($_GET['domain']);

   if ($CONF['database_type'] == "pgsql") db_query('BEGIN');

   $error=0;

   if (empty ($fTable))
   {
      $error = 1;
   }
   
   if ($fTable == "domain")
   {
      $result_domain_admins = db_delete ($table_domain_admins,$fWhere,$fDelete);
      $result_alias = db_delete ($table_alias,$fWhere,$fDelete);
      $result_mailbox = db_delete ($table_mailbox,$fWhere,$fDelete);
      $result_log = db_delete ($table_log,$fWhere,$fDelete);
      if ($CONF['vacation'] == "YES")
      {
         $result_vacation = db_delete ($table_vacation,$fWhere,$fDelete);
      }
      $result_domain = db_delete ($table_domain,$fWhere,$fDelete);

      if (!$result_domain || !domain_postdeletion($fDelete))
      {
         $error = 1;
         $tMessage = $PALANG['pAdminDelete_domain_error'];
      }
      else
      {
         $url = "list-domain.php";
      }
   }
   
   if ($fTable == "admin")
   {
      $result_admin = db_delete ($table_admin,$fWhere,$fDelete);
      $result_domain_admins = db_delete ($table_domain_admins,$fWhere,$fDelete);
      
      if (!($result_admin == 1) and ($result_domain_admins >= 0))
      {
         $error = 1;
         $tMessage = $PALANG['pAdminDelete_admin_error'];
      }
      else
      {
         $url = "list-admin.php";
      }
   }

   if ($fTable == "alias" or $fTable == "mailbox")
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
         $url = "list-virtual.php?domain=$fDomain";
         db_log ($SESSID_USERNAME , $fDomain, 'delete_alias', $fDelete);
      }

      if (!$error)
      {
         $result = db_query ("SELECT * FROM $table_mailbox WHERE username='$fDelete' AND domain='$fDomain'");
         if ($result['rows'] == 1)
         {
            $result = db_query ("DELETE FROM $table_mailbox WHERE username='$fDelete' AND domain='$fDomain'");
            $postdel_res=mailbox_postdeletion($fDelete,$fDomain);
            if ($result['rows'] != 1 || !$postdel_res)
            {
               $error = 1;
               $tMessage = $PALANG['pDelete_delete_error'] . "<b>$fDelete</b> (";
               if ($result['rows']!=1)
               {
                  $tMessage.='mailbox';
                  if (!$postdel_res) $tMessage.=', ';
               }
               if (!$postdel_res)
               {
                  $tMessage.='post-deletion';
               }
               $tMessage.=')</span>';
            }
            else
            {
               $url = "list-virtual.php?domain=$fDomain";
               db_query ("DELETE FROM $table_vacation WHERE email='$fDelete' AND domain='$fDomain'");
               db_log ($SESSID_USERNAME, $fDomain, 'delete_mailbox', $fDelete);
            }
         }
      }
   }

   if ($error == 1)
   {
      if ($CONF['database_type']=='pgsql') { db_query('ROLLBACK'); }
   } else {
      if ($CONF['database_type']=='pgsql') { db_query('COMMIT'); }
      header ("Location: $url");
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
   include ("../templates/menu.tpl");
   include ("../templates/message.tpl");
   include ("../templates/footer.tpl");
}
/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
