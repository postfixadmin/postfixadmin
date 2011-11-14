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
 * File: edit.php
 * This file implements the handling of edit forms.
 */

require_once('common.php');

$username = authentication_get_username(); # enforce login

$table = safepost('table', safeget('table'));
$handlerclass = ucfirst($table) . 'Handler';

if ( !preg_match('/^[a-z]+$/', $table) || !file_exists("model/$handlerclass.php")) { # validate $table
    die ("Invalid table name given!");
}

$error = 0;
$mode = 'create';

$edit = safepost('edit', safeget('edit'));
$new  = 0;
if ($edit == "") $new = 1;

$handler     = new $handlerclass($new, $username);

$formconf = $handler->webformConfig();

authentication_require_role($formconf['required_role']);

$form_fields = $handler->getStruct();
$id_field    = $handler->getId_field();


if ($edit != "" || $formconf['early_init']) {
    if (!$handler->init($edit)) {
        flash_error(join("<br />", $handler->errormsg));
        header ("Location: " . $formconf['listview']);
        exit;
    }
}

if ($edit != "") {
    $mode = 'edit';
    if ($_SERVER['REQUEST_METHOD'] == "GET") { # read values from database
        if (!$handler->view()) {
            flash_error(join("<br />", $handler->errormsg));
            header ("Location: " . $formconf['listview']);
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
        $errormsg = $handler->errormsg;
    }

    if (!$handler->set($values)) {
        $error = 1;
        $errormsg = $handler->errormsg;
    }

    if ($error != 1) {
        if (!$handler->store()) {
            $errormsg = $handler->errormsg;
        } else {
            flash_info(Lang::read($formconf['successmessage']) . " (" . $values[$id_field] . ")");
            # TODO: - use a sprintf string
            # TODO: - get the success message from DomainHandler
            # TODO: - use a different success message for create and edit

            if (count($handler->errormsg)) { # might happen if domain_postcreation fails
                flash_error(join("<br />", $handler->errormsg));
            }

            if ($edit != "") {
                header ("Location: " . $formconf['listview']);
                exit;
            } else {
                header("Location: edit.php?table=$table");
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

$errormsg = $handler->errormsg;
$fielderror = array();

foreach($form_fields as $key => $field) {
  if($form_fields[$key]['display_in_form']) {

    if (isset($errormsg[$key])) {
        $fielderror[$key] = $errormsg[$key];
        unset ($errormsg[$key]);
    } else {
        $fielderror[$key] = '';
    }

    $smarty->assign ("value_$key", $values[$key]);
  }
}

foreach($errormsg as $msg) { # output the remaining error messages (not related to a field) with flash_error
    flash_error($msg);
}

if ($mode == 'edit') {
    $smarty->assign('formtitle', Lang::read($formconf['formtitle_edit']));
    $smarty->assign('submitbutton', Lang::read('save'));
} else {
    $smarty->assign('formtitle', Lang::read($formconf['formtitle_create']));
    $smarty->assign('submitbutton', Lang::read($formconf['create_button']));
}

$smarty->assign ('struct', $form_fields);
$smarty->assign ('fielderror', $fielderror);
$smarty->assign ('mode', $mode);
$smarty->assign ('table', $table);
$smarty->assign ('smarty_template', 'editform');
$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
