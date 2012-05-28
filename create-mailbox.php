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
 * File: create-mailbox.php
 * Responsible for allowing for the creation of mail boxes
 *
 * @version $Id$
 * @license GNU GPL v2 or later.
 *
 * Template Variables:
 *
 * tUsername
 * tName
 * tQuota
 * tDomain
 *
 * Form POST \ GET Variables:
 *
 * fUsername
 * fPassword
 * fPassword2
 * fName
 * fQuota
 * fDomain
 * fActive
 * fMail
 */

require_once('common.php');

authentication_require_role('admin');
$SESSID_USERNAME = authentication_get_username();
if(authentication_has_role('global-admin')) {
   $list_domains = list_domains ();
}
else {
   $list_domains = list_domains_for_admin($SESSID_USERNAME);
}


$pCreate_mailbox_username_text_error = "";
$pCreate_mailbox_password_text_error = "";
$pCreate_mailbox_quota_text_error = "";

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   $fDomain = $list_domains[0];
   if (isset ($_GET['domain'])) $fDomain = escape_string ($_GET['domain']);

   if(!in_array($fDomain, $list_domains)) {
      die("Invalid domain name selected, or you tried to select a domain you are not an admin for");
   }
   $tDomain = $fDomain;
   $result = db_query ("SELECT * FROM $table_domain WHERE domain='$fDomain'");
   if ($result['rows'] == 1)
   {
      $row = db_array ($result['result']);
      $tQuota = allowed_quota($tDomain, 0);
      # TODO: check for remaining domain quota, reduce $tQuota if it is lower
      # Note: this is dependent on the domain, which means to do it correct we'd have to remove the domain dropdown and hardcode the domain name from ?domain=...
      # allowed_quota() will provide the maximum allowed quota 
   }
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{

   if (isset ($_POST['fUsername']) && isset ($_POST['fDomain'])) $fUsername = escape_string ($_POST['fUsername']) . "@" . escape_string ($_POST['fDomain']);
   $fUsername = strtolower ($fUsername);
   if (isset ($_POST['fPassword'])) $fPassword = escape_string ($_POST['fPassword']); # TODO: remove escaping (except for DB query and when handing it over to dovecotpw) - https://sourceforge.net/tracker/?func=detail&aid=3094804&group_id=191583&atid=937964
   if (isset ($_POST['fPassword2'])) $fPassword2 = escape_string ($_POST['fPassword2']);
   isset ($_POST['fName']) ? $fName = escape_string ($_POST['fName']) : $fName = "";
   if (isset ($_POST['fDomain'])) $fDomain = escape_string ($_POST['fDomain']);
   isset ($_POST['fQuota']) ? $fQuota = intval($_POST['fQuota']) : $fQuota = 0;
   isset ($_POST['fActive']) ? $fActive = escape_string ($_POST['fActive']) : $fActive = "1";
   if (isset ($_POST['fMail'])) $fMail = escape_string ($_POST['fMail']);


   if ( (!check_owner ($SESSID_USERNAME, $fDomain)) && (!authentication_has_role('global-admin')) )
   {
      $error = 1;
      $pCreate_mailbox_username_text_error = $PALANG['pCreate_mailbox_username_text_error1'];
   }

   if (!check_mailbox ($fDomain))
   {
      $error = 1;
      $pCreate_mailbox_username_text_error = $PALANG['pCreate_mailbox_username_text_error3'];
   }

   if (empty ($fUsername) or !check_email ($fUsername))
   {
      $error = 1;
      $pCreate_mailbox_username_text_error = $PALANG['pCreate_mailbox_username_text_error1'];
   }

   $tPassGenerated = 0;
   if (empty ($fPassword) && empty ($fPassword2) && $CONF['generate_password'] == "YES") {
      $fPassword = generate_password ();
      $tPassGenerated = 1;
   } elseif (empty ($fPassword) || empty ($fPassword2) || ($fPassword != $fPassword2)) {
         $error = 1;
         $pCreate_mailbox_password_text_error = $PALANG['pCreate_mailbox_password_text_error'];
   } else {
      $validpass = validate_password($fPassword);
      if(count($validpass) > 0) {
         $pCreate_mailbox_password_text_error = $validpass[0]; # TODO: honor all error messages, not only the first one
         $error = 1;
      }
   }

   if ($CONF['quota'] == "YES")
   {
      if (!check_quota ($fQuota, $fDomain))
      {
         $error = 1;
         $pCreate_mailbox_quota_text_error = $PALANG['pCreate_mailbox_quota_text_error'];
      }
   }

   $result = db_query ("SELECT * FROM $table_alias WHERE address='$fUsername'");
   if ($result['rows'] == 1)
   {
      $error = 1;
      $pCreate_mailbox_username_text_error = $PALANG['pCreate_mailbox_username_text_error2'];
   }

   if ($error != 0) {
      $tUsername = escape_string ($_POST['fUsername']);
      $tName = $fName;
      $tQuota = $fQuota;
      $tDomain = $fDomain;
   } else {
      $password = escape_string(pacrypt ($fPassword));

      if($CONF['maildir_name_hook'] != 'NO' && function_exists($CONF['maildir_name_hook'])) {
         $hook_func = $CONF['maildir_name_hook'];
         $maildir = $hook_func ($fDomain, $fUsername);
      }
      else if ($CONF['domain_path'] == "YES")
      {
         if ($CONF['domain_in_mailbox'] == "YES")
         {
            $maildir = $fDomain . "/" . $fUsername . "/";
         }
         else
         {
            $maildir = $fDomain . "/" . escape_string (strtolower($_POST['fUsername'])) . "/";
         }
      }
      else
      {
         $maildir = $fUsername . "/";
      }

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
      }
      else
      {
         $sqlActive = db_get_boolean(False);
      }
      if ('pgsql'==$CONF['database_type'])
      {
         db_query('BEGIN');
      }

      $result = db_query ("INSERT INTO $table_alias (address,goto,domain,created,modified,active) VALUES ('$fUsername','$fUsername','$fDomain',NOW(),NOW(),'$sqlActive')");
      if ($result['rows'] != 1)
      {
         $tDomain = $fDomain;
         flash_error($PALANG['pAlias_result_error'] . "<br />($fUsername -> $fUsername)");
      }

      // apparently uppercase usernames really confuse some IMAP clients.
      $fUsername = strtolower($fUsername);
      $local_part = '';
      if(preg_match('/^(.*)@/', $fUsername, $matches)) {
         $local_part = $matches[1];
      }

      $result = db_query ("INSERT INTO $table_mailbox (username,password,name,maildir,local_part,quota,domain,created,modified,active) VALUES ('$fUsername','$password','$fName','$maildir','$local_part','$quota','$fDomain',NOW(),NOW(),'$sqlActive')");
      if ($result['rows'] != 1 || !mailbox_postcreation($fUsername,$fDomain,$maildir, $quota))
      {
         $tDomain = $fDomain;
         flash_error($PALANG['pCreate_mailbox_result_error'] . "<br />($fUsername)");
         db_query('ROLLBACK');
      }
      else
      {
         db_query('COMMIT');
         db_log ($fDomain, 'create_mailbox', "$fUsername");
         $tDomain = $fDomain;

         $tQuota = allowed_quota($tDomain, 0);

         if ($fMail == "on")
         {
            $fTo = $fUsername;
            $fFrom = smtp_get_admin_email();
            $fSubject = $PALANG['pSendmail_subject_text'];
            $fBody = $CONF['welcome_text'];

            if (!smtp_mail ($fTo, $fFrom, $fSubject, $fBody))
            {
               flash_error($PALANG['pSendmail_result_error']);
            }
            else
            {
               flash_info($PALANG['pSendmail_result_success']);
            }
         }

         $tShowpass = "";
         if ( $tPassGenerated == 1 || $CONF['show_password'] == "YES") $tShowpass = " / $fPassword";

         if (create_mailbox_subfolders($fUsername,$fPassword))
         {
            flash_info($PALANG['pCreate_mailbox_result_success'] . "<br />($fUsername$tShowpass)");
         } else {
            flash_info($PALANG['pCreate_mailbox_result_succes_nosubfolders'] . "<br />($fUsername$tShowpass)");
         }

      }
   }
}

$smarty->assign ('mode', 'create');
$smarty->assign ('tUsername', $tUsername);
$smarty->assign ('tActive', ' checked="checked" '); # TODO: use form value if POST
$smarty->assign ('select_options', select_options ($list_domains, array ($tDomain)), false);
$smarty->assign ('pCreate_mailbox_username_text_error', $pCreate_mailbox_username_text_error, false);
$smarty->assign ('mailbox_password_text_error', $pCreate_mailbox_password_text_error, false);
$smarty->assign ('tName', $tName, false);
$smarty->assign ('tQuota', $tQuota);
$smarty->assign ('mailbox_quota_text_error', $pCreate_mailbox_quota_text_error, false);
$smarty->assign ('smarty_template', 'edit-mailbox');
$smarty->display ('index.tpl');


/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
