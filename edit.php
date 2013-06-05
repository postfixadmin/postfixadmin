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
 * The form layout is retrieved from the *Handler classes, which also do
 * the actual work of verifying and storing the values.
 *
 * GET parameters:
 *      table   what to edit (*Handler)
 *      edit    item to edit (if net given: a new item will be created)
 *      active  if given: only change active state to given value (which must be 0 or 1) and return to listview
 *      additional parameters will be accepted if specified in *Handler->webformConfig()[prefill] when creating a new item
 */

require_once('common.php');

$username = authentication_get_username(); # enforce login

$table = safepost('table', safeget('table'));
$handlerclass = ucfirst($table) . 'Handler';

if ( !preg_match('/^[a-z]+$/', $table) || !file_exists("model/$handlerclass.php")) { # validate $table
    die ("Invalid table name given!");
}

$error = 0;

$edit = safepost('edit', safeget('edit'));
$new  = 0;
if ($edit == "") $new = 1;

$active = safeget('active');

$handler     = new $handlerclass($new, $username);

$formconf = $handler->webformConfig();

authentication_require_role($formconf['required_role']);

if ($active != '0' && $active != '1') {
    $active = ''; # ignore invalid values
}

if ($edit != '' || $active != '' || $formconf['early_init']) {
    if (!$handler->init($edit)) {
        flash_error($handler->errormsg);
        header ("Location: " . $formconf['listview']);
        exit;
    }
}

$form_fields = $handler->getStruct();
$id_field    = $handler->getId_field();

if ($_SERVER['REQUEST_METHOD'] == "GET" && $active == '') {
    if ($edit == '') { # new - prefill fields from URL parameters if allowed in $formconf['prefill']
        if ( isset($formconf['prefill']) ) {
            foreach ($formconf['prefill'] as $field) {
                if (isset ($_GET[$field])) {
                    $form_fields[$field]['default'] = safeget($field);
                    $handler->prefill($field, safeget($field));
                }
            }
        }
            $form_fields = $handler->getStruct(); # refresh $form_fields - a prefill field might have changed something
    } else { # edit mode - read values from database
        if (!$handler->view()) {
            flash_error($handler->errormsg);
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
        if ($field['editable'] && $field['display_in_form']) {
            if($field['type'] == 'bool') {
                $values[$key] = safepost($key, 0); # isset() for unchecked checkboxes is always false
            } elseif($field['type'] == 'txtl') {
                $values[$key] = safepost($key);
                $values[$key] = preg_replace ('/\\\r\\\n/', ',', $values[$key]);
                $values[$key] = preg_replace ('/\r\n/',     ',', $values[$key]);
                $values[$key] = preg_replace ('/,[\s]+/i',  ',', $values[$key]); 
                $values[$key] = preg_replace ('/[\s]+,/i',  ',', $values[$key]); 
                $values[$key] = preg_replace ('/,,*/',      ',', $values[$key]);
                $values[$key] = preg_replace ('/,*$|^,*/',  '',  $values[$key]);
                if ($values[$key] == '') {
                    $values[$key] = array();
                } else {
                    $values[$key] = explode(",", $values[$key]);
                }
            } else {
                $values[$key] = safepost($key);
            }
        }
    }
}

if ($active != '') {
    $values['active'] = $active;
}

if ($_SERVER['REQUEST_METHOD'] == "POST" || $active != '') {
    if ($edit != "") $values[$id_field] = $edit;

    if ($new && ($form_fields[$id_field]['display_in_form'] == 0) && ($form_fields[$id_field]['editable'] == 1) ) { # address split to localpart and domain?
        $values[$id_field] = $handler->mergeId($values);
    }

    if (!$handler->init($values[$id_field])) {
        $error = 1;
        $errormsg = $handler->errormsg;
    }

    if (!$handler->set($values)) {
        $error = 1;
        $errormsg = $handler->errormsg;
    }

    $form_fields = $handler->getStruct(); # refresh $form_fields - set() might have changed something

    if ($error != 1) {
        if (!$handler->store()) {
            $errormsg = $handler->errormsg;
        } else {
            flash_info($handler->infomsg);

            if (count($handler->errormsg)) { # might happen if domain_postcreation fails
                flash_error($handler->errormsg);
            }

            if ($edit != "") {
                header ("Location: " . $formconf['listview']);
                exit;
            } else {
                header("Location: edit.php?table=$table"); # TODO: hand over last used domain etc. ($formconf['prefill'] ?)
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

if (count($errormsg)) flash_error($errormsg); # display the remaining error messages (not related to a field) with flash_error

if ($new) {
    $smarty->assign ('mode', 'create');
    $smarty->assign('formtitle', Lang::read($formconf['formtitle_create']));
    $smarty->assign('submitbutton', Lang::read($formconf['create_button']));
} else {
    $smarty->assign ('mode', 'edit');
    $smarty->assign('formtitle', Lang::read($formconf['formtitle_edit']));
    $smarty->assign('submitbutton', Lang::read('save'));
}

$smarty->assign ('struct', $form_fields);
$smarty->assign ('fielderror', $fielderror);
$smarty->assign ('table', $table);
$smarty->assign ('smarty_template', 'editform');
$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
