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
 * File: edit-alias.php
 * Users can use this to set forwards etc for their mailbox.
 *
 * Template File: users_edit-alias.tpl
 *
 */

require_once('../common.php');

$smarty = PFASmarty::getInstance();
$smarty->configureTheme('../');

$smarty->assign('smarty_template', 'users_edit-alias');

authentication_require_role('user');
$USERID_USERNAME = authentication_get_username();

// is edit-alias support enabled in $CONF ?
if (! Config::bool('edit_alias')) {
    header("Location: main.php");
    exit(0);
}

$ah = new AliasHandler();
$ah->init($USERID_USERNAME);

$smarty->assign('USERID_USERNAME', $USERID_USERNAME);


if (! $ah->view()) {
    die("Can't get alias details. Invalid alias?");
} # this can only happen if a admin deleted the user since the user logged in
$result = $ah->result();
$tGotoArray = $result['goto'];
$tStoreAndForward = $result['goto_mailbox'];

if ($_SERVER['REQUEST_METHOD'] == "GET") {
    if ($tStoreAndForward) {
        $smarty->assign('forward_and_store', ' checked="checked"');
        $smarty->assign('forward_only', '');
    } else {
        $smarty->assign('forward_and_store', '');
        $smarty->assign('forward_only', ' checked="checked"');
    }

    $smarty->assign('tGotoArray', $tGotoArray);
    $smarty->display('index.tpl');
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    if (safepost('token') != $_SESSION['PFA_token']) {
        die('Invalid token!');
    }

    // user clicked on cancel button
    if (isset($_POST['fCancel'])) {
        header("Location: main.php");
        exit(0);
    }

    $fGoto = trim(safepost('fGoto'));
    $fForward_and_store = safepost('fForward_and_store');

    # TODO: use edit.php (or create a edit_user.php)
    # TODO: this will obsolete lots of the code below (parsing $goto and the error checks)

    $goto = strtolower($fGoto);
    $goto = preg_replace('/\\\r\\\n/', ',', $goto);
    $goto = preg_replace('/\r\n/', ',', $goto);
    $goto = preg_replace('/,[\s]+/i', ',', $goto);
    $goto = preg_replace('/[\s]+,/i', ',', $goto);
    $goto = preg_replace('/\,*$/', '', $goto);

    $goto = explode(",", $goto);

    $error = 0;
    $goto = array_merge(array_unique($goto));
    $good_goto = array();

    if ($fForward_and_store != 1 && sizeof($goto) == 1 && $goto[0] == '') {
        flash_error($PALANG['pEdit_alias_goto_text_error1']);
        $error += 1;
    }
    if ($error === 0) {
        foreach ($goto as $address) {
            if ($address != "") { # $goto[] may contain a "" element
                # TODO - from https://sourceforge.net/tracker/?func=detail&aid=3027375&group_id=191583&atid=937964
                # The not-so-good news is that some internals of edit-alias aren't too nice
                # - for example, $goto[] can contain an element with empty string. I added a
                # check for that in the 2.3 branch, but we should use a better solution
                # (avoid empty elements in $goto) in trunk ;-)
                $email_check = check_email($address);
                if ($email_check != '') {
                    $error += 1;
                    flash_error("$address: $email_check");
                } else {
                    $good_goto[] = $address;
                }
            }
        }
    }

    if ($error == 0) {
        $values = array(
            'goto'          => $good_goto,
            'goto_mailbox'  => $fForward_and_store,
        );

        if (!$ah->set($values)) {
            $errormsg = $ah->errormsg;
            flash_error($errormsg[0]);
        }

        $updated = $ah->save();

        if ($updated) {
            header("Location: main.php");
            exit;
        }
        flash_error($PALANG['pEdit_alias_result_error']);
    } else {
        $tGotoArray = $goto;
    }
    $smarty->assign('tGotoArray', $tGotoArray);
    if ($fForward_and_store == 1) {
        $smarty->assign('forward_and_store', ' checked="checked"');
        $smarty->assign('forward_only', '');
    } else {
        $smarty->assign('forward_and_store', '');
        $smarty->assign('forward_only', ' checked="checked"');
    }
    $smarty->display('index.tpl');
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
