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
require ("./config.inc.php");
require ("./functions.inc.php");
include ("./languages/" . $CONF['language'] . ".lang");
 
if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   include ("./templates/header.tpl");
   include ("./templates/login.tpl");
   include ("./templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $fUsername = $_POST['fUsername'];
   $fPassword = $_POST['fPassword'];

   $result = db_query ("SELECT password FROM admin WHERE username='$fUsername'");
   if ($result['rows'] == 1)
   {
      $row = mysql_fetch_array ($result['result']);
      $salt = preg_split ('/\$/', $row['password']);
      $password = md5crypt ($fPassword, $salt[2]);

      $result = db_query ("SELECT * FROM admin WHERE username='$fUsername' AND password='$password'");
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
      session_register("sessid");
      $_SESSION['sessid']['username'] = $fUsername;

      header("Location: main.php");
      exit;
   }
   
   include ("./templates/header.tpl");
   include ("./templates/login.tpl");
   include ("./templates/footer.tpl");
} 
?>
