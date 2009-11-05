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
 * File: edit-active-domain.php 
 * Responsible for toggling the status of a domain
 * Template File: message.php
 *
 * Template Variables:
 *
 * tMessage
 *
 * Form POST \ GET Variables:
 *
 * fDomain
 */

require_once('common.php');

authentication_require_role('global-admin');

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   if (isset ($_GET['domain'])) $fDomain = escape_string ($_GET['domain']);

   $sqlSet='active=1-active';
   if ('pgsql'==$CONF['database_type']) $sqlSet='active=NOT active';
   
   $result = db_query ("UPDATE $table_domain SET $sqlSet,modified=NOW() WHERE domain='$fDomain'");
   if ($result['rows'] != 1)
   {
      $error = 1;
      $tMessage = $PALANG['pAdminEdit_domain_result_error'];
   }
   
   if ($error != 1)
   {
      header ("Location: list-domain.php");
      exit;
   }
}

$smarty->assign ('tMessage', $tMessage);
$smarty->assign ('smarty_template', 'message');
$smarty->display ('index.tpl');


/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
