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
 * Template File: login.tpl
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
 *  lang
 */

require_once("../common.php");

$smarty->assign ('language_selector', language_selector(), false);

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
	$smarty->assign ('smarty_template', 'users_login');
	$smarty->display ('index.tpl');
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{

   $lang = safepost('lang');

   if ( $lang != check_language(0) ) { # only set cookie if language selection was changed
      setcookie('lang', $lang, time() + 60*60*24*30); # language cookie, lifetime 30 days
      # (language preference cookie is processed even if username and/or password are invalid)
   }

   $fUsername = escape_string ($_POST['fUsername']);
   $fPassword = escape_string ($_POST['fPassword']);

   if(UserHandler::login($_POST['fUsername'], $_POST['fPassword'])) {
      session_regenerate_id();
      $_SESSION['sessid'] = array();
      $_SESSION['sessid']['roles'] = array();
      $_SESSION['sessid']['roles'][] = 'user';
      $_SESSION['sessid']['username'] = $fUsername;
      header("Location: main.php");
      exit;
   }
   else {   
         $error = 1;
         $tMessage = '<span class="error_msg">' . $PALANG['pLogin_failed'] . '</span>';
         $tUsername = $fUsername;
   }
	$smarty->assign ('tUsername', $tUsername);
	$smarty->assign ('tMessage', $tMessage, false);
	$smarty->assign ('smarty_template', 'users_login');
	$smarty->display ('index.tpl');
}
/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
