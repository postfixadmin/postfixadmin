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
 * File: create-admin.php
 * Used to create new administrators.
 * Template File: admin_edit-admin.tpl
 *
 *
 * Template Variables:
 *
 * tUsername
 * tDomains
 *
 * Form POST \ GET Variables:
 *
 * fUsername
 * fPassword
 * fPassword2
 * fDomains
 */

require_once('common.php');

authentication_require_role('global-admin');
$list_domains = list_domains ();
$tDomains = array();
$pAdminCreate_admin_username_text_error = "";
$pAdminCreate_admin_password_text_error = "";

if ($_SERVER['REQUEST_METHOD'] == "POST") {
   $fUsername  = safepost('fUsername');
   $fPassword  = safepost('fPassword');
   $fPassword2 = safepost('fPassword2');
   $fDomains   = safepost('fDomains', array());

   list ($error, $infoMessage, $pAdminCreate_admin_username_text_error, $pAdminCreate_admin_password_text_error) = create_admin($fUsername, $fPassword, $fPassword2, $fDomains);

   if ($error != 0) {
      $tUsername = $fUsername;
      $tDomains = $fDomains;
   }

   if(!empty($infoMessage)) flash_info($infoMessage);
}

$smarty->assign ('mode', 'create');
$smarty->assign ('tUsername', $tUsername);
$smarty->assign ('pAdminCreate_admin_username_text', $PALANG['pAdminCreate_admin_username_text']);
$smarty->assign ('pAdminCreate_admin_username_text_error', $pAdminCreate_admin_username_text_error, false);
$smarty->assign ('admin_password_text_error', $pAdminCreate_admin_password_text_error, false);
$smarty->assign ('select_options', select_options ($list_domains, $tDomains), false);

$smarty->assign ('smarty_template', 'admin_edit-admin');
$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
