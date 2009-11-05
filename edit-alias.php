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
 * Template File: edit-alias.php
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

if($CONF['alias_control_admin'] == 'NO' && !authentication_has_role('global-admin')) {
   die("Check config.inc.php - domain administrators do not have the ability to edit user's aliases (alias_control_admin)");
}

/* retrieve existing alias record for the user first... may be via GET or POST */

$fAddress = safepost('address', safeget('address')); # escaped below
$fDomain = escape_string(preg_replace("/.*@/", "", $fAddress));
$fAddress = escape_string($fAddress); # escaped now
if ($fAddress == "") {
   die("Required parameters not present");
}

/* Check the user is able to edit the domain's aliases */

   if(!check_owner($SESSID_USERNAME, $fDomain) && !authentication_has_role('global-admin'))
   {
	   die("You lack permission to do this. yes.");
   }

   $table_alias = table_by_key('alias');
   $alias_list = array();
   $orig_alias_list = array();
      $result = db_query ("SELECT * FROM $table_alias WHERE address='$fAddress' AND domain='$fDomain'");
      if ($result['rows'] == 1)
      {
         $row = db_array ($result['result']);
         $tGoto = $row['goto'];
		 $orig_alias_list = explode(',', $tGoto);
		 $alias_list = $orig_alias_list;
		 //. if we are not a global admin, and special_alias_control is NO, hide the alias that's the mailbox name.
		 if($CONF['special_alias_control'] == 'NO' && !authentication_has_role('global-admin')) {

         /* Has a mailbox as well? Remove the address from $tGoto in order to edit just the real aliases */
         $result = db_query ("SELECT * FROM $table_mailbox WHERE username='$fAddress' AND domain='$fDomain'");
         if ($result['rows'] == 1)
         {
			 $alias_list = array(); // empty it, repopulated again below
			 foreach($orig_alias_list as $alias) {
				 if(strtolower($alias) == strtolower($fAddress)) {
					 // mailbox address is dropped if they don't have special_alias_control enabled, and/or not a global-admin
				 }
				 else {
					 $alias_list[] = $alias;
				 }
			 }
		 }
   }
}
else {
	die("Invalid alias");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $pEdit_alias_goto = $PALANG['pEdit_alias_goto'];
   
   if (isset ($_POST['fGoto'])) $fGoto = escape_string ($_POST['fGoto']);
   $fGoto = strtolower ($fGoto);

   if (!check_alias_owner ($SESSID_USERNAME, $fAddress))
   {
     $error = 1;
     $tGoto = $fGoto;
     $tMessage = $PALANG['pEdit_alias_result_error'];
   }

   $goto = preg_replace ('/\\\r\\\n/', ',', $fGoto);
   $goto = preg_replace ('/\r\n/', ',', $goto);
   $goto = preg_replace ('/[\s]+/i', '', $goto);
   $goto = preg_replace ('/,*$|^,*/', '', $goto);
   $goto = preg_replace ('/,,*/', ',', $goto);

   if (empty ($goto) && !authentication_has_role('global-admin'))
   {
      $error = 1;
      $tGoto = $_POST['fGoto'];
      $tMessage = $PALANG['pEdit_alias_goto_text_error1'];
   }

   $new_aliases = array();
   if ($error != 1)
   {
      $new_aliases = explode(',', $goto);
   }
   $new_aliases = array_unique($new_aliases);

   foreach($new_aliases as $address) {
      if (in_array($address, $CONF['default_aliases'])) continue;
      if (empty($address)) continue; # TODO: should never happen - remove after 2.2 release
      if (!check_email($address))
      {
         $error = 1;
         $tGoto = $goto;
		 $tMessage = $PALANG['pEdit_alias_goto_text_error2'] . "$address</span>";
      }
   }
   
   $result = db_query ("SELECT * FROM $table_mailbox WHERE username='$fAddress' AND domain='$fDomain'");
   if ($result['rows'] == 1)
   {
	  if($CONF['alias_control_admin'] == 'NO' && !authentication_has_role('global-admin')) {
		  // if original record had a mailbox alias, so ensure the updated one does too.
		  if(in_array($orig_alias_list, $fAddress)) {
			  $new_aliases[] = $fAddress;
		  }
	  }

   }
   // duplicates suck, mmkay..
   $new_aliases = array_unique($new_aliases);

   $goto = implode(',', $new_aliases);

   if ($error != 1)
   {
	  $goto = escape_string($goto);
      $result = db_query ("UPDATE $table_alias SET goto='$goto',modified=NOW() WHERE address='$fAddress' AND domain='$fDomain'");
      if ($result['rows'] != 1)
      {
         $tMessage = $PALANG['pEdit_alias_result_error'];
      }
      else
      {
         db_log ($SESSID_USERNAME, $fDomain, 'edit_alias', "$fAddress -> $goto");

		 header ("Location: list-virtual.php?domain=$fDomain");
         exit;
      }
   }
}

$fAddress = htmlentities($fAddress, ENT_QUOTES);
$fDomain = htmlentities($fDomain, ENT_QUOTES);

$array = preg_split ('/,/', $tGoto);
// TOCHECK
$array = $alias_list;

$smarty->assign ('fAddress', $fAddress);
$smarty->assign ('array', $array);
$smarty->assign ('tMessage', $tMessage);
$smarty->assign ('smarty_template', 'edit-alias');
$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
