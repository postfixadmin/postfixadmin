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
 * File: vacation.php
 * Used by users to set/change their vacation settings.
 *
 * Template File: users_vacation.tpl
 *
 * Template Variables:
 *
 * tMessage
 * tSubject
 * tBody
 *
 * Form POST \ GET Variables:
 *
 * fSubject
 * fBody
 * fAway
 * fBack
 */

require_once('../common.php');

authentication_require_role('user');
$USERID_USERNAME = authentication_get_username();

// is vacation support enabled in $CONF ?
if($CONF['vacation'] == 'NO') {
    header("Location: " . $CONF['postfix_admin_url'] . "/users/main.php");
    exit(0);
}

$vh = new VacationHandler(authentication_get_username());

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
    $tSubject = '';
    $tBody = '';
	$tActiveFrom = '';
	$tActiveUntil = '';
		
	$details = $vh->get_details();
	if($details != false) {
        $tSubject = $details['subject'];
        $tBody = $details['body'];
		$fActiveFrom = $details['activeFrom'];
		$fActiveUntil = $details['activeUntil'];
    }
    if($vh->check_vacation()) {
        $tMessage = $PALANG['pUsersVacation_welcome_text'];
    }

    if ($tSubject == '') { $tSubject = html_entity_decode($PALANG['pUsersVacation_subject_text'], ENT_QUOTES, 'UTF-8'); }
    if ($tBody == '') { $tBody = html_entity_decode($PALANG['pUsersVacation_body_text'], ENT_QUOTES, 'UTF-8'); }
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    if(isset($_POST['fCancel'])) {
        header("Location: main.php");
        exit(0);
    }

    if (isset ($_POST['fSubject'])) $fSubject = $_POST['fSubject'];
    if (isset ($_POST['fBody']))    $fBody    = $_POST['fBody'];
    if (isset ($_POST['fAway'])) $fAway = escape_string ($_POST['fAway']);
    if (isset ($_POST['fBack'])) $fBack = escape_string ($_POST['fBack']);
	if (isset ($_POST['fActiveFrom'])) $tActiveFrom = date ("Y-m-d 00:00:00", strtotime ($_POST['fActiveFrom']));
	if (isset ($_POST['fActiveUntil'])) $tActiveUntil = date ("Y-m-d 23:59:59", strtotime ($_POST['fActiveUntil']));

    //set a default, reset fields for coming back selection
    if ($tSubject == '') { $tSubject = html_entity_decode($PALANG['pUsersVacation_subject_text'], ENT_QUOTES, 'UTF-8'); }
    if ($tBody == '') { $tBody = html_entity_decode($PALANG['pUsersVacation_body_text'], ENT_QUOTES, 'UTF-8'); }

    // if they've set themselves away OR back, delete any record of vacation emails.

    // the user is going away - set the goto alias and vacation table as necessary.
    if (!empty ($fAway))
    {
        if(!$vh->set_away($fSubject, $fBody, $tActiveFrom, $tActiveUntil)) {
            $error = 1;
            $tMessage = $PALANG['pUsersVacation_result_error'];
        }
        flash_info($PALANG['pVacation_result_added']);
        header ("Location: main.php");
        exit;
    }

    if (!empty ($fBack)) {
        $vh->remove();
        $tMessage = $PALANG['pUsersVacation_result_success'];
        flash_info($tMessage);
        header ("Location: main.php");
        exit;
    }
}

$smarty->assign ('tSubject', htmlentities ($tSubject, ENT_QUOTES, 'UTF-8'), false);
$smarty->assign ('tBody', htmlentities ($tBody, ENT_QUOTES, 'UTF-8'), false);
$smarty->assign ('tMessage', $tMessage, false);
$smarty->assign ('tActiveFrom',  date ("d.m.Y", strtotime ($fActiveFrom)));
$smarty->assign ('tActiveUntil',  date ("d.m.Y", strtotime ($fActiveUntil)));
$smarty->assign ('smarty_template', 'users_vacation');
$smarty->display ('index.tpl');
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
