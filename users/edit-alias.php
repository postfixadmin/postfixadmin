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
 * File: edit-alias.php
 * Users can use this to set forwards etc for their mailbox.
 *
 * Template File: users_edit-alias.tpl
 *
 * Template Variables:
 *
 * tMessage
 * tGotoArray
 * tStoreAndForward
 *
 * Form POST \ GET Variables:
 *
 * fAddress
 * fGoto
 */

require_once('../common.php');
$smarty->assign ('smarty_template', 'users_edit-alias');

authentication_require_role('user');
$USERID_USERNAME = authentication_get_username();

$tmp = preg_split ('/@/', $USERID_USERNAME);
$USERID_DOMAIN = $tmp[1];

$vacation_domain = $CONF['vacation_domain'];
$vacation_goto = preg_replace('/@/', '#', $USERID_USERNAME) . '@' . $vacation_domain;

$ah = new AliasHandler($USERID_USERNAME);
$smarty->assign ('USERID_USERNAME', $USERID_USERNAME);


if ( ! $ah->get() ) die("Can't get alias details. Invalid alias?"); # this can only happen if a admin deleted the user since the user logged in
$tGotoArray = $ah->result();
$tStoreAndForward = $ah->hasStoreAndForward();
$vacation_domain = $CONF['vacation_domain'];

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
    ($tStoreAndForward) ? $smarty->assign ('forward_and_store', ' checked="checked"') : $smarty->assign ('forward_only', ' checked="checked"');

    $smarty->assign ('tGotoArray', $tGotoArray);
    $smarty->display ('index.tpl');
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    // user clicked on cancel button
    if(isset($_POST['fCancel'])) {
        header("Location: main.php");
        exit(0);
    }

    $pEdit_alias_goto = $PALANG['pEdit_alias_goto'];

    if (isset($_POST['fGoto'])) $fGoto = trim($_POST['fGoto']);
    if (isset($_POST['fForward_and_store'])) $fForward_and_store = $_POST['fForward_and_store'];

    $goto = strtolower ($fGoto);
    $goto = preg_replace ('/\\\r\\\n/', ',', $goto);
    $goto = preg_replace ('/\r\n/', ',', $goto);
    $goto = preg_replace ('/,[\s]+/i', ',', $goto);
    $goto = preg_replace ('/[\s]+,/i', ',', $goto);
    $goto = preg_replace ('/\,*$/', '', $goto);

    $goto = explode(",",$goto);

    $error = 0;
    $goto = array_merge(array_unique($goto));
    $good_goto = array();

    if($fForward_and_store != 'YES' && sizeof($goto) == 1 && $goto[0] == '') {
        $tMessage = $PALANG['pEdit_alias_goto_text_error1'];
        $error += 1;
    }
    if($error === 0) {
        foreach($goto as $address) {
          if ($address != "") { # $goto[] may contain a "" element
            # TODO - from https://sourceforge.net/tracker/?func=detail&aid=3027375&group_id=191583&atid=937964 
            # The not-so-good news is that some internals of edit-alias aren't too nice
            # - for example, $goto[] can contain an element with empty string. I added a
            # check for that in the 2.3 branch, but we should use a better solution
            # (avoid empty elements in $goto) in trunk ;-)
            if(!check_email($address)) {
                $error += 1;
                if (!empty($tMessage)) $tMessage .= "<br />";
                $tMessage .= $PALANG['pEdit_alias_goto_text_error2'] . " $address</font>";
            }
            else {
                $good_goto[] = $address;
            }
          }
        }
    }

    if ($error == 0) {
        $flags = 'remote_only';
        if($fForward_and_store == "YES" ) {
            $flags = 'forward_and_store';
        }
        $updated = $ah->update($good_goto, $flags);
        if($updated) {
            header ("Location: main.php");
            exit;
        }
        if (!empty($tMessage)) $tMessage .= "<br />";
        $tMessage .= $PALANG['pEdit_alias_result_error'];
    }
    else {
        $tGotoArray = $goto;
    }
    $smarty->assign ('tMessage', $tMessage, false);
    $smarty->assign ('tGotoArray', $tGotoArray);
    if ($fForward_and_store == "YES") {
        $smarty->assign ('forward_and_store', ' checked="checked"');
    } else {
        $smarty->assign ('forward_only', ' checked="checked"');
    }
    $smarty->display ('index.tpl');
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
