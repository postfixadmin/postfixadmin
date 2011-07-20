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
 * File: edit-mailbox.php 
 * Used to update an existing mailboxes settings.
 * Template File: edit-mailbox.tpl
 *
 * Template Variables:
 *
 * tName
 * tQuota
 *
 * Form POST \ GET Variables:
 *
 * fUsername
 * fDomain
 * fPassword
 * fPassword2
 * fName
 * fQuota
 * fActive
 */

require_once('common.php');

authentication_require_role('admin');
$SESSID_USERNAME = authentication_get_username();

$fUsername = 'x';
$fDomain = 'y';
$error = 0;

if (isset ($_GET['username'])) $fUsername = escape_string ($_GET['username']);
$fUsername = strtolower ($fUsername);
if (isset ($_GET['domain'])) $fDomain = escape_string ($_GET['domain']);

$pEdit_mailbox_name_text = $PALANG['pEdit_mailbox_name_text'];
$pEdit_mailbox_quota_text = $PALANG['pEdit_mailbox_quota_text'];
$pEdit_mailbox_quota_text_error = "";

if (!(check_owner ($SESSID_USERNAME, $fDomain) || authentication_has_role('global-admin')) )
{
   $error = 1;
   $tName = $fName;
   $tQuota = $fQuota;
   $tActive = $fActive;
   flash_error($PALANG['pEdit_mailbox_domain_error'] . "$fDomain");
}

$result = db_query("SELECT * FROM $table_mailbox WHERE username = '$fUsername' AND domain = '$fDomain'");
if($result['rows'] != 1) {
   die("Invalid username chosen; user does not exist in mailbox table");
}
$user_details = db_array($result['result']);

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   if (check_owner($SESSID_USERNAME, $fDomain) || authentication_has_role('global-admin'))
   {
      $tName = $user_details['name'];
      $tQuota = divide_quota($user_details['quota']);
      $tActive = $user_details['active'];
      if ('pgsql'==$CONF['database_type']) {
         $tActive = ('t'==$user_details['active']) ? 1 : 0;
      }

      $result = db_query ("SELECT * FROM $table_domain WHERE domain='$fDomain'");
      if ($result['rows'] == 1)
      {
         $row = db_array ($result['result']);
         $tMaxquota = $row['maxquota'];
      }
   }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel'])) {
    header("Location: list-virtual.php?domain=$fDomain");
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   if (isset ($_POST['fPassword'])) $fPassword = escape_string ($_POST['fPassword']);
   if (isset ($_POST['fPassword2'])) $fPassword2 = escape_string ($_POST['fPassword2']);
   if (isset ($_POST['fName'])) $fName = escape_string ($_POST['fName']);
   if (isset ($_POST['fQuota'])) $fQuota = intval ($_POST['fQuota']);
   if (isset ($_POST['fActive'])) $fActive = escape_string ($_POST['fActive']);

   if($fPassword != $user_details['password'] || $fPassword2 != $user_details['password']){
      $min_length = $CONF['min_password_length'];

      if($fPassword == $fPassword2) {
         if ($fPassword != "") {
            if($min_length > 0 && strlen($fPassword) < $min_length) {
               flash_error(sprintf($PALANG['pPasswordTooShort'], $CONF['min_password_length']));
               $error = 1;
            }
            $formvars['password'] = pacrypt($fPassword);
         }
      }
      else {
         flash_error($PALANG['pEdit_mailbox_password_text_error']);
         $error = 1;
      }
   }
   if ($CONF['quota'] == "YES")
   {
      if (!check_quota ($fQuota, $fDomain, $fUsername))
      {
         $error = 1;
         $tName = $fName;
         $tQuota = $fQuota;
         $tActive = $fActive;
         $pEdit_mailbox_quota_text_error = $PALANG['pEdit_mailbox_quota_text_error'];
      }
   }
   if ($error != 1)
   {
      if (!empty ($fQuota))
      {
         $quota = multiply_quota ($fQuota);
      }
      else
      {
         $quota = 0;
      }

      if ($fActive == "on")
      {
         $sqlActive = db_get_boolean(True);
         $fActive = 1;
      }
      else
      {
         $sqlActive = db_get_boolean(False);
         $fActive = 0;
      }

      $formvars['name'] = $fName;
      $formvars['quota'] =$quota;
      $formvars['active']=$sqlActive;
      if(preg_match('/^(.*)@/', $fUsername, $matches)) {
         $formvars['local_part'] = $matches[1];
      }
      $result = db_update_q('mailbox', "username='$fUsername' AND domain='$fDomain'", $formvars); # TODO: check if we need the AND domain=... clause, if not, switch to db_update()
      $maildir = $user_details['maildir'];
      if ($result != 1 || !mailbox_postedit($fUsername,$fDomain,$maildir, $quota)) {
         flash_error($PALANG['pEdit_mailbox_result_error']);
      }
      else {
         db_log ($fDomain, 'edit_mailbox', $fUsername);

         header ("Location: list-virtual.php?domain=$fDomain");
         exit(0);
      }
   } 
   else 
   {
      # error detected. Put the values the user entered in the form again.
      $tName = $fName;
      $tQuota = $fQuota;
      $tActive = $fActive;
   }
}

$smarty->assign ('fUsername', $fUsername);
$smarty->assign ('pEdit_mailbox_password_text', $pEdit_mailbox_password_text, false);
$smarty->assign ('tName', $tName, false);
$smarty->assign ('pEdit_mailbox_name_text', $pEdit_mailbox_name_text,false);
$smarty->assign ('tMaxquota', $tMaxquota);
$smarty->assign ('tQuota', $tQuota);
$smarty->assign ('pEdit_mailbox_quota_text', $pEdit_mailbox_quota_text);
$smarty->assign ('pEdit_mailbox_quota_text_error', $pEdit_mailbox_quota_text_error);
if ($tActive)	$smarty->assign ('tActive', ' checked="checked"');
$smarty->assign ('smarty_template', 'edit-mailbox');
$smarty->display ('index.tpl');
/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
