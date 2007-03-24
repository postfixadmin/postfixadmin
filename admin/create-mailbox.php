<?php
//
// File: create-mailbox.php
//
// Template File: create-mailbox.tpl
//
// Template Variables:
//
// tMessage
// tUsername
// tName
// tQuota
// tDomain
//
// Form POST \ GET Variables:
//
// fUsername
// fPassword
// fPassword2
// fName
// fQuota
// fDomain
// fActive
// fMail
//
require ("../variables.inc.php");
require ("../config.inc.php");
require ("../functions.inc.php");
include ("../languages/" . check_language () . ".lang");

$list_domains = list_domains ();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   $tQuota = $CONF['maxquota'];

   $pCreate_mailbox_password_text = $PALANG['pCreate_mailbox_password_text'];
   $pCreate_mailbox_name_text = $PALANG['pCreate_mailbox_name_text'];
   $pCreate_mailbox_quota_text = $PALANG['pCreate_mailbox_quota_text'];

   if (isset ($_GET['domain'])) { $tDomain = $_GET['domain']; }

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/create-mailbox.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $pCreate_mailbox_password_text = $PALANG['pCreate_mailbox_password_text'];
   $pCreate_mailbox_name_text = $PALANG['pCreate_mailbox_name_text'];
   $pCreate_mailbox_quota_text = $PALANG['pCreate_mailbox_quota_text'];
  
   $fUsername = $_POST['fUsername'] . "@" . $_POST['fDomain'];
   $fUsername = strtolower ($fUsername);
   $fPassword = $_POST['fPassword'];
   $fPassword2 = $_POST['fPassword2'];
   $fName = $_POST['fName'];
   $fDomain = $_POST['fDomain'];
   if (isset ( $_POST['fQuota'])) $fQuota = $_POST['fQuota'];
   if (isset ( $_POST['fActive'])) $fActive = $_POST['fActive'];
   if (isset ( $_POST['fMail'])) $fMail = $_POST['fMail'];

   if (!check_mailbox ($fDomain))
   {
      $error = 1;
      $tUsername = $_POST['fUsername'];
      $tName = $fName;
      $tQuota = $fQuota;
      $tDomain = $fDomain;
      $pCreate_mailbox_username_text = $PALANG['pCreate_mailbox_username_text_error3'];
   }
    
	if (empty ($fUsername) or !check_email ($fUsername))
	{
      $error = 1;
      $tUsername = $_POST['fUsername'];
      $tName = $fName;
      $tQuota = $fQuota;
      $tDomain = $fDomain;
      $pCreate_mailbox_username_text = $PALANG['pCreate_mailbox_username_text_error1'];
   }

	if (empty ($fPassword) or ($fPassword != $fPassword2))
	{
	   if ($CONF['generate_password'] == "YES")
	   {
	      $fPassword = generate_password ();
	   }
	   else
	   {
         $error = 1;
         $tUsername = $_POST['fUsername'];
         $tName = $fName;
         $tQuota = $fQuota;
         $tDomain = $fDomain;
         $pCreate_mailbox_password_text = $PALANG['pCreate_mailbox_password_text_error'];
      }
	}

   if (!check_quota ($fQuota, $fDomain))
   {
      $error = 1;
      $tUsername = $_POST['fUsername'];
      $tName = $fName;
      $tQuota = $fQuota;
      $tDomain = $fDomain;
      $pCreate_mailbox_quota_text = $PALANG['pCreate_mailbox_quota_text_error'];
	}
	
   $result = db_query ("SELECT * FROM alias WHERE address='$fUsername'");
   if ($result['rows'] == 1)
   {
      $error = 1;
      $tUsername = $_POST['fUsername'];
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
            $maildir = $fDomain . "/" . $_POST['fUsername'] . "/";
         }
      }
      else
      {
         $maildir = $fUsername . "/";
      }
      
      if (!empty ($fQuota)) $quota = $fQuota * $CONF['quota_multiplier'];
      if ($fActive == "on") $fActive = 1;

      $result = db_query ("INSERT INTO alias (address,goto,domain,created,modified,active) VALUES ('$fUsername','$fUsername','$fDomain',NOW(),NOW(),'$fActive')");
      if ($result['rows'] != 1)
      {
         $tDomain = $fDomain;
         $tMessage = $PALANG['pAlias_result_error'] . "<br />($fUsername -> $fUsername)</br />";
      }

      $result = db_query ("INSERT INTO mailbox (username,password,name,maildir,quota,domain,created,modified,active) VALUES ('$fUsername','$password','$fName','$maildir','$quota','$fDomain',NOW(),NOW(),'$fActive')");
      if ($result['rows'] != 1)
      {
         $tDomain = $fDomain;
         $tMessage .= $PALANG['pCreate_mailbox_result_error'] . "<br />($fUsername)<br />";
      }
      else
      {
      
         db_log ($CONF['admin_email'], $fDomain, "create mailbox", $fUsername);

         $tDomain = $fDomain;
         $tMessage = $PALANG['pCreate_mailbox_result_succes'] . "<br />($fUsername";
         if ($CONF['generate_password'] == "YES")
         {
            $tMessage .= " / $fPassword)</br />";
         }
         else
         {
            $tMessage .= ")</br />";
         }
   

         $tQuota = $CONF['maxquota'];

         if ($fMail == "on")
         {
            $fTo = $fUsername;
            $fFrom = $CONF['admin_email'];
            $fHeaders = "To: " . $fTo . "\n";
            $fHeaders .= "From: " . $fFrom . "\n";
   
            if (!empty ($PALANG['charset']))
            {
               $fHeaders .= encode_header ($PALANG['pSendmail_subject_text'], $PALANG['charset']) . "\n";
               $fHeaders .= "MIME-Version: 1.0\n";
               $fHeaders .= "Content-Type: text/plain; charset=" . $PALANG['charset'] . "\n";
               $fHeaders .= "Content-Transfer-Encoding: 8bit\n";
            }
            else
            {
               $fHeaders .= "Subject: " . $PALANG['pSendmail_subject_text'] . "\n\n";
            }
   
            $fHeaders .= $PALANG['pSendmail_body_text'];

            if (!smtp_mail ($fTo, $fFrom, $fHeaders))
            {
               $tMessage .= "<br />" . $PALANG['pSendmail_result_error'] . "<br />";
            }
            else
            {
               $tMessage .= "<br />" . $PALANG['pSendmail_result_succes'] . "<br />";
            }
         }
      }
   }

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/create-mailbox.tpl");
   include ("../templates/footer.tpl");
}
?>
