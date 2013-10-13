<?php /**
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
 * File: list-domain.php
 * List all domains as a quick overview.
 *
 */

require_once('common.php');

authentication_require_role('admin');

# default: domain admin restrictions
$admin_username = authentication_get_username();
$list_admins = array(authentication_get_username());
$is_superadmin = 0;
$fUsername = "";

if (authentication_has_role('global-admin')) { # more permissions? Fine!
   $list_admins = array_keys(list_admins());
   $is_superadmin = 1;

   $fUsername = safepost('fUsername', safeget('username', authentication_get_username())); # prefer POST over GET variable
   if ($fUsername != "") {
      $admin_username = $fUsername;
   }
}

$handler = new DomainHandler(0, $admin_username);
$handler->getList('');
$domain_properties = $handler->result();

$smarty->assign ('domain_properties', $domain_properties);
$smarty->assign ('select_options', select_options($list_admins, array ($fUsername)), false);
if ($is_superadmin) {
	$smarty->assign('smarty_template', 'adminlistdomain');
} else {
	$smarty->assign ('smarty_template', 'overview-get');
}

$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
