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
 * File: create-admin.php
 * Used to create new administrators.
 * Template File: admin_create-admin.php
 *
 *
 * Template Variables:
 *
 * tMessage
 * tUsername
 * tDomains
 *
 * Form POST \ GET Variables:
 *
 * fUsername
 * fPassword
 * fPassword2
 * fDomains
 */

require_once('common.php');

authentication_require_role('global-admin');

$list_domains = list_domains ();
$tDomains = array();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   $pAdminCreate_admin_username_text = $PALANG['pAdminCreate_admin_username_text'];
   $tDomains = array ();
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   if (isset ($_POST['fUsername'])) $fUsername = escape_string ($_POST['fUsername']);
   if (isset ($_POST['fPassword'])) $fPassword = escape_string ($_POST['fPassword']);
   if (isset ($_POST['fPassword2'])) $fPassword2 = escape_string ($_POST['fPassword2']);
   $fDomains = array();
   if (!empty ($_POST['fDomains'])) $fDomains = $_POST['fDomains'];

   list ($error, $tMessage, $pAdminCreate_admin_username_text, $pAdminCreate_admin_password_text) = create_admin($fUsername, $fPassword, $fPassword2, $fDomains);

   if ($error != 0) {
      if (isset ($_POST['fUsername'])) $tUsername = escape_string ($_POST['fUsername']);
      if (isset ($_POST['fDomains'])) $tDomains = $_POST['fDomains'];
   }
}

include ("templates/header.php");
include ("templates/menu.php");
include ("templates/admin_create-admin.php");
include ("templates/footer.php");

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
