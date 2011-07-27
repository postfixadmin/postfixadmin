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
 * tSubject
 * tBody
 *
 * Form POST \ GET Variables:
 *
 * fUsername
 * fDomain
 * fCanceltarget
 * fChange
 * fBack
 * fQuota
 * fActive
 */

require_once('common.php');

if($CONF['vacation'] == 'NO') {
   header("Location: list-virtual.php");
   exit(0);
}

$SESSID_USERNAME = authentication_get_username();
$tmp = preg_split ('/@/', $SESSID_USERNAME);
$USERID_DOMAIN = $tmp[1];

// only allow admins to change someone else's 'stuff'
if(authentication_has_role('admin')) {
   if (isset($_GET['username'])) $fUsername = escape_string ($_GET['username']);
   if (isset($_GET['domain'])) $fDomain = escape_string ($_GET['domain']);
}
else {
   $fUsername = $SESSID_USERNAME;
   $fDomain = $USERID_DOMAIN;
}

date_default_timezone_set(@date_default_timezone_get()); # Suppress date.timezone warnings

$vh = new VacationHandler($fUsername);

$fCanceltarget = "list-virtual.php?domain=$fDomain";

if ($_SERVER['REQUEST_METHOD'] == "GET")
{

   $result = db_query("SELECT * FROM $table_vacation WHERE email='$fUsername'");
   if ($result['rows'] == 1)
   {
      $row = db_array($result['result']);
      $tSubject = $row['subject'];
      $tBody = $row['body'];
      $tActiveFrom = $row['activefrom'];
      $tActiveUntil = $row['activeuntil'];
   }

   $tUseremail = $fUsername;
   $tDomain = $fDomain;
   if ($tSubject == '') { $tSubject = html_entity_decode($PALANG['pUsersVacation_subject_text'], ENT_QUOTES, 'UTF-8'); }
   if ($tBody == '') { $tBody = html_entity_decode($PALANG['pUsersVacation_body_text'], ENT_QUOTES, 'UTF-8'); }

}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{

   $tSubject   = safepost('fSubject');
   $fSubject   = escape_string ($tSubject);
   $tBody      = safepost('fBody');
//   $tActiveFrom = safepost('activefrom').' 00:00:01';
//   $tActiveUntil = safepost('activeuntil').' 23:59:59';
   $tActiveFrom = date ("Y-m-d 00:00:00", strtotime (safepost('activefrom')));
   $tActiveUntil = date ("Y-m-d 23:59:59", strtotime (safepost('activeuntil')));
   $fBody      = escape_string ($tBody);
   $fChange    = escape_string (safepost('fChange'));
   $fBack      = escape_string (safepost('fBack'));
   if(authentication_has_role('admin') && isset($_GET['domain'])) {
      $fDomain = escape_string ($_GET['domain']);
   }
   else {
      $fDomain = $USERID_DOMAIN;
   }
   if(authentication_has_role('admin') && isset ($_GET['username'])) {
      $fUsername = escape_string($_GET['username']);
   }
   else {
      $fUsername = authentication_get_username();
   }

   $tUseremail = $fUsername;
   if ($tSubject == '') { $tSubject = html_entity_decode($PALANG['pUsersVacation_subject_text'], ENT_QUOTES, 'UTF-8'); }
   if ($tBody == '') { $tBody = html_entity_decode($PALANG['pUsersVacation_body_text'], ENT_QUOTES, 'UTF-8'); }

   //if change, remove old one, then perhaps set new one
   if (!empty ($fBack))
   {
      if(!$vh->remove()) {
        $error = 1;
      }
   }


   //Set the vacation data for $fUsername
   if (!empty ($fChange))
   {
      if(!$vh->set_away($fSubject, $fBody, $tActiveFrom, $tActiveUntil)) {
            $error = 1;
        }

   }
}

if($error == 0) {
   if(!empty ($fBack)) {
      flash_info($PALANG['pVacation_result_removed']);
   }
   if(!empty($fChange)) {
      flash_info($PALANG['pVacation_result_added']);
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
$smarty->assign ('tBody', $tBody ,false);
$smarty->assign ('tActiveFrom',  date ("d.m.Y", strtotime ($tActiveFrom)));
$smarty->assign ('tActiveUntil',  date ("d.m.Y", strtotime ($tActiveUntil)));
$smarty->assign ('fCanceltarget', $fCanceltarget);
$smarty->assign ('smarty_template', 'edit-vacation');
$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
