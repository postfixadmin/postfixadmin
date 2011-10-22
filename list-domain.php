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
 * Template File: adminlistdomain.tpl
 *
 * Template Variables:
 *
 * -none-
 *
 * Form POST \ GET Variables:
 *
 * fUsername
 */

require_once('common.php');

authentication_require_role('admin');

if (authentication_has_role('global-admin')) {
//if (authentication_has_role('admin')) {
   $list_admins = list_admins ();
   $is_superadmin = 1;
   $fUsername = escape_string(safepost('fUsername', safeget('username', authentication_get_username()))); # prefer POST over GET variable
   if ($fUsername != "") $admin_properties = get_admin_properties($fUsername);
} else {
   $list_admins = array(authentication_get_username());
   $is_superadmin = 0;
   $fUsername = "";
}

$list_all_domains = 0;
if (isset($admin_properties) && $admin_properties['domain_count'] == 'ALL') { # list all domains for superadmins
   $list_all_domains = 1;
} elseif (!empty($fUsername)) {
  $list_domains = list_domains_for_admin ($fUsername);
} elseif ($is_superadmin) {
   $list_all_domains = 1;
} else {
   $list_domains = list_domains_for_admin(authentication_get_username());
}

if ($list_all_domains == 1) {
	$where = " domain != 'ALL' "; # TODO: the ALL dummy domain is annoying...
} else {
	$list_domains = escape_string($list_domains);
	$where = db_in_clause('domain', $list_domains);
}

$handler = new DomainHandler();
if ($handler->getList($where)) {
   $domain_properties = $handler->result();
} else {
   $domain_properties = array();
   # TODO: check if there was an error or simply no domains
}


$smarty->assign ('domain_properties', $domain_properties);
if ($is_superadmin)
{
	$smarty->assign('select_options', select_options($list_admins, array ($fUsername)), false);
	$smarty->assign('smarty_template', 'adminlistdomain');
}
else
{
	$smarty->assign ('select_options', select_options($list_admins, array ($fUsername)), false);
	$smarty->assign ('smarty_template', 'overview-get');
}

$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
