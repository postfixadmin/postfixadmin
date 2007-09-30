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
 * tMessage
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

$SESSID_USERNAME = authentication_get_username();

if($CONF['vacation'] == 'NO') { 
   header("Location: " . $CONF['postfix_admin_url'] . "/main.php");
   exit(0);
}

$vacation_domain = $CONF['vacation_domain'];
$vacation_goto = preg_replace('/@/', '#', $SESSID_USERNAME);
$vacation_goto = $vacation_goto . '@' . $vacation_domain;

$tmp = preg_split ('/@/', $SESSID_USERNAME);
$USERID_DOMAIN = $tmp[1];

if (isset ($_GET['username'])) $fUsername = escape_string ($_GET['username']);
if (isset ($_GET['domain'])) $fDomain = escape_string ($_GET['domain']);

$fCanceltarget = $CONF['postfix_admin_url'] . '/main.php';

if ($_SERVER['REQUEST_METHOD'] == "GET")
{

   $result = db_query("SELECT * FROM $table_vacation WHERE email='$fUsername'");
   if ($result['rows'] == 1)
   {
      $row = db_array($result['result']);
      $tMessage = '';
      $tSubject = $row['subject'];
      $tBody = $row['body'];
   }

   $tUseremail = $fUsername;
   if ($tSubject == '') { $tSubject = $PALANG['pUsersVacation_subject_text']; }
   if ($tBody == '') { $tBody = $PALANG['pUsersVacation_body_text']; }

}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{

   if (isset ($_POST['fSubject'])) $fSubject = escape_string ($_POST['fSubject']);
   if (isset ($_POST['fBody'])) $fBody = escape_string ($_POST['fBody']);
   if (isset ($_POST['fChange'])) $fChange = escape_string ($_POST['fChange']);
   if (isset ($_POST['fBack'])) $fBack = escape_string ($_POST['fBack']);

   if (isset ($_GET['domain'])) {
      $fDomain = escape_string ($_GET['domain']);
   }
   else {
      $fDomain = $USERID_DOMAIN;
   }
   if (isset ($_GET['username'])) {
      $fUsername = escape_string ($_GET['username']);
   }
   else {
      $fUsername = authentication_get_username();
   }

   $tUseremail = $fUsername;
   if ($tSubject == '') { $tSubject = $PALANG['pUsersVacation_subject_text']; }
   if ($tBody == '') { $tBody = $PALANG['pUsersVacation_body_text']; }

   //if change, remove old one, then perhaps set new one
   if (!empty ($fBack) || !empty ($fChange))
   {
      //if we find an existing vacation entry, delete it
      $result = db_query("SELECT * FROM $table_vacation WHERE email='$fUsername'");
      if ($result['rows'] == 1)
      {
         $result = db_query ("DELETE FROM $table_vacation WHERE email='$fUsername'");
         if ($result['rows'] != 1)
         {
            $error = 1;
         }

         $result = db_query ("SELECT * FROM $table_alias WHERE address='$fUsername'");
         if ($result['rows'] == 1)
         {
            $row = db_array ($result['result']);
            $goto = $row['goto'];

            //only one of these will do something, first handles address at beginning and middle, second at end
            $goto= preg_replace ( "/$vacation_goto,/", '', $goto);
            $goto= preg_replace ( "/,$vacation_goto/", '', $goto);

            $result = db_query ("UPDATE $table_alias SET goto='$goto',modified=NOW() WHERE address='$fUsername'");
            if ($result['rows'] != 1)
            {
               $error = 1;
            }
         }
      }
   }


   //Set the vacation data for $fUsername
   if (!empty ($fChange))
   {
      $goto = '';
      $result = db_query ("SELECT * FROM $table_alias WHERE address='$fUsername'");
      if ($result['rows'] == 1)
      {
         $row = db_array ($result['result']);
         $goto = $row['goto'];
      }

      ($CONF['database_type']=='pgsql') ? $Active='true' : $Active=1;
      $result = db_query ("INSERT INTO $table_vacation (email,subject,body,domain,created,active) VALUES ('$fUsername','$fSubject','$fBody','$fDomain',NOW(),$Active)");

      if ($result['rows'] != 1)
      {
         $error = 1;
      }

      $goto = $goto . "," . $vacation_goto;

      $result = db_query ("UPDATE $table_alias SET goto='$goto',modified=NOW() WHERE address='$fUsername'");
      if ($result['rows'] != 1)
      {
         $error = 1;
      }
   }
}

if($error == 0) {
   if(!empty ($fBack)) {
      $tMessage = $PALANG['pVacation_result_removed'];
   }
   if(!empty($fChange)) {
      $tMessage= $PALANG['pVacation_result_added'];   
   }
}
else {
   $tMessage = $PALANG['pVacation_result_error'];
}

$tUseremail = $SESSID_USERNAME;
include ("$incpath/templates/header.tpl");
if (authentication_has_role('global-admin')) {
   include ("$incpath/templates/admin_menu.tpl");
} else {
   include ("$incpath/templates/menu.tpl");
}
include ("$incpath/templates/edit-vacation.tpl");
include ("$incpath/templates/footer.tpl");
/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
