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
 * File: sendmail.php
 * Used to send an email to a user.
 * Template File: sendmail.tpl
 *
 * Template Variables:
 *
 * tMessage
 * tFrom
 * tSubject
 * tBody
 *
 * Form POST \ GET Variables:
 *
 * fTo
 * fSubject
 * fBody
 */

require_once('common.php');

authentication_require_role('admin');

(($CONF['sendmail'] == 'NO') ? header("Location: " . $CONF['postfix_admin_url'] . "/main.php") && exit : '1');

$SESSID_USERNAME = authentication_get_username();
if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/sendmail.tpl");
   include ("./templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   if (isset ($_POST['fTo'])) $fTo = escape_string ($_POST['fTo']);
   $fFrom = $SESSID_USERNAME;
   if (isset ($_POST['fTo'])) $fHeaders = "To: " . $fTo . "\n";
   if (isset ($_POST['fTo'])) $fHeaders .= "From: " . $fFrom . "\n";

   if (!empty ($PALANG['charset']))
   {
      $fHeaders .= "Subject: " . encode_header (escape_string ($_POST['fSubject']), $PALANG['charset']) . "\n";
      $fHeaders .= "MIME-Version: 1.0\n";
      $fHeaders .= "Content-Type: text/plain; charset=" . $PALANG['charset'] . "\n";
      $fHeaders .= "Content-Transfer-Encoding: 8bit\n";
   }
   else
   {
      $fHeaders .= "Subject: " . escape_string ($_POST['fSubject']) . "\n\n";
   }

   $fHeaders .= escape_string ($_POST['fBody']);

   if (empty ($fTo) or !check_email ($fTo))
	{
      $error = 1;
      $tTo = escape_string ($_POST['fTo']);
      $tSubject = escape_string ($_POST['fSubject']);
      $tBody = escape_string ($_POST['fBody']);
      $tMessage = $PALANG['pSendmail_to_text_error'];
	}

   if ($error != 1)
   {
      if (!smtp_mail ($fTo, $fFrom, $fHeaders))
      {
         $tMessage .= $PALANG['pSendmail_result_error'];
      }
      else
      {
         $tMessage .= $PALANG['pSendmail_result_success'];
      }
   }

   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/sendmail.tpl");
   include ("./templates/footer.tpl");
}
?>
