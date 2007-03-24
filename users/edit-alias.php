<?php
// 
// Postfix Admin 
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// License Info: http://www.postfixadmin.com/?file=LICENSE.TXT
//
// File: edit-alias.php
//
// Template File: users_edit-alias.tpl
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
require ("../variables.inc.php");
require ("../config.inc.php");
require ("../functions.inc.php");
include ("../languages/" . check_language () . ".lang");

$USERID_USERNAME = check_user_session ();
$tmp = preg_split ('/@/', $USERID_USERNAME);
$USERID_DOMAIN = $tmp[1];

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   $vacation_domain = $CONF['vacation_domain'];
   
   $result = db_query ("SELECT * FROM alias WHERE address='$USERID_USERNAME'");
   if ($result['rows'] == 1)
   {
      $row = db_array ($result['result']);
      $tGoto = $row['goto'];
   }
   else
   {
      $tMessage = $PALANG['pEdit_alias_address_error'];
   }
   
   include ("../templates/header.tpl");
   include ("../templates/users_menu.tpl");
   include ("../templates/users_edit-alias.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $vacation_domain = $CONF['vacation_domain'];
   
   $pEdit_alias_goto = $PALANG['pEdit_alias_goto'];

   if (isset ($_POST['fVacation'])) $fVacation = $_POST['fVacation'];   
   if (isset ($_POST['fGoto'])) $fGoto = escape_string ($_POST['fGoto']);
   $fGoto = strtolower ($fGoto);

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
   		$tMessage = $PALANG['pEdit_alias_goto_text_error2'] . "$array[$i]</font>";
	   }
   }
   
   if ($error != 1)
   {
      if (empty ($goto))
      {
         $goto = $USERID_USERNAME;
      }
      else
      {
         $goto = $USERID_USERNAME . "," . $goto;
      }
      if ($fVacation == "YES")
      {
         $goto .= "," . $USERID_USERNAME . "@" . $vacation_domain;
      }
      
      $result = db_query ("UPDATE alias SET goto='$goto',modified=NOW() WHERE address='$USERID_USERNAME'");
      if ($result['rows'] != 1)
      {
         $tMessage = $PALANG['pEdit_alias_result_error'];
      }
      else
      {
         db_log ($USERID_USERNAME, $USERID_DOMAIN, "edit alias", "$USERID_USERNAME -> $goto");
               
         header ("Location: main.php");
         exit;
      }
   }
   
   include ("../templates/header.tpl");
   include ("../templates/users_menu.tpl");
   include ("../templates/users_edit-alias.tpl");
   include ("../templates/footer.tpl");
}
?>
