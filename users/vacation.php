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
 * tSubject
 * tBody
 *
 * Form POST \ GET Variables:
 *
 * fSubject
 * fBody
 * fChange
 * fBack
 */

require_once('../common.php');

authentication_require_role('user');
$USERID_USERNAME = authentication_get_username();
$fUsername = authentication_get_username();

// is vacation support enabled in $CONF ?
if($CONF['vacation'] == 'NO') {
    header("Location: main.php");
    exit(0);
}

date_default_timezone_set(@date_default_timezone_get()); # Suppress date.timezone warnings

$vh = new VacationHandler($fUsername);
//$vh = new VacationHandler(authentication_get_username());

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
        flash_info($PALANG['pUsersVacation_welcome_text']);
    }

    $tUseremail = $fUsername;

    //set a default, reset fields for coming back selection
    if ($tSubject == '') { $tSubject = html_entity_decode($PALANG['pUsersVacation_subject_text'], ENT_QUOTES, 'UTF-8'); }
    if ($tBody == '') { $tBody = html_entity_decode($PALANG['pUsersVacation_body_text'], ENT_QUOTES, 'UTF-8'); }
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    if(isset($_POST['fCancel'])) {
        header("Location: main.php");
        exit(0);
    }


    $tActiveFrom = date ("Y-m-d 00:00:00", strtotime (safepost('fActiveFrom')));
    $tActiveUntil = date ("Y-m-d 23:59:59", strtotime (safepost('fActiveUntil')));

    $tSubject   = safepost('fSubject');
    $fSubject   = $tSubject;
    $tBody      = safepost('fBody');
    $fBody      = $tBody;

    $fChange    = escape_string (safepost('fChange'));
    $fBack      = escape_string (safepost('fBack'));

    $tUseremail = $fUsername;

    //set a default, reset fields for coming back selection
    if ($tSubject == '') { $tSubject = html_entity_decode($PALANG['pUsersVacation_subject_text'], ENT_QUOTES, 'UTF-8'); }
    if ($tBody == '') { $tBody = html_entity_decode($PALANG['pUsersVacation_body_text'], ENT_QUOTES, 'UTF-8'); }

    // if they've set themselves change OR back, delete any record of vacation emails.
    // the user is going away - set the goto alias and vacation table as necessary.

//    if (!empty ($fChange))
//    {
//        if(!$vh->set_away($fSubject, $fBody, $tActiveFrom, $tActiveUntil)) {
//            $error = 1;
//            flash_error($PALANG['pUsersVacation_result_error']);
//        }
//       flash_info($PALANG['pVacation_result_added']);
//        header ("Location: main.php");
//        exit;
//    }
//
//    if (!empty ($fBack)) {
//        $vh->remove();
//        flash_info($PALANG['pUsersVacation_result_success']);
//        header ("Location: main.php");
//        exit;
//    }

   //Set the vacation data for $fUsername
   if (!empty ($fChange))
   {
      if(!$vh->set_away($fSubject, $fBody, $tActiveFrom, $tActiveUntil)) {
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
      $Flash_Message = $PALANG['pVacation_result_removed'] ; //TODO adding useremail to flash info depending on $
      flash_info($Flash_Message);
      header ("Location: main.php");
      exit;
   }
   if(!empty($fChange)) {
      $Flash_Message =( $PALANG['pVacation_result_added']); //TODO adding useremail to flash info depending on $$
      flash_info($Flash_Message);
      header ("Location: main.php");
      exit;
   }
}
else {
   $Flash_Message = $PALANG['pVacation_result_error'] ; //TODO adding useremail to flash info depending on $Role
   flash_error($Flash_Message);
}

if (empty ($fActiveFrom))
	$fActiveFrom = date ("Y-m-d");
if (empty ($fActiveUntil))
	$fActiveUntil = date ("Y-m-d");

$smarty->assign ('tUseremail', $tUseremail);
$smarty->assign ('tSubject', $tSubject);
$smarty->assign ('tBody', $tBody);
$smarty->assign ('tActiveFrom',  date ("d.m.Y", strtotime ($fActiveFrom)));
$smarty->assign ('tActiveUntil',  date ("d.m.Y", strtotime ($fActiveUntil)));
$smarty->assign ('smarty_template', 'vacation');
$smarty->display ('index.tpl');
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
