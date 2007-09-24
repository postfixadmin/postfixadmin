<?php
// 
// Postfix Admin 
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// Licensed under GPL for more info check GPL-LICENSE.TXT
//
// File: create-alias.php
//
// Template File: create-alias.tpl
//
// Template Variables:
//
// tMessage
// tAddress
// tGoto
// tDomain
//
// Form POST \ GET Variables:
//
// fAddress
// fGoto
// fDomain
//

require_once('common.php');

authentication_require_role('admin');
$username = authentication_get_username();
$SESSID_USERNAME = $username;
if(authentication_has_role('global-admin')) {
    $list_domains = list_domains ();
}
else {
   $list_domains = list_domains_for_admin ($username);
}

$pCreate_alias_goto_text = $PALANG['pCreate_alias_goto_text'];

if ($_SERVER['REQUEST_METHOD'] == "GET")
{

   if (isset ($_GET['domain'])) $tDomain = escape_string ($_GET['domain']);
   
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{

   if (isset ($_POST['fAddress']) && isset ($_POST['fDomain'])) $fAddress = escape_string ($_POST['fAddress']) . "@" . escape_string ($_POST['fDomain']);
   $fAddress = strtolower ($fAddress);
   if (isset ($_POST['fGoto'])) $fGoto = escape_string ($_POST['fGoto']);
   $fGoto = strtolower ($fGoto);
   isset ($_POST['fActive']) ? $fActive = escape_string ($_POST['fActive']) : $fActive = "1";
   if (isset ($_POST['fDomain'])) $fDomain = escape_string ($_POST['fDomain']);

   if (!preg_match ('/@/',$fGoto))
   {
      $fGoto = $fGoto . "@" . escape_string ($_POST['fDomain']);
   }
   
   if (! (authentication_has_role('global-admin') || check_owner ($SESSID_USERNAME, $fDomain) ))
   {
      $error = 1;
      $tAddress = escape_string ($_POST['fAddress']);
      $tGoto = $fGoto;
      $tDomain = $fDomain;      
      $pCreate_alias_address_text = $PALANG['pCreate_alias_address_text_error1'];
   }

   if (!check_alias ($fDomain))
   {
      $error = 1;
      $tAddress = escape_string ($_POST['fAddress']);
      $tGoto = $fGoto;
      $tDomain = $fDomain;
      $pCreate_alias_address_text = $PALANG['pCreate_alias_address_text_error3'];
   }
   
	if (empty ($fAddress) or !check_email ($fAddress))
	{
      $error = 1;
      $tAddress = escape_string ($_POST['fAddress']);
      $tGoto = $fGoto;
      $tDomain = $fDomain;
      $pCreate_alias_address_text = $PALANG['pCreate_alias_address_text_error1'];
   }

	if (empty ($fGoto) or !check_email ($fGoto))
	{
      $error = 1;
      $tAddress = escape_string ($_POST['fAddress']);
      $tGoto = $fGoto;
      $tDomain = $fDomain;
      $pCreate_alias_goto_text = $PALANG['pCreate_alias_goto_text_error'];
	}

   if (escape_string ($_POST['fAddress']) == "*") $fAddress = "@" . escape_string ($_POST['fDomain']);

   $result = db_query ("SELECT * FROM $table_alias WHERE address='$fAddress'");
   if ($result['rows'] == 1)
   {
      $error = 1;
      $tAddress = escape_string ($_POST['fAddress']);
      $tGoto = $fGoto;
      $tDomain = $fDomain;
      $pCreate_alias_address_text = $PALANG['pCreate_alias_address_text_error2'];
   }

   if ($fActive == "on")
   {
       $sqlActive = db_get_boolean(True);
   }
   else
   {
       $sqlActive = db_get_boolean(False);
   }

   if ($error != 1)
   {
      if (preg_match ('/^\*@(.*)$/', $fGoto, $match)) $fGoto = "@" . $match[1];
      
      $result = db_query ("INSERT INTO $table_alias (address,goto,domain,created,modified,active) VALUES ('$fAddress','$fGoto','$fDomain',NOW(),NOW(),'$sqlActive')");
      if ($result['rows'] != 1)
      {
         $tDomain = $fDomain;
         $tMessage = $PALANG['pCreate_alias_result_error'] . "<br />($fAddress -> $fGoto)<br />\n";
      }
      else
      {
         db_log ($SESSID_USERNAME, $fDomain, "create alias", "$fAddress -> $fGoto");

         $tDomain = $fDomain;
         $tMessage = $PALANG['pCreate_alias_result_success'] . "<br />($fAddress -> $fGoto)<br />\n";
      }
   }

}

include ("$incpath/templates/header.tpl");

if (authentication_has_role('global-admin')) {
   include ("$incpath/templates/admin_menu.tpl");
} else {
   include ("$incpath/templates/menu.tpl");
}

include ("$incpath/templates/create-alias.tpl");
include ("$incpath/templates/footer.tpl");
?>
