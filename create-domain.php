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
 * File: create-domain.php
 * Allows administrators to create new domains.
 * Template File: admin_edit-domain.tpl
 */

require_once('common.php');

authentication_require_role('global-admin');

$error = 0;

$handler = new DomainHandler(1);
$form_fields = $handler->getStruct();

if ($_SERVER['REQUEST_METHOD'] == "POST") {

    foreach($form_fields as $key => $field) {
        if ($field['editable'] == 0) {
            $values[$key] = $field['default'];
        } else {
            if($field['type'] == 'bool') {
                $values[$key] = safepost($key, 0); # isset() for unchecked checkboxes is always false
            } else {
                $values[$key] = safepost($key);
            }
        }
    }

    if (!$handler->init($values['domain'])) {
        $error = 1;
        $pAdminCreate_domain_domain_text_error = join("<br />", $handler->errormsg);
    }

    if (!$handler->set($values)) {
        $error = 1;
        $pAdminCreate_domain_domain_text_error = join("<br />", $handler->errormsg);
    }

    if ($error != 1) {
        if (!$handler->store()) {
            $pAdminCreate_domain_domain_text_error = join("\n", $handler->errormsg);
        } else {
            flash_info($PALANG['pAdminCreate_domain_result_success'] . " (" . $values['domain'] . ")"); # TODO: use a sprintf string
            if (count($handler->errormsg)) { # might happen if domain_postcreation fails
                flash_error(join("<br />", $handler->errormsg));
            }
        }
    }
}


if ($error != 1) {
    $values = array();
    foreach (array_keys($form_fields) as $key) {
        $values[$key] = $form_fields[$key]['default'];
    }
}

foreach($form_fields as $key => $field) {
    $smartykey = "t" . ucfirst($key); # TODO: ugly workaround until I decide on the template variable names
    switch ($field['type']) {
        case 'bool':
            $smarty->assign ($smartykey, ($values[$key] == '1') ? ' checked="checked"' : '');
            break;
        case 'enum':
            $smarty->assign ($smartykey, select_options ($form_fields[$key]['options'], array ($values[$key])),false);
            break;
        default:
            $smarty->assign ($smartykey, $values[$key]);
    }
}

$smarty->assign ('mode', 'create');
$smarty->assign ('pAdminCreate_domain_domain_text_error', $pAdminCreate_domain_domain_text_error, false);
$smarty->assign ('smarty_template', 'admin_edit-domain');
$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
