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
 * File: edit-domain.php 
 * Updates the properties of a domain.
 * Template File: admin_edit-domain.tpl
 *
 * Template Variables:
 *
 * tDescription
 * tAliases
 * tMailboxes
 * tMaxquota
 * tActive
 *
 * Form POST \ GET Variables:
 *
 * fDescription
 * fAliases
 * fMailboxes
 * fMaxquota
 * fActive
 */

require_once('common.php');

authentication_require_role('global-admin');

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
    if (isset ($_GET['domain']))
    {
        $domain = escape_string ($_GET['domain']);
        $domain_properties = get_domain_properties ($domain);

        $tDescription = $domain_properties['description'];
        $tAliases = $domain_properties['aliases'];
        $tMailboxes = $domain_properties['mailboxes'];
        $tDomainquota = $domain_properties['quota'];
        $tMaxquota = $domain_properties['maxquota'];
        $tTransport = $domain_properties['transport'];
        $tBackupmx = $domain_properties['backupmx'];
        $tActive = $domain_properties['active'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    if (isset ($_GET['domain'])) $domain = escape_string ($_GET['domain']);

    $fDescription   =       safepost('description');
    $fAliases       = (int) safepost('aliases');
    $fMailboxes     = (int) safepost('mailboxes');
    $fMaxquota      = (int) safepost('maxquota', 0);
    $fQuota         = (int) safepost('quota', $CONF['domain_quota_default']);
    # TODO: check for / error out on values < -1

    $fTransport = $CONF['transport_default'];
    if($CONF['transport'] != 'NO' && isset ($_POST['transport'])) {
        $fTransport = escape_string($_POST['transport']);
        if(!in_array($fTransport, $CONF['transport_options'])) {
            die("Invalid transport option given; check config.inc.php");
        }
    }

    if (isset ($_POST['backupmx'])) $fBackupmx = (int) escape_string ($_POST['backupmx']);
    if (isset ($_POST['active']))   $fActive   = (int) escape_string ($_POST['active']);

    if ($fBackupmx == 1)
    {
        $fBackupmx = 1;
        $sqlBackupmx = db_get_boolean(True);
    }
    else
    {
        $fBackupmx = 0;
        $sqlBackupmx = db_get_boolean(False);
    }

    if ($fActive == 1) { 
        $sqlActive = db_get_boolean(True);
    }
    else {
        $sqlActive = db_get_boolean(False);
    }

    $db_values = array(
       'description'=> $fDescription,
       'aliases'    => $fAliases,
       'mailboxes'  => $fMailboxes,
       'maxquota'   => $fMaxquota,
       'quota'      => $fQuota,
       'backupmx'   => $sqlBackupmx,
       'active'     => $sqlActive,
    );

    if($CONF['transport'] != 'NO') { # only change transport if it is allowed in config. Otherwise, keep the old value.
        $db_values['transport'] =$fTransport;
    }

    $result = db_update('domain', 'domain', $domain, $db_values);

    if ($result == 1) {
        header ("Location: list-domain.php");
        exit;
    } else {
        flash_error($PALANG['pAdminEdit_domain_result_error']);
    }
}

$smarty->assign ('mode', 'edit');
$smarty->assign ('pAdminCreate_domain_domain_text_error', '');
$smarty->assign ('domain', $domain);
$smarty->assign ('tDescription', $tDescription);
$smarty->assign ('tAliases', $tAliases);
$smarty->assign ('tMailboxes', $tMailboxes);
$smarty->assign ('tMaxquota', $tMaxquota);
$smarty->assign ('tQuota', $tDomainquota);
$smarty->assign ('tTransport', select_options($CONF['transport_options'], array($tTransport)), false);
if ($tBackupmx)	$smarty->assign ('tBackupmx', ' checked="checked"');
if ($tActive)	$smarty->assign ('tActive', ' checked="checked"');
$smarty->assign ('smarty_template', 'admin_edit-domain');
$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
