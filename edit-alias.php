<?php
/** 
 * Postfix Admin 
 * 
 * LICENSE 
 * This source file is subject to the GPL license that is bundled with  
 * this package in the file LICENSE.TXT. 
 * 
 * Further details on the project are available at : 
 *     http://www.postfixadmin.com or http://postfixadmin.sf.net 
 * 
 * @version $Id$ 
 * @license GNU GPL v2 or later. 
 * 
 * File: edit-alias.php 
 * Used to update an alias.
 *
 * Template File: edit-alias.tpl
 *
 * Template Variables:
 *
 * tMessage
 * tGoto
 *
 * Form POST \ GET Variables:
 *
 * fAddress
 * fDomain
 * fGoto
 */

require_once('common.php');

authentication_require_role('admin');
$SESSID_USERNAME = authentication_get_username();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   if (isset ($_GET['address'])) $fAddress = escape_string ($_GET['address']);
   if (isset ($_GET['domain'])) $fDomain = escape_string ($_GET['domain']);

   if (check_owner ($SESSID_USERNAME, $fDomain) || authentication_has_role('global-admin'))
   {
      $result = db_query ("SELECT * FROM $table_alias WHERE address='$fAddress' AND domain='$fDomain'");
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
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $pEdit_alias_goto = $PALANG['pEdit_alias_goto'];
   
   if (isset ($_GET['address'])) $fAddress = escape_string ($_GET['address']);
   $fAddress = strtolower ($fAddress);
   if (isset ($_GET['domain'])) $fDomain = escape_string ($_GET['domain']);
   if (isset ($_POST['fGoto'])) $fGoto = escape_string ($_POST['fGoto']);
   $fGoto = strtolower ($fGoto);

   if (! (check_owner ($SESSID_USERNAME, $fDomain) || authentication_has_role('global-admin')) )
   {
      $error = 1;
      $tGoto = $_POST['fGoto'];
      $tMessage = $PALANG['pEdit_alias_domain_error'] . "$fDomain</span>";
   }
   elseif (!check_alias_owner ($SESSID_USERNAME, $fAddress))
   {
     $error = 1;
     $tGoto = $fGoto;
     $tMessage = $PALANG['pEdit_alias_result_error'];
   }
   elseif (empty ($fGoto))
   {
      $error = 1;
      $tGoto = $_POST['fGoto'];
      $tMessage = $PALANG['pEdit_alias_goto_text_error1'];
   }

   if ($error != 1)
   {
      $goto = preg_replace ('/\\\r\\\n/', ',', $fGoto);
      $goto = preg_replace ('/\r\n/', ',', $goto);
      $goto = preg_replace ('/[\s]+/i', '', $goto);
      $goto = preg_replace ('/\,*$/', '', $goto);
      $array = preg_split ('/,/', $goto);
   }
   else
   {
      $array = array();
   }

	for ($i = 0; $i < sizeof ($array); $i++) {
		if (in_array ("$array[$i]", $CONF['default_aliases'])) continue;
		if (empty ($array[$i])) continue;
		if (!check_email ($array[$i]))
		{
   		$error = 1;
   		$tGoto = $goto;
   		$tMessage = $PALANG['pEdit_alias_goto_text_error2'] . "$array[$i]</span>";
	   }
   }
   
   if ($error != 1)
   {
      $result = db_query ("UPDATE $table_alias SET goto='$goto',modified=NOW() WHERE address='$fAddress' AND domain='$fDomain'");
      if ($result['rows'] != 1)
      {
         $tMessage = $PALANG['pEdit_alias_result_error'];
      }
      else
      {
         db_log ($SESSID_USERNAME, $fDomain, 'edit_alias', "$fAddress -> $goto");

         if (authentication_has_role('global-admin')) {
            header ("Location: list-virtual.php?domain=$fDomain");
         } else {
            header ("Location: overview.php?domain=$fDomain");
         }
         exit;
      }
   }
}

include ("$incpath/templates/header.tpl");
include ("$incpath/templates/menu.tpl");
include ("$incpath/templates/edit-alias.tpl");
include ("$incpath/templates/footer.tpl");
?>
