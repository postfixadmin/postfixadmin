<?php
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
require ("../variables.inc.php");
require ("../config.inc.php");
require ("../functions.inc.php");
include ("../languages/" . $CONF['language'] . ".lang");

$list_domains = list_domains ();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   $pCreate_alias_goto_text = $PALANG['pCreate_alias_goto_text'];

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/create-alias.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $pCreate_alias_goto_text = $PALANG['pCreate_alias_goto_text'];

   $fAddress = $_POST['fAddress'] . "@" . $_POST['fDomain'];
   $fGoto = $_POST['fGoto'];
   $fDomain = $_POST['fDomain'];

   if (!preg_match ('/@/',$fGoto))
   {
      $fGoto = $fGoto . "@" . $_POST['fDomain'];
   }
   
   if (!check_alias ($fDomain))
   {
      $error = 1;
      $tAddress = $_POST['fAddress'];
      $tGoto = $fGoto;
      $tDomain = $fDomain;
      $pCreate_alias_address_text = $PALANG['pCreate_alias_address_text_error3'];
   }
   
	if (empty ($fAddress) or !check_email ($fAddress))
	{
      $error = 1;
      $tAddress = $_POST['fAddress'];
      $tGoto = $fGoto;
      $tDomain = $fDomain;
      $pCreate_alias_address_text = $PALANG['pCreate_alias_address_text_error1'];
   }

	if (empty ($fGoto) or !check_email ($fGoto))
	{
      $error = 1;
      $tAddress = $_POST['fAddress'];
      $tGoto = $fGoto;
      $tDomain = $fDomain;
      $pCreate_alias_goto_text = $PALANG['pCreate_alias_goto_text_error'];
	}

   if ($_POST['fAddress'] == "*") $fAddress = "@" . $_POST['fDomain'];

   $result = db_query ("SELECT * FROM alias WHERE address='$fAddress'");
   if ($result['rows'] == 1)
   {
      $error = 1;
      $tAddress = $_POST['fAddress'];
      $tGoto = $fGoto;
      $tDomain = $fDomain;
      $pCreate_alias_address_text = $PALANG['pCreate_alias_address_text_error2'];
   }

   if ($error != 1)
   {
      if (preg_match ('/^\*@(.*)$/', $fGoto, $match)) $fGoto = "@" . $match[1];
      
      $result = db_query ("INSERT INTO alias (address,goto,domain,created,modified) VALUES ('$fAddress','$fGoto','$fDomain',NOW(),NOW())");
      if ($result['rows'] != 1)
      {
         $tDomain = $fDomain;
         $tMessage = $PALANG['pCreate_alias_result_error'] . "<br />($fAddress -> $fGoto)<br />";
      }
      else
      {
         db_log ("site admin", $fDomain, "create alias", "$fAddress -> $fGoto");

         $tDomain = $fDomain;
         $tMessage = $PALANG['pCreate_alias_result_succes'] . "<br />($fAddress -> $fGoto)</br />";
      }
   }

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/create-alias.tpl");
   include ("../templates/footer.tpl");
}
?>
