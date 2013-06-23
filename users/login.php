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
 *  tUsername
 *
 * Form POST \ GET Variables:  
 *
 *  fUsername
 *  fPassword
 *  lang
 */

$rel_path = '../';
require_once("../common.php");


if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $lang = safepost('lang');

   if ( $lang != check_language(0) ) { # only set cookie if language selection was changed
      setcookie('lang', $lang, time() + 60*60*24*30); # language cookie, lifetime 30 days
      # (language preference cookie is processed even if username and/or password are invalid)
   }

   $fUsername = escape_string ($_POST['fUsername']);
   $fPassword = escape_string ($_POST['fPassword']);

   $h = new MailboxHandler();
   if($h->login($_POST['fUsername'], $_POST['fPassword'])) {
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
         flash_error($PALANG['pLogin_failed']);;
   }
}

$smarty->assign ('language_selector', language_selector(), false);
$smarty->assign ('smarty_template', 'login');
$smarty->assign ('logintype', 'user');
$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
