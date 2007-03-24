<?php
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
include ("../languages/" . $CONF['language'] . ".lang");

$USERID_USERNAME = check_user_session ();
$tmp = preg_split ('/@/', $USERID_USERNAME);     
$USERID_DOMAIN = $tmp[1];

if ($_SERVER['REQUEST_METHOD'] == "GET")
{

   $result = db_query("SELECT * FROM vacation WHERE email='$USERID_USERNAME'");
   if ($result['rows'] == 1)
   {
      $row = mysql_fetch_array($result['result']);
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
   $fSubject = $_POST['fSubject'];
   $fBody = $_POST['fBody'];
   $fAway = $_POST['fAway'];
   $fBack = $_POST['fBack'];

   if (!empty ($fBack))
   {
      $result = db_query ("DELETE FROM vacation WHERE email='$USERID_USERNAME'");
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
      $result = db_query ("INSERT INTO vacation (email,subject,body,domain,created) VALUES ('$USERID_USERNAME','$fSubject','$fBody','$USERID_DOMAIN',NOW())");
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
