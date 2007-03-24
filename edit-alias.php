<?php
// 
// Postfix Admin 
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// License Info: http://www.postfixadmin.com/?file=LICENSE.TXT
//
// File: edit-alias.php
//
// Template File: edit-alias.tpl
//
// Template Variables:
//
// tMessage
// tGoto
//
// Form POST \ GET Variables:
//
// fAddress
// fDomain
// fGoto
//
require ("./variables.inc.php");
require ("./config.inc.php");
require ("./functions.inc.php");
include ("./languages/" . check_language () . ".lang");

$SESSID_USERNAME = check_session ();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   $fAddress = escape_string ($_GET['address']);
   $fDomain = escape_string ($_GET['domain']);

   if (check_owner ($SESSID_USERNAME, $fDomain))
   {
      $result = db_query ("SELECT * FROM alias WHERE address='$fAddress' AND domain='$fDomain'");
      if ($result['rows'] == 1)
      {
         $row = db_array ($result['result']);
         $tGoto = $row['goto'];
      }
   }
   else
   {
      $tMessage = $PALANG['pEdit_alias_address_error'];
   }
   
   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/edit-alias.tpl");
   include ("./templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $pEdit_alias_goto = $PALANG['pEdit_alias_goto'];
   
   $fAddress = escape_string ($_GET['address']);
   $fAddress = strtolower ($fAddress);
   $fDomain = escape_string ($_GET['domain']);
   $fGoto = escape_string ($_POST['fGoto']);
   $fGoto = strtolower ($fGoto);

   if (!check_owner ($SESSID_USERNAME, $fDomain))
   {
      $error = 1;
      $tGoto = $fGoto;
      $tMessage = $PALANG['pEdit_alias_domain_error'] . "$fDomain</font>";
   }   
   
   if (empty ($fGoto))
   {
      $error = 1;
      $tGoto = $fGoto;
      $tMessage = $PALANG['pEdit_alias_goto_text_error1'];
   }

   $goto = preg_replace ('/\\\r\\\n/', ',', $fGoto);
	$goto = preg_replace ('/\r\n/', ',', $fGoto);
	$goto = preg_replace ('/[\s]+/i', '', $goto);
	$goto = preg_replace ('/\,*$/', '', $goto);
	$array = preg_split ('/,/', $goto);

	for ($i = 0; $i < sizeof ($array); $i++) {
		if (in_array ("$array[$i]", $CONF['default_aliases'])) continue;
		if (empty ($array[$i])) continue;
		if (!check_email ($array[$i]))
		{
   		$error = 1;
   		$tGoto = $goto;
   		$tMessage = $PALANG['pEdit_alias_goto_text_error2'] . "$array[$i]</div>";
	   }
   }
   
   if ($error != 1)
   {
      $result = db_query ("UPDATE alias SET goto='$goto',modified=NOW() WHERE address='$fAddress' AND domain='$fDomain'");
      if ($result['rows'] != 1)
      {
         $tMessage = $PALANG['pEdit_alias_result_error'];
      }
      else
      {
         db_log ($SESSID_USERNAME, $fDomain, "edit alias", "$fAddress -> $goto");
               
         header ("Location: overview.php?domain=$fDomain");
         exit;
      }
   }
   
   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/edit-alias.tpl");
   include ("./templates/footer.tpl");
}
?>
