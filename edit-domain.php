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
        $tMaxquota = $domain_properties['maxquota'];
        $tTransport = $domain_properties['transport'];
        $tBackupmx = $domain_properties['backupmx'];
        $tActive = $domain_properties['active'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    if (isset ($_GET['domain'])) $domain = escape_string ($_GET['domain']);

    if (isset ($_POST['fDescription'])) $fDescription = escape_string ($_POST['fDescription']);
    if (isset ($_POST['fAliases'])) $fAliases = intval($_POST['fAliases']);
    if (isset ($_POST['fMailboxes'])) $fMailboxes = intval($_POST['fMailboxes']);
    if (isset ($_POST['fMaxquota'])) {
        $fMaxquota = intval($_POST['fMaxquota']);
    } else {
        $fMaxquota = 0;
    }

    $fTransport = $CONF['transport_default'];
    if($CONF['transport'] != 'NO' && isset ($_POST['fTransport'])) {
        $fTransport = escape_string($_POST['fTransport']);
        if(!in_array($fTransport, $CONF['transport_options'])) {
            die("Invalid transport option given; check config.inc.php");
        }
    }

    if (isset ($_POST['fBackupmx'])) $fBackupmx = escape_string ($_POST['fBackupmx']);
    if (isset ($_POST['fActive'])) $fActive = escape_string ($_POST['fActive']);

    if ($fBackupmx == "on")
    {
        $fBackupmx = 1;
        $sqlBackupmx = db_get_boolean(True);
    }
    else
    {
        $fBackupmx = 0;
        $sqlBackupmx = db_get_boolean(False);
    }

    if ($fActive == "on") { 
        $sqlActive = db_get_boolean(True);
    }
    else {
        $sqlActive = db_get_boolean(False);
    }

    $sqltransport = "";
    if($CONF['transport'] != 'NO') { # only change transport if it is allowed in config. Otherwise, keep the old value.
       $sqltransport = "transport='$fTransport',";
    }

    $result = db_query ("UPDATE $table_domain SET description='$fDescription',aliases=$fAliases,mailboxes=$fMailboxes,maxquota=$fMaxquota,$sqltransport backupmx='$sqlBackupmx',active='$sqlActive',modified=NOW() WHERE domain='$domain'");
    if ($result['rows'] == 1)
    {
        header ("Location: list-domain.php");
        exit;
    }
    else
    {
        $tMessage = $PALANG['pAdminEdit_domain_result_error'];
    }
}

$smarty->assign ('domain', $domain);
$smarty->assign ('tDescription', htmlspecialchars($tDescription, ENT_QUOTES), false);
$smarty->assign ('tAliases', $tAliases);
$smarty->assign ('tMailboxes', $tMailboxes);
$smarty->assign ('tMaxquota', $tMaxquota);
$smarty->assign ('select_options', select_options ($CONF ['transport_options'], array ($tTransport)), false);
if ($tBackupmx)	$smarty->assign ('tBackupmx', ' checked="checked"');
if ($tActive)	$smarty->assign ('tActive', ' checked="checked"');
$smarty->assign ('tMessage', $tMessage,false);
$smarty->assign ('smarty_template', 'admin_edit-domain');
$smarty->display ('index.tpl');


/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
