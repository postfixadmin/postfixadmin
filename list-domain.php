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
 * File: list-domain.php
 * List all domains as a quick overview.
 * Template File: admin_list-domain.tpl
 *
 * Template Variables:
 *
 * -none-
 *
 * Form POST \ GET Variables:
 *
 * fUsername
 */

require_once('common.php');

authentication_require_role('admin');

if (authentication_has_role('global-admin')) {
	$list_admins = list_admins ();
	$is_superadmin = 1;
} else {
	$list_admins = array(authentication_get_username());
	$is_superadmin = 0;
}

if ($_SERVER['REQUEST_METHOD'] == "POST" && $is_superadmin && isset ($_POST['fUsername'])) {
  $fUsername = escape_string ($_POST['fUsername']);
  $list_domains = list_domains_for_admin ($fUsername);
} elseif ($_SERVER['REQUEST_METHOD'] == "GET" && $is_superadmin && isset ($_GET['username'])) {
  $fUsername = escape_string ($_GET['username']);
  $list_domains = list_domains_for_admin ($fUsername);
} elseif ($is_superadmin) {
   $list_domains = list_domains ();
} else {
	$list_domains = list_domains_for_admin(authentication_get_username());
}

   if (!empty ($list_domains))
   {
      for ($i = 0; $i < sizeof ($list_domains); $i++)
      {
         $domain_properties[$i] = get_domain_properties ($list_domains[$i]);
      }
   }
#}

include ("templates/header.tpl");
include ("templates/menu.tpl");

if ($is_superadmin) {
	include ("templates/admin_list-domain.tpl");
} else {
	include ("templates/overview-get.tpl");
}
include ("templates/footer.tpl");
?>
