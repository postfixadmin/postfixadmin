<?php
//
// File: edit-mailbox.php
//
// Template File: edit-mailbox.tpl
//
// Template Variables:
//
// tMessage
// tName
// tQuota
//
// Form POST \ GET Variables:
//
// fUsername
// fDomain
// fPassword
// fPassword2
// fName
// fQuota
// fActive
//
require ("./variables.inc.php");
require ("./config.inc.php");
require ("./functions.inc.php");
include ("./languages/" . check_language () . ".lang");

$SESSID_USERNAME = check_session ();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   $fUsername = $_GET['username'];
   $fDomain = $_GET['domain'];

   if (check_owner ($SESSID_USERNAME, $fDomain))
   {
      $result = db_query ("SELECT * FROM mailbox WHERE username='$fUsername' AND domain='$fDomain'");
      if ($result['rows'] == 1)
      {
         $row = db_array ($result['result']);
         $tName = $row['name'];
         $tQuota = $row['quota'] / $CONF['quota_multiplier'];
         $tActive = $row['active'];
      }
   }
   else
   {
      $tMessage = $PALANG['pEdit_mailbox_login_error'];
   }
   
   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/edit-mailbox.tpl");
   include ("./templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $pEdit_mailbox_password_text = $PALANG['pEdit_mailbox_password_text_error'];
   $pEdit_mailbox_quota_text = $PALANG['pEdit_mailbox_quota_text'];
   
   $fUsername = $_GET['username'];
   $fUsername = strtolower ($fUsername);
   $fDomain = $_GET['domain'];
   
   $fPassword = $_POST['fPassword'];
   $fPassword2 = $_POST['fPassword2'];
   $fName = $_POST['fName'];
   if (isset ($_POST['fQuota'])) $fQuota = $_POST['fQuota'];
   if (isset ($_POST['fActive'])) $fActive = $_POST['fActive'];
  
   if (!check_owner ($SESSID_USERNAME, $fDomain))
   {
      $error = 1;
      $tName = $fName;
      $tQuota = $fQuota;
      $tMessage = $PALANG['pEdit_mailbox_domain_error'] . "$fDomain</font>";
   }

	if ($fPassword != $fPassword2)
	{
	   $error = 1;
      $tName = $fName;
      $tQuota = $fQuota;
      $pEdit_mailbox_password_text = $PALANG['pEdit_mailbox_password_text_error'];
   }

   if (!check_quota ($fQuota, $fDomain))
   {
      $error = 1;
      $tName = $fName;
      $tQuota = $fQuota;
      $pEdit_mailbox_quota_text = $PALANG['pEdit_mailbox_quota_text_error'];
	}

   if ($error != 1)
   {
      if (!empty ($fQuota)) $quota = $fQuota * $CONF['quota_multiplier'];
      if ($fActive == "on") $fActive = 1;
      
      if (empty ($fPassword) and empty ($fPassword2))
      {
         $result = db_query ("UPDATE mailbox SET name='$fName',quota='$quota',modified=NOW(),active='$fActive' WHERE username='$fUsername' AND domain='$fDomain'");
      }
      else
      {
         $password = pacrypt ($fPassword);
         $result = db_query ("UPDATE mailbox SET password='$password',name='$fName',quota='$quota',modified=NOW(),active='$fActive' WHERE username='$fUsername' AND domain='$fDomain'");
      }

      if ($result['rows'] != 1)
      {
         $tMessage = $PALANG['pEdit_mailbox_result_error'];
      }
      else
      {
         db_log ($SESSID_USERNAME, $fDomain, "edit mailbox", $fUsername);
         
         header ("Location: overview.php?domain=$fDomain");
         exit;
      }
   }
   
   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/edit-mailbox.tpl");
   include ("./templates/footer.tpl");
}
?>
