<?php /**
 * Postfix Admin 
 * 
 * LICENSE 
 * This source file is subject to the GPL license that is bundled with  
 * this package in the file LICENSE.TXT. 
 * 
 * Further details on the project are available at http://postfixadmin.sf.net 
 * 
 * @version $Id$ 
 * @license GNU GPL v2 or later. 
 * 
 * File: list.php
 * List all items as a quick overview.
 *
 */

require_once('common.php');

# if (safeget('token') != $_SESSION['PFA_token']) die('Invalid token!');

$username = authentication_get_username(); # enforce login

$table = safeget('table');

$handlerclass = ucfirst($table) . 'Handler';

if ( !preg_match('/^[a-z]+$/', $table) || !file_exists("model/$handlerclass.php")) { # validate $table
     die ("Invalid table name given!");
}

# default: domain admin restrictions
$list_admins = array($username);
$is_superadmin = 0;

if (authentication_has_role('global-admin')) { # more permissions? Fine!
    $list_admins = array_keys(list_admins());
    $is_superadmin = 1;
    $username = safepost('username', safeget('username', authentication_get_username())); # prefer POST over GET variable
}

$handler = new $handlerclass(0, $username);

$formconf = $handler->webformConfig();

authentication_require_role($formconf['required_role']);

$handler->getList('');
$items = $handler->result();

$smarty->assign ('select_options', select_options($list_admins, array ($fUsername)), false);
#if ($is_superadmin) {
    $smarty->assign('smarty_template', 'list');
    $smarty->assign('struct', $handler->getStruct());
    $smarty->assign('msg', $handler->getMsg());
    $smarty->assign('table', $table);
    $smarty->assign('items', $items);
    $smarty->assign('id_field', $handler->getId_field());
    $smarty->assign('formconf', $formconf);
#} else {
#    $smarty->assign ('smarty_template', 'overview-get');
#}

$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
