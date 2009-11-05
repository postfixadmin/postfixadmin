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
 * File: list-admin.php
 * Lists all administrators
 * Template File: list-admin.php
 *
 * Template Variables: -none-
 *
 * Form POST \ GET Variables: -none-
 */

require_once("common.php");

authentication_require_role('global-admin');

$_active = array ($PALANG ['NO'], $PALANG ['YES']);

$list_admins = list_admins();
if ((is_array ($list_admins) and sizeof ($list_admins) > 0))
{
	for ($i = 0; $i < sizeof ($list_admins); $i++)
	{
		$admin_properties[$i] = get_admin_properties ($list_admins[$i]);
		$admin_properties[$i] ['name'] = $list_admins[$i];
		if ($admin_properties [$i] ['domain_count'] == 'ALL')
			$admin_properties [$i] ['domain_count'] = $PALANG ['pAdminEdit_admin_super_admin'];
		$admin_properties [$i] ['active'] = $_active [$admin_properties [$i] ['active']];			
	}
}
$smarty->assign ('admin_properties', $admin_properties);
$smarty->assign ('tMessage', $tMessage);
$smarty->assign ('smarty_template', 'admin_list-admin');
$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
