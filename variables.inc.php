<?php
//
// Postfix Admin
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// Licensed under GPL for more info check GPL-LICENSE.TXT
//
// File: variables.inc.php
//
if (ereg ("variables.inc.php", $_SERVER['PHP_SELF']))
{
   header ("Location: login.php");
   exit;
}

$error = "";
$escaped_string = "";
$quota = "";
$vacation = "";
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
$fFrom = "";
$fGoto = "";
$fHeaders = "";
$fMail = "";
$fMailboxes = "";
$fMaxquota = "";
$fName = "";
$fPassword = "";
$fPassword2 = "";
$fQuota = "";
$fSearch = "";
$fTable = "";
$fTransport = "";
$fTo = "";
$fUsername = "";
$fVacation = "";
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
$tMessage = "&nbsp;";
$tName = "";
$tQuota = "";
$tSubject = "";
$tUsername = "";
$tTransport = "";

$pAdminCreate_admin_password_text = "&nbsp;";
$pAdminCreate_admin_username_text = "&nbsp;";
$pAdminCreate_domain_defaultaliases_text = "&nbsp;";
$pAdminCreate_domain_domain_text = "&nbsp;";
$pAdminEdit_admin_password_text = "&nbsp;";
$pCreate_alias_address_text = "&nbsp;";
$pCreate_alias_goto_text = "&nbsp;";
$pCreate_mailbox_name_text = "&nbsp;";
$pCreate_mailbox_password_text = "&nbsp;";
$pCreate_mailbox_quota_text = "&nbsp;";
$pCreate_mailbox_username_text = "&nbsp;";
$pEdit_mailbox_password_text = "&nbsp;";
$pEdit_mailbox_quota_text = "&nbsp;";
$pEdit_mailbox_username_text = "&nbsp;";
$pPassword_admin_text = "&nbsp;";
$pPassword_password_current_text = "&nbsp;";
$pPassword_password_text = "&nbsp;";
?>
