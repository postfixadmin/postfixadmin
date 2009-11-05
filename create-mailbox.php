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
 * tMessage
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


$pCreate_mailbox_password_text = $PALANG['pCreate_mailbox_password_text'];
$pCreate_mailbox_quota_text = $PALANG['pCreate_mailbox_quota_text'];

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
      $tQuota = $row['maxquota'];
   }
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{

   if (isset ($_POST['fUsername']) && isset ($_POST['fDomain'])) $fUsername = escape_string ($_POST['fUsername']) . "@" . escape_string ($_POST['fDomain']);
   $fUsername = strtolower ($fUsername);
   if (isset ($_POST['fPassword'])) $fPassword = escape_string ($_POST['fPassword']);
   if (isset ($_POST['fPassword2'])) $fPassword2 = escape_string ($_POST['fPassword2']);
   isset ($_POST['fName']) ? $fName = escape_string ($_POST['fName']) : $fName = "";
   if (isset ($_POST['fDomain'])) $fDomain = escape_string ($_POST['fDomain']);
   isset ($_POST['fQuota']) ? $fQuota = intval($_POST['fQuota']) : $fQuota = 0;
   isset ($_POST['fActive']) ? $fActive = escape_string ($_POST['fActive']) : $fActive = "1";
   if (isset ($_POST['fMail'])) $fMail = escape_string ($_POST['fMail']);


   if ( (!check_owner ($SESSID_USERNAME, $fDomain)) && (!authentication_has_role('global-admin')) )
   {
      $error = 1;
      $tUsername = escape_string ($_POST['fUsername']);
      $tName = $fName;
      $tQuota = $fQuota;
      $tDomain = $fDomain;
      $pCreate_mailbox_username_text = $PALANG['pCreate_mailbox_username_text_error1'];
   }

   if (!check_mailbox ($fDomain))
   {
      $error = 1;
      $tUsername = escape_string ($_POST['fUsername']);
      $tName = $fName;
      $tQuota = $fQuota;
      $tDomain = $fDomain;
      $pCreate_mailbox_username_text = $PALANG['pCreate_mailbox_username_text_error3'];
   }

   if (empty ($fUsername) or !check_email ($fUsername))
   {
      $error = 1;
      $tUsername = escape_string ($_POST['fUsername']);
      $tName = $fName;
      $tQuota = $fQuota;
      $tDomain = $fDomain;
      $pCreate_mailbox_username_text = $PALANG['pCreate_mailbox_username_text_error1'];
   }

   $tPassGenerated = 0;
   if (empty ($fPassword) or empty ($fPassword2) or ($fPassword != $fPassword2))
   {
      if (empty ($fPassword) and empty ($fPassword2) and $CONF['generate_password'] == "YES")
      {
         $fPassword = generate_password ();
         $tPassGenerated = 1;
      }
      else
      {
         $error = 1;
         $tUsername = escape_string ($_POST['fUsername']);
         $tName = $fName;
         $tQuota = $fQuota;
         $tDomain = $fDomain;
         $pCreate_mailbox_password_text = $PALANG['pCreate_mailbox_password_text_error'];
      }
   }

   if ($CONF['quota'] == "YES")
   {
      if (!check_quota ($fQuota, $fDomain))
      {
         $error = 1;
         $tUsername = escape_string ($_POST['fUsername']);
         $tName = $fName;
         $tQuota = $fQuota;
         $tDomain = $fDomain;
         $pCreate_mailbox_quota_text = $PALANG['pCreate_mailbox_quota_text_error'];
      }
   }

   $result = db_query ("SELECT * FROM $table_alias WHERE address='$fUsername'");
   if ($result['rows'] == 1)
   {
      $error = 1;
      $tUsername = escape_string ($_POST['fUsername']);
      $tName = $fName;
      $tQuota = $fQuota;
      $tDomain = $fDomain;
      $pCreate_mailbox_username_text = $PALANG['pCreate_mailbox_username_text_error2'];
   }

   if ($error != 1)
   {
      $password = pacrypt ($fPassword);

      if ($CONF['domain_path'] == "YES")
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
         $tMessage = $PALANG['pAlias_result_error'] . "<br />($fUsername -> $fUsername)</br />";
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
         $tMessage .= $PALANG['pCreate_mailbox_result_error'] . "<br />($fUsername)<br />";
         db_query('ROLLBACK');
      }
      else
      {
         db_query('COMMIT');
         db_log ($SESSID_USERNAME, $fDomain, 'create_mailbox', "$fUsername");
      $tDomain = $fDomain;

      $tQuota = $CONF['maxquota'];

      if ($fMail == "on")
      {
         $fTo = $fUsername;
         $fFrom = $SESSID_USERNAME;
         $fHeaders = "To: " . $fTo . "\n";
         $fHeaders .= "From: " . $fFrom . "\n";

         $fHeaders .= "Subject: " . encode_header ($PALANG['pSendmail_subject_text']) . "\n";
         $fHeaders .= "MIME-Version: 1.0\n";
         $fHeaders .= "Content-Type: text/plain; charset=utf-8\n";
         $fHeaders .= "Content-Transfer-Encoding: 8bit\n";
         
         $fHeaders .= $CONF['welcome_text'];

         if (!smtp_mail ($fTo, $fFrom, $fHeaders))
         {
            $tMessage .= "<br />" . $PALANG['pSendmail_result_error'] . "<br />";
         }
         else
         {
            $tMessage .= "<br />" . $PALANG['pSendmail_result_success'] . "<br />";
         }
      }

      $tShowpass = "";
      if ( $tPassGenerated == 1 || $CONF['show_password'] == "YES") $tShowpass = " / $fPassword";

      if (create_mailbox_subfolders($fUsername,$fPassword))
      {
         $tMessage .= $PALANG['pCreate_mailbox_result_success'] . "<br />($fUsername$tShowpass)";
      } else {
         $tMessage .= $PALANG['pCreate_mailbox_result_succes_nosubfolders'] . "<br />($fUsername$tShowpass)";
      }

      }
   }
}

$smarty->assign ('tUsername', $tUsername);
$smarty->assign ('select_options', select_options ($list_domains, array ($tDomain)));
$smarty->assign ('pCreate_mailbox_username_text', $pCreate_mailbox_username_text);
$smarty->assign ('pCreate_mailbox_password_text', $pCreate_mailbox_password_text);
$smarty->assign ('tName', $tName);
$smarty->assign ('tQuota', $tQuota);
$smarty->assign ('pCreate_mailbox_quota_text', $pCreate_mailbox_quota_text);
$smarty->assign ('tMessage', $tMessage);
$smarty->assign ('smarty_template', 'create-mailbox');
$smarty->display ('index.tpl');


/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
