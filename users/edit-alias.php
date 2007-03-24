<?php
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
require ("../config.inc.php");
require ("../functions.inc.php");
include ("../languages/" . $CONF['language'] . ".lang");

$USERID_USERNAME = check_user_session ();
$tmp = preg_split ('/@/', $USERID_USERNAME);
$USERID_DOMAIN = $tmp[1];

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
      $result = db_query ("SELECT * FROM alias WHERE address='$USERID_USERNAME'");
      if ($result['rows'] == 1)
      {
         $row = mysql_fetch_array ($result['result']);
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
   $pEdit_alias_goto = $PALANG['pEdit_alias_goto'];
   
   $fGoto = $_POST['fGoto'];

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
      if (empty ($fGoto))
      {
         $goto = $USERID_USERNAME;
      }
      else
      {
         $goto = $USERID_USERNAME . "," . $goto;
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
