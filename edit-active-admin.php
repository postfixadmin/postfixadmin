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
 * File: edit-active-admin.php 
 * Edit an active administrator. This is used as a 'toggle' page from list-admin.
 *
 * Template File: message.tpl
 *
 * Template Variables:
 *
 * tMessage
 *
 * Form POST \ GET Variables:
 *
 * fUsername
 */

require_once('common.php');

authentication_require_role('global-admin');

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   if (isset ($_GET['username'])) $fUsername = escape_string ($_GET['username']);

   $sqlSet='active=1-active';
   if ('pgsql'==$CONF['database_type']) $sqlSet='active=NOT active';

   $result = db_query ("UPDATE $table_admin SET $sqlSet,modified=NOW() WHERE username='$fUsername'");
   if ($result['rows'] != 1)
   {
      $error = 1;
      $tMessage = $PALANG['pAdminEdit_admin_result_error'];
   }
   
   if ($error != 1)
   {
      header ("Location: list-admin.php");
      exit;
   }
}

include ("templates/header.tpl");
include ("templates/menu.tpl");
include ("templates/message.tpl");
include ("templates/footer.tpl");

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */

?>
