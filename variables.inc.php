<?php
//
// File: variables.inc.php
//
if (ereg ("variables.inc.php", $_SERVER['PHP_SELF']))
{
   header ("Location: login.php");
   exit;
}

$error = "";
$quota = "";
$fActive = "";
$fAddress = "";
$fAliases = "";
$fBackupmx = "";
$fDefaultaliases = "";
$fDelete = "";
$fDescription = "";
$fDomain = "";
$fDomains = "";
$fDomains[0] = "";
$fGoto = "";
$fMail = "";
$fMailboxes = "";
$fMaxquota = "";
$fName = "";
$fPassword = "";
$fPassword2 = "";
$fQuota = "";
$fTable = "";
$fUsername = "";
$fWhere = "";
$tActive = "";
$tActive = "";
$tAddress = "";
$tAlias = "";
$tAliases = "";
$tBody = "";
$tDefaultaliases = "";
$tDescription = "";
$tDisplay_back = "";
$tDisplay_back_show = "";
$tDisplay_up_show = "";
$tDisplay_next = "";
$tDisplay_next_show = "";
$tDomain = "";
$tDomains = "";
$tFrom = "";
$tGoto = "";
$tLog = "";
$tMailbox = "";
$tMailboxes = "";
$tMaxquota = "";
$tMessage = "";
$tName = "";
$tQuota = "";
$tSubject = "";
$tUsername = "";

$pAdminCreate_admin_password_text = "";
$pAdminCreate_admin_username_text = "";
$pAdminCreate_domain_defaultaliases_text = "";
$pAdminCreate_domain_domain_text = "";
$pAdminEdit_admin_password_text = "";
$pCreate_alias_address_text = "";
$pCreate_alias_goto_text = "";
$pCreate_mailbox_name_text = "";
$pCreate_mailbox_password_text = "";
$pCreate_mailbox_quota_text = "";
$pCreate_mailbox_username_text = "";
$pEdit_mailbox_password_text = "";
$pEdit_mailbox_quota_text = "";
$pEdit_mailbox_username_text = "";
$pPassword_admin_text = "";
$pPassword_password_current_text = "";
$pPassword_password_text = "";
$pVcp_password_current_text = "";
$pVcp_password_text = "";
$pVcp_username_text = "";
?>
