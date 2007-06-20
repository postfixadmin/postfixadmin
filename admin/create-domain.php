<?php
// 
// Postfix Admin 
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// Licensed under GPL for more info check GPL-LICENSE.TXT
//
// File: create-domain.php
//
// Template File: admin_create-domain.tpl
//
// Template Variables:
//
// tMessage
// tDomain
// tDescription
// tAliases
// tMailboxes
// tMaxquota
// tDefaultaliases
//
// Form POST \ GET Variables:
//
// fDomain
// fDescription
// fAliases
// fMailboxes
// fMaxquota
// fDefaultaliases
//
require ("../variables.inc.php");
require ("../config.inc.php");
require ("../functions.inc.php");
include ("../languages/" . check_language () . ".lang");

$SESSID_USERNAME = check_session ();
(!check_admin($SESSID_USERNAME) ? header("Location: " . $CONF['postfix_admin_url'] . "/main.php") && exit : '1');

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   $tAliases = $CONF['aliases'];
   $tMailboxes = $CONF['mailboxes'];
   $tMaxquota = $CONF['maxquota'];
   $tTransport = $CONF['transport_default'];

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/admin_create-domain.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   if (isset ($_POST['fDomain'])) $fDomain = escape_string ($_POST['fDomain']);
   !empty ($_POST['fDescription']) ? $fDescription = escape_string ($_POST['fDescription']) : $fDescription = "";
   if (isset ($_POST['fAliases'])) $fAliases = escape_string ($_POST['fAliases']);
   if (isset ($_POST['fMailboxes'])) $fMailboxes = escape_string ($_POST['fMailboxes']);
   !empty ($_POST['fMaxquota']) ? $fMaxquota = escape_string ($_POST['fMaxquota']) : $fMaxquota = "0";
   !empty ($_POST['fTransport']) ? $fTransport = escape_string ($_POST['fTransport']) : $fTransport = "virtual";
   if (isset ($_POST['fDefaultaliases'])) $fDefaultaliases = escape_string ($_POST['fDefaultaliases']);
   isset ($_POST['fBackupmx']) ? $fBackupmx = escape_string ($_POST['fBackupmx']) : $fBackupmx = "0";

   if (empty ($fDomain) or domain_exist ($fDomain) or !check_domain ($fDomain))
   {
      $error = 1;
      $tDomain = escape_string ($_POST['fDomain']);
      $tDescription = escape_string ($_POST['fDescription']);
      $tAliases = escape_string ($_POST['fAliases']);
      $tMailboxes = escape_string ($_POST['fMailboxes']);
      if (isset ($_POST['fMaxquota'])) $tMaxquota = escape_string ($_POST['fMaxquota']);
      if (isset ($_POST['fTransport'])) $tTransport = escape_string ($_POST['fTransport']);
      if (isset ($_POST['fDefaultaliases'])) $tDefaultaliases = escape_string ($_POST['fDefaultaliases']);
      if (isset ($_POST['fBackupmx'])) $tBackupmx = escape_string ($_POST['fBackupmx']);
      if (domain_exist ($fDomain)) $pAdminCreate_domain_domain_text = $PALANG['pAdminCreate_domain_domain_text_error'];
      if (empty ($fDomain) or !check_domain ($fDomain)) $pAdminCreate_domain_domain_text = $PALANG['pAdminCreate_domain_domain_text_error2'];
   }
      
   if ($error != 1)
   {
      $tAliases = $CONF['aliases'];
      $tMailboxes = $CONF['mailboxes'];
      $tMaxquota = $CONF['maxquota'];

      if ($fBackupmx == "on")
      {
         $fAliases = -1;
         $fMailboxes = -1;
         $fMaxquota = -1;
         $fBackupmx = 1;
         $sqlBackupmx = ('pgsql'==$CONF['database_type']) ? 'true' : 1;
      }
      else
      {
         $fBackupmx = 0;
         $sqlBackupmx = ('pgsql'==$CONF['database_type']) ? 'false' : 0;
      }
      
      $result = db_query ("INSERT INTO $table_domain (domain,description,aliases,mailboxes,maxquota,transport,backupmx,created,modified) VALUES ('$fDomain','$fDescription',$fAliases,$fMailboxes,$fMaxquota,'$fTransport',$sqlBackupmx,NOW(),NOW())");
      if ($result['rows'] != 1)
      {
         $tMessage = $PALANG['pAdminCreate_domain_result_error'] . "<br />($fDomain)<br />";
      }
      else
      {
         if ($fDefaultaliases == "on")
         {
            foreach ($CONF['default_aliases'] as $address=>$goto)
            {
               $address = $address . "@" . $fDomain;
               $result = db_query ("INSERT INTO $table_alias (address,goto,domain,created,modified) VALUES ('$address','$goto','$fDomain',NOW(),NOW())");
            }
         }
         $tMessage = $PALANG['pAdminCreate_domain_result_succes'] . "<br />($fDomain)</br />";
      }
   }

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/admin_create-domain.tpl");
   include ("../templates/footer.tpl");
/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
}
?>
