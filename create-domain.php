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

$form_fields = array(
    'domain'         => array('type' => 'str', 'default' => null),
    'description'    => array('type' => 'str', 'default' =>''), 
    'aliases'        => array('type' => 'int', 'default' => $CONF['aliases']), 
    'mailboxes'      => array('type' => 'int', 'default' => $CONF['mailboxes']), 
    'maxquota'       => array('type' => 'int', 'default' => $CONF['maxquota']),
    'quota'          => array('type' => 'int', 'default' => $CONF['domain_quota_default']),
    'transport'      => array('type' => 'str', 'default' => $CONF['transport_default'], 'options' => $CONF['transport_options']), 
    'default_aliases'=> array('type' => 'bool', 'default' => '1', 'options' => array(1, 0)), 
    'backupmx'       => array('type' => 'bool', 'default' => '0', 'options' => array(1, 0)) 
);

# TODO: this foreach block should only be executed for POST
foreach($form_fields  as $key => $field) {
    if($field['type'] == 'bool' && $_SERVER['REQUEST_METHOD'] == "POST") {
        $values[$key] = safepost($key, 0); # isset for unchecked checkboxes is always false
    } 
    elseif (isset($_POST[$key]) && (strlen($_POST[$key]) > 0)) {
        $values[$key] = safepost($key);
    }
    else {
        $values[$key] = $field['default'];
    }

# TODO: check via _inp_enum in *Handler
    if(isset($field['options'])) {
        if(!in_array($values[$key], $field['options'])) {
            die("Invalid parameter given for $key");
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {

    $handler =  new DomainHandler($values['domain'], 1);
    if (!$handler->result()) {
        $error = 1;
        $pAdminCreate_domain_domain_text_error = join("<br />", $handler->errormsg);
    }

    $values['active'] = 1; # hardcoded for now - TODO: change this ;-)
    
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

$smarty->assign ('mode', 'create');
$smarty->assign ('pAdminCreate_domain_domain_text_error', $pAdminCreate_domain_domain_text_error, false);
$smarty->assign ('tDomain', $values['domain']);
$smarty->assign ('tDescription', $values['description']);
$smarty->assign ('tAliases', $values['aliases']);
$smarty->assign ('tMailboxes', $values['mailboxes']);
$smarty->assign ('tDomainquota', $values['quota']);
$smarty->assign ('tMaxquota', $values['maxquota']);
$smarty->assign ('select_options', select_options ($form_fields['transport']['options'], array ($values['transport'])),false);
$smarty->assign ('tDefaultaliases', ($values['default_aliases'] == '1') ? ' checked="checked"' : '');
$smarty->assign ('tBackupmx', ($values['backupmx'] == '1') ? ' checked="checked"' : '');
$smarty->assign ('smarty_template', 'admin_edit-domain');
$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
