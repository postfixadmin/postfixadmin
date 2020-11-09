<?php
/**
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
 * File: edit.php
 * This file implements the handling of edit forms.
 * The form layout is retrieved from the *Handler classes, which also do
 * the actual work of verifying and storing the values.
 *
 * GET parameters:
 *      table   what to edit (*Handler)
 *      edit    item to edit (if net given: a new item will be created)
 *      additional parameters will be accepted if specified in *Handler->webformConfig()[prefill] when creating a new item
 */

require_once('common.php');

$smarty = PFASmarty::getInstance();

$username = authentication_get_username(); # enforce login

$table = safepost('table', safeget('table'));

if (empty($table)) {
    die("Invalid table name given!");
}

$handlerclass = ucfirst($table) . 'Handler';

if (!preg_match('/^[a-z]+$/', $table) || !file_exists(dirname(__FILE__) . "/../model/$handlerclass.php")) { # validate $table
    die("Invalid table name given!");
}

$error = 0;
$values = [];
$edit = safepost('edit', safeget('edit'));
$new = 0;
if ($edit == "") {
    $new = 1;
}

$is_admin = authentication_has_role('admin');

$handler = new $handlerclass($new, $username, $is_admin);
$formconf = $handler->webformConfig();

if ($is_admin) {
    authentication_require_role($formconf['required_role']);
} else {
    if (empty($formconf['user_hardcoded_field'])) {
        die($handlerclass . ' is not available for users');
    }
}

if ($new == 0 || $formconf['early_init']) {
    if (!$handler->init($edit)) {
        if (count($handler->errormsg) == 0) {
            # should never happen and indicates a bug in $handler->init()
            flash_error($handlerclass . "->init() failed, but didn't set any error message");
        }
        flash_error($handler->errormsg);
        header("Location: " . $formconf['listview']);
        exit;
    }
}

$form_fields = $handler->getStruct();
$id_field = $handler->getId_field();

if ($_SERVER['REQUEST_METHOD'] == "GET") {
    if ($new) { # new - prefill fields from URL parameters if allowed in $formconf['prefill']
        if (isset($formconf['prefill'])) {
            foreach ($formconf['prefill'] as $field) {
                $prefillvalue = safeget($field, safesession("prefill:$table:$field"));
                if ($prefillvalue != '') {
                    $form_fields[$field]['default'] = $prefillvalue;
                    $handler->prefill($field, $prefillvalue);
                }
            }
        }
        $form_fields = $handler->getStruct(); # refresh $form_fields - a prefill field might have changed something
    } else { # edit mode - read values from database
        if (!$handler->view()) {
            flash_error($handler->errormsg);
            header("Location: " . $formconf['listview']);
            exit;
        } else {
            $values = $handler->result;
            $values[$id_field] = $edit;
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] == "POST") {
    if (safepost('token') != $_SESSION['PFA_token']) {
        die('Invalid token!');
    }

    $inp_values = [];

    if (isset($_POST['value']) && is_array($_POST['value'])) {
        $inp_values = $_POST['value'];
    }

    foreach ($form_fields as $key => $field) {
        if ($field['editable'] && $field['display_in_form']) {
            if (!isset($inp_values[$key])) {
                $inp_values[$key] = '';
            }

            if ($field['type'] == 'bool' && $inp_values[$key] == '') {
                $values[$key] = 0; # isset() for unchecked checkboxes is always false
            } elseif ($field['type'] == 'txtl') {
                $values[$key] = $inp_values[$key];
                $values[$key] = preg_replace('/\\\r\\\n/', ',', $values[$key]);
                $values[$key] = preg_replace('/\r\n/', ',', $values[$key]);
                $values[$key] = preg_replace('/,[\s]+/i', ',', $values[$key]);
                $values[$key] = preg_replace('/[\s]+,/i', ',', $values[$key]);
                $values[$key] = preg_replace('/,,*/', ',', $values[$key]);
                $values[$key] = preg_replace('/,*$|^,*/', '', $values[$key]);
                if ($values[$key] == '') {
                    $values[$key] = array();
                } else {
                    $values[$key] = explode(",", $values[$key]);
                }
            } else {
                $values[$key] = $inp_values[$key];
            }
        }
    }

    if (isset($formconf['hardcoded_edit']) && $formconf['hardcoded_edit']) {
        $values[$id_field] = $form_fields[$id_field]['default'];
    } elseif ($new == 0) {
        $values[$id_field] = $edit;
    }

    if ($new && ($form_fields[$id_field]['display_in_form'] == 0)) {
        if ($form_fields[$id_field]['editable'] == 1) { # address split to localpart and domain?
            $values[$id_field] = $handler->mergeId($values);
        } else { # probably auto_increment
            $values[$id_field] = '';
        }
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
        if (!$handler->save()) {
            $errormsg = $handler->errormsg;
        } else {
            flash_info($handler->infomsg);

            if (count($handler->errormsg)) { # might happen if domain_postcreation fails
                flash_error($handler->errormsg);
            }

            # remember prefill values for next usage of the form
            if (isset($formconf['prefill'])) {
                foreach ($formconf['prefill'] as $field) {
                    if (isset($values[$field])) {
                        $_SESSION["prefill:$table:$field"] = $values[$field];
                    }
                }
            }

            if ($new == 0) {
                header("Location: " . $formconf['listview']);
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

foreach ($form_fields as $key => $field) {
    if ($form_fields[$key]['display_in_form']) {
        if (isset($errormsg[$key])) {
            $fielderror[$key] = $errormsg[$key];
            unset($errormsg[$key]);
        } else {
            $fielderror[$key] = '';
        }

        if (isset($values[$key])) {
            $smarty->assign("value_$key", $values[$key]);
        } else {
            $smarty->assign("value_$key", $form_fields[$key]['default']);
        }
    }
}

if (count($errormsg)) {
    flash_error($errormsg);
} # display the remaining error messages (not related to a field) with flash_error

if ($new) {
    $smarty->assign('mode', 'create');
    $smarty->assign('formtitle', Config::lang($formconf['formtitle_create']));
    $smarty->assign('submitbutton', Config::lang($formconf['create_button']));
} else {
    $smarty->assign('mode', 'edit');
    $smarty->assign('formtitle', Config::lang($formconf['formtitle_edit']));
    $smarty->assign('submitbutton', Config::lang('save'));
}

$smarty->assign('struct', $form_fields);
$smarty->assign('fielderror', $fielderror);
$smarty->assign('table', $table);
$smarty->assign('smarty_template', 'editform');
$smarty->display('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
