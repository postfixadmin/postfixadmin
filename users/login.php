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
 * File: login.php
 * Used to authenticate want-to-be users.
 * Template File: login.php
 *
 * Template Variables:
 *
 *  tMessage
 *  tUsername
 *
 * Form POST \ GET Variables:  
 *
 *  fUsername
 *  fPassword
 */

require_once("../common.php");


if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   include ("../templates/header.php");
   include ("../templates/users_login.php");
   include ("../templates/footer.php");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $fUsername = escape_string ($_POST['fUsername']);
   $fPassword = escape_string ($_POST['fPassword']);

   $active = db_get_boolean(True);
   $query = "SELECT password FROM $table_mailbox WHERE username='$fUsername' AND active=$active";

   $result = db_query ($query);
   if ($result['rows'] == 1)
   {
      $row = db_array ($result['result']);
      $password = pacrypt ($fPassword, $row['password']);

      $query = "SELECT * FROM $table_mailbox WHERE username='$fUsername' AND password='$password' AND active=$active";

      $result = db_query ($query);
      if ($result['rows'] != 1)
      {
         $error = 1;
         $tMessage = $PALANG['pLogin_password_incorrect'];
         $tUsername = $fUsername;
      }
   }
   else
   {
      $error = 1;
      $tMessage = $PALANG['pLogin_username_incorrect'];
   }

   if ($error != 1)
   {
      session_regenerate_id();
      $_SESSION['sessid'] = array();
      $_SESSION['sessid']['roles'] = array();
      $_SESSION['sessid']['roles'][] = 'user';
      $_SESSION['sessid']['username'] = $fUsername;
      header("Location: main.php");
      exit;
   }

   include ("../templates/header.php");
   include ("../templates/users_login.php");
   include ("../templates/footer.php");
}
/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
