<?php
//
// File: password.php
//
// Template File: users_password.tpl
//
// Template Variables:
//
// tMessage
//
// Form POST \ GET Variables:
//
// fPassword_current
// fPassword
// fPassword2
//
require ("../config.inc.php");
require ("../functions.inc.php");
include ("../languages/" . $CONF['language'] . ".lang");

$USERID_USERNAME = check_user_session ();
$tmp = preg_split ('/@/', $USERID_USERNAME);     
$USERID_DOMAIN = $tmp[1];

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   include ("../templates/header.tpl");
   include ("../templates/users_menu.tpl");
   include ("../templates/users_password.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $pPassword_password_text = $LANG['pPassword_password_text'];
   
   $fPassword_current = $_POST['fPassword_current'];
   $fPassword = $_POST['fPassword'];
   $fPassword2 = $_POST['fPassword2'];

   $username = $USERID_USERNAME;
     
  	$result = db_query ("SELECT * FROM mailbox WHERE username='$username'");
   if ($result['rows'] == 1)
   {
      $row = mysql_fetch_array ($result['result']);
      $salt = preg_split ('/\$/', $row['password']);
      $checked_password = pacrypt ($fPassword_current, $salt[2]);

		$result = db_query ("SELECT * FROM mailbox WHERE username='$username' AND password='$checked_password'");      
      if ($result['rows'] != 1)
      {
         $error = 1;
         $pPassword_password_current_text = $LANG['pPassword_password_current_text_error'];
      }
   }
   else
   {
      $error = 1;
      $pPassword_email_text = $LANG['pPassword_email_text_error']; 
   }

	if (empty ($fPassword) or ($fPassword != $fPassword2))
	{
	   $error = 1;
      $pPassword_password_text = $LANG['pPassword_password_text_error'];
	}

   if ($error != 1)
   {
      $password = pacrypt ($fPassword);
      $result = db_query ("UPDATE mailbox SET password='$password',modified=NOW() WHERE username='$username'");
      if ($result['rows'] == 1)
      {
         $tMessage = $LANG['pPassword_result_succes'];
         db_log ($USERID_USERNAME, $USERID_DOMAIN, "change password", "$USERID_USERNAME");
      }
      else
      {
         $tMessage = $LANG['pPassword_result_error'];
      }
   }
   
   include ("../templates/header.tpl");
   include ("../templates/users_menu.tpl");
   include ("../templates/users_password.tpl");
   include ("../templates/footer.tpl");
}
?>
