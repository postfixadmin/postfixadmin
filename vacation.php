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
 * File: edit-vacation.php
 * Responsible for allowing users to update their vacation status.
 *
 * Template File: edit-vacation.tpl
 *
 * Template Variables:
 *
 * tUseremail
 * tActiveFrom
 * tActiveUntil
 * tSubject
 * tBody
 *
 * Form POST \ GET Variables:
 *
 * fUsername
 * fDomain
 * fCancel
 * fChange
 * fBack
 * fQuota
 * fActive
 */

require_once('common.php');

// only allow admins to change someone else's 'stuff'
if(authentication_has_role('admin')) {
   $Admin_role = 1 ;
   $fUsername = safeget('username');
   list(/*NULL*/,$fDomain) = explode('@',$fUsername);
   $Return_url = "list-virtual.php?domain=" . urlencode($fDomain);

   # TODO: better check for valid username (check if mailbox exists)
   # TODO: (should be done in VacationHandler)
   if ($fDomain == '' || !check_owner(authentication_get_username(), $fDomain)) {
      die("Invalid username!"); # TODO: better error message
   }
}
else {
   $Admin_role = 0 ;
#   $Return_url = "users/main.php";
   $Return_url = "main.php";
   authentication_require_role('user');
   $fUsername = authentication_get_username();
}

// is vacation support enabled in $CONF ?
if($CONF['vacation'] == 'NO') {
  header ("Location: $Return_url");
  exit(0);
}

date_default_timezone_set(@date_default_timezone_get()); # Suppress date.timezone warnings

$vh = new VacationHandler($fUsername);


if ($_SERVER['REQUEST_METHOD'] == "GET") {
    $tSubject = '';
    $tBody = '';
    $tActiveFrom = '';
    $tActiveUntil = '';
    $tUseremail = $fUsername;
    $tReply_Type = '';
    $tInterval_Time = '';

    $details = $vh->get_details();
    if($details != false) {
        $tSubject = $details['subject'];
        $tBody = $details['body'];
   $tReply_Type = $details['reply_type'];
   $tInterval_Time = $details['interval_time'];
        $tActiveFrom = $details['activeFrom'];
        $tActiveUntil = $details['activeUntil'];
    }

    if($vh->check_vacation() and (!$Admin_role)) {
       # TODO: would also be useful for admins, but needs a text change to include the username
        flash_info($PALANG['pUsersVacation_welcome_text']);
    }

    //set a default, reset fields for coming back selection
   if ($tSubject == '') { $tSubject = html_entity_decode($PALANG['pUsersVacation_subject_text'], ENT_QUOTES, 'UTF-8'); }
   if ($tBody == '') { $tBody = html_entity_decode($PALANG['pUsersVacation_body_text'], ENT_QUOTES, 'UTF-8'); }

    if ($tReply_Type =='') { $tReply_Type = $CONF['vacation_replytype_default'];}
    if ($tReply_Type =='One Reply') { $tInterval_Time = '0';}
    if ($tReply_Type =='Auto Reply') { $tInterval_Time = $CONF['vacation_autoreplydelay_default'];}
    if (($tReply_Type =='Interval Reply') and ($tInterval_Time =='')) { $tInterval_Time = $CONF['vacation_intervaldelay_default'];}
    if (($tReply_Type =='Interval Reply') and ($tInterval_Time <= $CONF['vacation_autoreplydelay_default'])) { $tInterval_Time = $CONF['vacation_intervaldelay_default'];}

}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    if(isset($_POST['fCancel'])) {
	header ("Location: $Return_url");
        exit(0);
    }

   $tActiveFrom = date ("Y-m-d 00:00:00", strtotime (safepost('fActiveFrom')));
   $tActiveUntil = date ("Y-m-d 23:59:59", strtotime (safepost('fActiveUntil')));

   $tSubject   = safepost('fSubject');
   $fSubject   = $tSubject;
   $tBody      = safepost('fBody');
   $fBody	= $tBody;

   $tReply_Type = safepost('fReply_Type');
   $tInterval_Time = safepost('fInterval_Time');

   $fChange    = escape_string (safepost('fChange'));
   $fBack      = escape_string (safepost('fBack'));

   $tUseremail = $fUsername;

    //set a default, reset fields for coming back selection
   if ($tSubject == '') { $tSubject = html_entity_decode($PALANG['pUsersVacation_subject_text'], ENT_QUOTES, 'UTF-8'); }
   if ($tBody == '') { $tBody = html_entity_decode($PALANG['pUsersVacation_body_text'], ENT_QUOTES, 'UTF-8'); }
   if ($tReply_Type =='')  { $tReply_Type = $CONF['vacation_replytype_default'];}
   if ($tReply_Type =='One Reply')  { $tInterval_Time = '0';}
   if ($tReply_Type =='Auto Reply') { $tInterval_Time = $CONF['vacation_autoreplydelay_default'];}
   if (($tReply_Type =='Interval Reply') and ($tInterval_Time ==''))  { $tInterval_Time  = $CONF['vacation_intervaldelay_default'];}
   if (($tReply_Type =='Interval Reply') and ($tInterval_Time <= $CONF['vacation_autoreplydelay_default']))  { $tInterval_Time = $CONF['vacation_intervaldelay_default'];}

   $fReply_Type = $tReply_Type ;
   $fInterval_Time = $tInterval_Time;

   // if they've set themselves change OR back, delete any record of vacation emails.
   // the user is going away - set the goto alias and vacation table as necessary.

   //Set the vacation data for $fUsername
   if (!empty ($fChange))
   {
      if(!$vh->set_away($fSubject, $fBody, $fReply_Type, $fInterval_Time, $tActiveFrom, $tActiveUntil)) {
            $error = 1;
        }
   }

   //if change, remove old one, then perhaps set new one
   if (!empty ($fBack))
   {
      if(!$vh->remove()) {
        $error = 1;
      }
   }
}

// If NO error then diplay flash message  and  go back to right url where we came from
if($error == 0) {
   if(!empty ($fBack)) {
      flash_info(sprintf($PALANG['pVacation_result_removed'],htmlentities($tUseremail)));
      header ("Location: $Return_url");
      exit;
   }
   if(!empty($fChange)) {
      flash_info(sprintf($PALANG['pVacation_result_added'],htmlentities($tUseremail)));
      header ("Location: $Return_url");
      exit;
   }
}
else {
	flash_error($PALANG['pVacation_result_error']);
}
if (empty ($tActiveFrom))
   $tActiveFrom = date ("Y-m-d");
if (empty ($tActiveUntil))
   $tActiveUntil = date ("Y-m-d");

$smarty->assign ('tUseremail', $tUseremail);
$smarty->assign ('tSubject', $tSubject);
$smarty->assign ('tBody', $tBody);
$smarty->assign ('tActiveFrom',  date ("d.m.Y", strtotime ($tActiveFrom)));
$smarty->assign ('tActiveUntil',  date ("d.m.Y", strtotime ($tActiveUntil)));
$smarty->assign ('select_options', select_options ( $CONF ['vacation_choice_of_reply'], array ($tReply_Type)),false);
$smarty->assign ('tInterval_Time', $tInterval_Time);
$smarty->assign ('smarty_template', 'vacation');
$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
