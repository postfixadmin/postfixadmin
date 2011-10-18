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
 *
 * Template Variables:
 *
 * tDomain
 * tDescription
 * tAliases
 * tMailboxes
 * tMaxquota
 * tDefaultaliases
 *
 * Form POST \ GET Variables:
 *
 * fDomain
 * fDescription
 * fAliases
 * fMailboxes
 * fMaxquota
 * fDefaultaliases
 */

require_once('common.php');

authentication_require_role('global-admin');


$form_fields = array(
    'fDomain'         => array('type' => 'str', 'default' => null),
    'fDescription'    => array('type' => 'str', 'default' =>''), 
    'fAliases'        => array('type' => 'int', 'default' => $CONF['aliases']), 
    'fMailboxes'      => array('type' => 'int', 'default' => $CONF['mailboxes']), 
    'fMaxquota'       => array('type' => 'int', 'default' => $CONF['maxquota']),
    'fDomainquota'    => array('type' => 'int', 'default' => $CONF['domain_quota_default']),
    'fTransport'      => array('type' => 'str', 'default' => $CONF['transport_default'], 'options' => $CONF['transport_options']), 
    'fDefaultaliases' => array('type' => 'bool', 'default' => '1', 'options' => array(1, 0)), 
    'fBackupmx'       => array('type' => 'bool', 'default' => '0', 'options' => array(1, 0)) 
);

$fDefaultaliases = "";
$tDefaultaliases = "";

# TODO: this foreach block should only be executed for POST
foreach($form_fields  as $key => $default) {
    if($default['type'] == 'bool' && $_SERVER['REQUEST_METHOD'] == "POST") {
        $$key = escape_string(safepost($key, 0)); # isset for unchecked checkboxes is always false
    } 
    elseif (isset($_POST[$key]) && (strlen($_POST[$key]) > 0)) {
        $$key = escape_string($_POST[$key]);
    }
    else {
        $$key = $default['default'];
    }
    if($default['type'] == 'int') {
        $$key = intval($$key);
    }
    if($default['type'] == 'str') {
        $$key = strip_tags($$key); /* should we even bother? */
    }
    if(isset($default['options'])) {
        if(!in_array($$key, $default['options'])) {
            die("Invalid parameter given for $key");
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
    /* default values as set above */
    $tTransport = $fTransport;
    $tAliases = $fAliases;
    $tMaxquota = $fMaxquota;
    $tDomainquota = $fDomainquota;
    $tMailboxes = $fMailboxes;
    $tDefaultaliases = $fDefaultaliases;
    $tBackupmx = $fBackupmx;
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    $tBackupmx = "";

    $handler =  new DomainHandler($fDomain, 1);
    if (!$handler->result()) {
        $error = 1;
        $tDomain = $fDomain;
        $tDescription = $fDescription;
        $tAliases = $fAliases;
        $tMailboxes = $fMailboxes;
        if (isset ($_POST['fMaxquota'])) $tMaxquota = $fMaxquota;
        if (isset ($_POST['fDomainquota'])) $tDomainquota = $fDomainquota;
        if (isset ($_POST['fTransport'])) $tTransport = $fTransport;
        if (isset ($_POST['fDefaultaliases'])) $tDefaultaliases = $fDefaultaliases;
        if (isset ($_POST['fBackupmx'])) $tBackupmx = $fBackupmx;
        $pAdminCreate_domain_domain_text_error = join("<br />", $handler->errormsg);
    }

    if ($error != 1)
    {
        $tAliases = $CONF['aliases'];
        $tMailboxes = $CONF['mailboxes'];
        $tMaxquota = $CONF['maxquota'];
        $tDomainquota = $CONF['domain_quota_default'];

        $values = array(
           'description'     => $fDescription,
           'aliases'         => $fAliases,
           'mailboxes'       => $fMailboxes,
           'maxquota'        => $fMaxquota,
           'quota'           => $fDomainquota,
           'transport'       => $fTransport,
           'backupmx'        => $fBackupmx,
           'active'          => 1, # hardcoded for now - TODO: change this ;-)
           'default_aliases' => $fDefaultaliases,
        );

        if (!$handler->set($values)) {
            $pAdminCreate_domain_domain_text_error = join("<br />", $handler->errormsg);
        } else {
            if (!$handler->store()) {
                $pAdminCreate_domain_domain_text_error = join("\n", $handler->errormsg);
            } else {
                flash_info($PALANG['pAdminCreate_domain_result_success'] . " ($fDomain)"); # TODO: use a sprintf string
                if (count($handler->errormsg)) { # might happen if domain_postcreation fails
                    flash_error(join("<br />", $handler->errormsg));
                }
            }
        }
    }
}


$smarty->assign ('mode', 'create');
$smarty->assign ('tDomain', $tDomain);
$smarty->assign ('pAdminCreate_domain_domain_text', $pAdminCreate_domain_domain_text, false);
$smarty->assign ('pAdminCreate_domain_domain_text_error', $pAdminCreate_domain_domain_text_error, false);
$smarty->assign ('tDescription', $tDescription, false);
$smarty->assign ('tAliases', $tAliases);
$smarty->assign ('tMailboxes', $tMailboxes);
$smarty->assign ('tDomainquota', $tDomainquota);
$smarty->assign ('tMaxquota', $tMaxquota,false); # TODO: why is sanitize disabled? Should be just integer...
$smarty->assign ('select_options', select_options ($CONF ['transport_options'], array ($tTransport)),false);
$smarty->assign ('tDefaultaliases', ($tDefaultaliases == '1') ? ' checked="checked"' : '');
$smarty->assign ('tBackupmx', ($tBackupmx == '1') ? ' checked="checked"' : '');
$smarty->assign ('smarty_template', 'admin_edit-domain');
$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
