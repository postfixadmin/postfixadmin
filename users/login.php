<?php
//
// File: login.php
//
// Template File: login.tpl
//
// Template Variables:
//
//  tMessage
//  tUsername
//
// Form POST \ GET Variables:  
//
//  fUsername
//  fPassword
//
require ("../config.inc.php");
require ("../functions.inc.php");
include ("../languages/" . $CONF['language'] . ".lang");
 
if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   include ("../templates/header.tpl");
   include ("../templates/users_login.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $fUsername = $_POST['fUsername'];
   $fPassword = $_POST['fPassword'];

   $result = db_query ("SELECT password FROM mailbox WHERE username='$fUsername'");
   if ($result['rows'] == 1)
   {
      $row = mysql_fetch_array ($result['result']);
      $salt = preg_split ('/\$/', $row[password]);
      $password = pacrypt ($fPassword, $salt[2]);

      $result = db_query ("SELECT * FROM mailbox WHERE username='$fUsername' AND password='$password'");
      if ($result['rows'] != 1)
      {
         $error = 1;
         $tMessage = $LANG['pLogin_password_incorrect'];
         $tUsername = $fUsername;
      }
   }
   else
   {
      $error = 1;
      $tMessage = $LANG['pLogin_username_incorrect'];
   }

   if ($error != 1)
   {
      session_start();
      session_register("userid");
      $_SESSION['userid']['username'] = $fUsername;

      header("Location: main.php");
      exit;
   }
   
   include ("../templates/header.tpl");
   include ("../templates/users_login.tpl");
   include ("../templates/footer.tpl");
} 
?>
