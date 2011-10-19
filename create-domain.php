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
 * Allows administrators to create or edit domains.
 * Template File: admin_edit-domain.tpl
 */

require_once('common.php');

authentication_require_role('global-admin');

$error = 0;
$errortext = "";
$mode = 'create';

$edit = safepost('edit', safeget('edit'));
$new  = 0;
if ($edit == "") $new = 1;

$listview = 'list-domain.php';

$handler     = new DomainHandler($new);
$form_fields = $handler->getStruct();
$id_field    = $handler->getId_field();


if ($edit != "") {
    $mode = 'edit';

    if (!$handler->init($edit)) {
        flash_error(join("<br />", $handler->errormsg));
        header ("Location: $listview");
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] == "GET") { # read values from database
        if (!$handler->view()) {
            flash_error(join("<br />", $handler->errormsg));
            header ("Location: $listview");
            exit;
        } else {
            $values = $handler->return;
            $values[$id_field] = $edit;
        }
    }
}


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
    if ($edit != "") $values[$id_field] = $edit;

    if (!$handler->init($values[$id_field])) {
        $error = 1;
        $errortext = join("<br />", $handler->errormsg);
    }

    if (!$handler->set($values)) {
        $error = 1;
        $errortext = join("<br />", $handler->errormsg);
    }

    if ($error != 1) {
        if (!$handler->store()) {
            $errortext = join("\n", $handler->errormsg);
        } else {
            flash_info($PALANG['pAdminCreate_domain_result_success'] . " (" . $values[$id_field] . ")");
            # TODO: - use a sprintf string
            # TODO: - get the success message from DomainHandler
            # TODO: - use a different success message for create and edit

            if (count($handler->errormsg)) { # might happen if domain_postcreation fails
                flash_error(join("<br />", $handler->errormsg));
            }

            if ($edit != "") {
                header ("Location: $listview");
                exit;
            }
        }
    }
}

if ($error != 1 && $new) { # no error and not in edit mode - reset fields to default for new item
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

$smarty->assign ('mode', $mode);
$smarty->assign ('errortext', $errortext, false); # non-escaped
$smarty->assign ('smarty_template', 'admin_edit-domain');
$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
