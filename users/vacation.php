<?php
// 
// Postfix Admin 
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// Licensed under GPL for more info check GPL-LICENSE.TXT
//
// File: vacation.php
//
// Template File: users_vacation.tpl
//
// Template Variables:
//
// tMessage
// tSubject
// tBody
//
// Form POST \ GET Variables:
//
// fSubject
// fBody
// fAway
// fBack
//
require ("../variables.inc.php");
require ("../config.inc.php");
require ("../functions.inc.php");
include ("../languages/" . check_language () . ".lang");

$USERID_USERNAME = check_user_session ();
(($CONF['vacation'] == 'NO') ? header("Location: " . $CONF['postfix_admin_url'] . "/users/main.php") && exit : '1');
$tmp = preg_split ('/@/', $USERID_USERNAME);     
$USERID_DOMAIN = $tmp[1];

if ($_SERVER['REQUEST_METHOD'] == "GET")
{

   $result = db_query("SELECT * FROM $table_vacation WHERE email='$USERID_USERNAME'");
   if ($result['rows'] == 1)
   {
      $row = db_array($result['result']);
      $tMessage = $PALANG['pUsersVacation_welcome_text'];
      $tSubject = $row['subject'];
      $tBody = $row['body'];

   }
   
   if ($tSubject == '') { $tSubject = $PALANG['pUsersVacation_subject_text']; }
   if ($tBody == '') { $tBody = $PALANG['pUsersVacation_body_text']; }

   $template = "users_vacation.tpl";

   include ("../templates/header.tpl");
   include ("../templates/users_menu.tpl");
   include ("../templates/users_vacation.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $vacation_domain = $CONF['vacation_domain'];
   
   if (isset ($_POST['fSubject'])) $fSubject = escape_string ($_POST['fSubject']);
   if (isset ($_POST['fBody'])) $fBody = escape_string ($_POST['fBody']);
   if (isset ($_POST['fAway'])) $fAway = escape_string ($_POST['fAway']);
   if (isset ($_POST['fBack'])) $fBack = escape_string ($_POST['fBack']);

   //set a default, reset fields for coming back selection
   if ($tSubject == '') { $tSubject = $PALANG['pUsersVacation_subject_text']; }
   if ($tBody == '') { $tBody = $PALANG['pUsersVacation_body_text']; }

   if (!empty ($fBack) || !empty ($fAway))
   {
      $result = db_query ("DELETE FROM $table_vacation WHERE email='$USERID_USERNAME'");
      if ($result['rows'] != 1)
      {
         $error = 1;
         $tMessage = $PALANG['pUsersVacation_result_error'];
      }
      else
      {
         $tMessage = $PALANG['pUsersVacation_result_succes'];
      }

      $result = db_query ("SELECT * FROM $table_alias WHERE address='$USERID_USERNAME'");
      if ($result['rows'] == 1)
      {
         $row = db_array ($result['result']);
         $tGoto = $row['goto'];

         //only one of these will do something, first handles address at beginning and middle, second at end
         $goto= preg_replace ( "/$fUsername@$vacation_domain,/", '', $tGoto);
         $goto= preg_replace ( "/,$fUsername@$vacation_domain/", '', $goto);

      }

      $result = db_query ("UPDATE $table_alias SET goto='$goto',modified=NOW() WHERE address='$USERID_USERNAME'");
      if ($result['rows'] != 1)
      {
         $error = 1;
         $tMessage = $PALANG['pUsersVacation_result_error'];
      }
      else
      {
         $tMessage = $PALANG['pUsersVacation_result_succes'];
      }

   }

   if (!empty ($fAway))
   {
      $result = db_query ("SELECT * FROM $table_alias WHERE address='$USERID_USERNAME'");
      if ($result['rows'] == 1)
      {
         $row = db_array ($result['result']);
         $tGoto = $row['goto'];
      }

      ($CONF['database_type']=='pgsql') ? $Active='true' : $Active=1;
      $result = db_query ("INSERT INTO $table_vacation (email,subject,body,domain,created,active) VALUES ('$USERID_USERNAME','$fSubject','$fBody','$USERID_DOMAIN',NOW(),$Active)");
      if ($result['rows'] != 1)
      {
         $error = 1;
         $tMessage = $PALANG['pUsersVacation_result_error'];
      }

      $goto = $tGoto . "," . "$USERID_USERNAME@$vacation_domain";
      
      $result = db_query ("UPDATE $table_alias SET goto='$goto',modified=NOW() WHERE address='$USERID_USERNAME'");
      if ($result['rows'] != 1)
      {
         $error = 1;
         $tMessage = $PALANG['pUsersVacation_result_error'];
      }
      else
      {
         header ("Location: main.php");
         exit;
      }
   }
   
   include ("../templates/header.tpl");
   include ("../templates/users_menu.tpl");
   include ("../templates/users_vacation.tpl");
   include ("../templates/footer.tpl");
}
?>
