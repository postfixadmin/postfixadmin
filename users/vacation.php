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
      $template = "users_vacation-get.tpl";
   }
   else
   {
      $template = "users_vacation.tpl";
   }
   
   include ("../templates/header.tpl");
   include ("../templates/users_menu.tpl");
   include ("../templates/$template");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $vacation_domain = $CONF['vacation_domain'];
   
   if (isset ($_POST['fSubject'])) $fSubject = escape_string ($_POST['fSubject']);
   if (isset ($_POST['fBody'])) $fBody = escape_string ($_POST['fBody']);
   if (isset ($_POST['fAway'])) $fAway = escape_string ($_POST['fAway']);
   if (isset ($_POST['fBack'])) $fBack = escape_string ($_POST['fBack']);

   if (!empty ($fBack))
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

         $array = preg_split ('/,/', $tGoto);
         {
            array_pop ($array);
            $goto = implode (",", $array);
         }
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
