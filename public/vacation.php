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
 * File: edit-vacation.php
 *
 * Allows users to update their vacation status and
 * admins to update the vacation status for the their users
 *
 * Template File: vacation.tpl
 *
 * Template Variables:
 *
 * tUseremail
 * tActiveFrom
 * tActiveUntil
 * tSubject
 * tBody
 * tInterval_time
 *
 * Form POST \ GET Variables:
 *
 * fUsername
 * fDomain
 * fCancel
 * fChange
 * fBack
 * fActive
 */

require_once('common.php');

$CONF = Config::getInstance()->getAll();
$smarty = PFASmarty::getInstance();
$now = new \DateTime();


// only allow admins to change someone else's 'stuff'
if (authentication_has_role('admin')) {
    $Admin_role = 1;
    $fUsername = safeget('username');
    list(/*NULL*/, $fDomain) = explode('@', $fUsername);
    $Return_url = "list-virtual.php?domain=" . urlencode($fDomain);

    # TODO: better check for valid username (check if mailbox exists)
    # TODO: (should be done in VacationHandler)
    if ($fDomain == '' || !check_owner(authentication_get_username(), $fDomain)) {
        die("Invalid username!"); # TODO: better error message
    }
} else {
    $Admin_role = 0;
    $Return_url = "main.php";
    authentication_require_role('user');
    $fUsername = authentication_get_username();
}

$smarty->assign('return_url', $Return_url);

// is vacation support enabled in $CONF ?
if ($CONF['vacation'] == 'NO') {
    header("Location: $Return_url");
    exit(0);
}

date_default_timezone_set(@date_default_timezone_get()); # Suppress date.timezone warnings

$error = 0;

$vh = new VacationHandler($fUsername);

$choice_of_reply = Config::read_array('vacation_choice_of_reply');
foreach (array_keys($choice_of_reply) as $key) {
    $choice_of_reply[$key] = Config::Lang($choice_of_reply[$key]);
}

$tUseremail = $fUsername;
$tInterval_Time = null;
$tBody = null;
$tSubject = null;
$tActiveFrom = new DateTime();
$tActiveUntil = new DateTime();

$details = $vh->get_details();

if (is_array($details)) {
    $tSubject = $details['subject'];
    $tBody = $details['body'];
    $tInterval_Time = $details['interval_time'];
    $tActiveFrom = new \DateTime($details['activeFrom']);
    $tActiveUntil = new \DateTime($details['activeUntil']);
} else {
    $details = ['active' => 0];
}

if ($_SERVER['REQUEST_METHOD'] == "GET") {
    if ($vh->check_vacation()) {
        flash_info(sprintf($PALANG['pUsersVacation_welcome_text'], htmlentities($tUseremail)));
    }

    //set a default, reset fields for coming back selection
    if ($tSubject == '') {
        $tSubject = html_entity_decode($PALANG['pUsersVacation_subject_text'], ENT_QUOTES, 'UTF-8');
    }
    if ($tBody == '') {
        $tBody = html_entity_decode($PALANG['pUsersVacation_body_text'], ENT_QUOTES, 'UTF-8');
    }
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    if (safepost('token') != $_SESSION['PFA_token']) {
        die('Invalid token!');
    }


    $tActiveFrom = (new \DateTime(safepost('fActiveFrom')));
    $tActiveUntil = (new \DateTime(safepost('fActiveUntil')));

    $tSubject = safepost('fSubject');
    $fSubject = $tSubject;
    $tBody = safepost('fBody');
    $fBody = $tBody;

    $tInterval_Time = safepost('fInterval_Time');

    $action = safepost('action');

    //set a default, reset fields for coming back selection
    if ($tSubject == '') {
        $tSubject = html_entity_decode($PALANG['pUsersVacation_subject_text'], ENT_QUOTES, 'UTF-8');
    }
    if ($tBody == '') {
        $tBody = html_entity_decode($PALANG['pUsersVacation_body_text'], ENT_QUOTES, 'UTF-8');
    }

    if (isset($choice_of_reply[$tInterval_Time])) {
        $fInterval_Time = (int) $tInterval_Time;
    } else {
        $fInterval_Time = 0;
    }

    // if they've set themselves change OR back, delete any record of vacation emails.
    // the user is going away - set the goto alias and vacation table as necessary.

    //Set the vacation data for $fUsername

    if ($action == 'fChange') {

        ## check if ActiveUntil is not  back in time,
        ## because vacation.pl will report SMTP recipient $smtp_recipient which resolves to $email does not have an active vacation (rv: $rv, email: $email)"
        ## and will not send message



        if (($tActiveUntil >= $now && ($tActiveUntil >= $tActiveFrom))) {
            if (!$vh->set_away($fSubject, $fBody, $fInterval_Time, $tActiveFrom->format('Y-m-d H:i'), $tActiveUntil->format('Y-m-d H:i'))) {
                $error = 1;
            }
        } else {
            if ($tActiveUntil < $now) {
                flash_error($PALANG['pVacation_until_before_today']);
            } else {
                flash_error($PALANG['pVacation_until_before_from']);
            }
            $error = 1;
        }
    }

    //if change, remove old one, then perhaps set new one
    if ($action == 'fBack') {
        if (!$vh->remove()) {
            $error = 1;
        }
    }


    // If NO error then diplay flash message  and  go back to right url where we came from
    if ($error == 0) {
        if ($action == 'fBack') {
            flash_info(sprintf($PALANG['pVacation_result_removed'], htmlentities($tUseremail)));
            header("Location: $Return_url");
            exit;
        }
        if ($action == 'fChange') {
            flash_info(sprintf($PALANG['pVacation_result_added'], htmlentities($tUseremail)));
            header("Location: $Return_url");
            exit;
        }
    } else {
        flash_error(sprintf($PALANG['pVacation_result_error'], htmlentities($fUsername)));
    }
}

if (!$details['active']) {

    # if vacation is disabled, there's no point in displaying the date of the last vacation ;-)
    # (which also means users would have to scroll in the calendar a lot)
    # so let's be user-friendly and set today's date (but only if the last vacation is in the past)
    if ($tActiveFrom < $now) {
        $tActiveFrom = $now;
    }

    if ($tActiveUntil < $now) {
        $tActiveUntil = $now;
    }
}

$smarty->assign('tUseremail', $tUseremail);
$smarty->assign('tSubject', $tSubject);
$smarty->assign('tBody', $tBody);
$smarty->assign('tActiveFrom', $tActiveFrom->format(DateTime::ISO8601));
$smarty->assign('tActiveUntil', $tActiveUntil->format(DateTime::ISO8601));
$smarty->assign('select_options', $choice_of_reply);
$smarty->assign('tInterval_Time', $tInterval_Time);
$smarty->assign('smarty_template', 'vacation');
$smarty->display('index.tpl');

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
