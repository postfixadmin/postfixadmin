<?php
//
// File: edit-domain.php
//
// Template File: admin_edit-domain.tpl
//
// Template Variables:
//
// tDescription
// tAliases
// tMailboxes
// tMaxquota
// tActive
//
// Form POST \ GET Variables:
//
// fDescription
// fAliases
// fMailboxes
// fMaxquota
// fActive
//
require ("../config.inc.php");
require ("../functions.inc.php");
include ("../languages/" . $CONF['language'] . ".lang");

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   $domain = $_GET['domain'];
   $domain_properties = get_domain_properties ($domain);
   
#   $result = db_query ("SELECT * FROM domain WHERE domain='$domain'");
#
#   if ($result['rows'] == 1)
#   {
#      $row = mysql_fetch_array ($result['result']);
#      $tDescription = $row['description'];
#      $tAliases = $row['aliases'];
#      $tMailboxes = $row['mailboxes'];
#      $tMaxquota = $row['maxquota'];
#      $tActive = $row['active'];
#   }
      $tDescription = $domain_properties['description'];
      $tAliases = $domain_properties['aliases'];
      $tMailboxes = $domain_properties['mailboxes'];
      $tMaxquota = $domain_properties['maxquota'];
      $tActive = $domain_properties['active'];

   
   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/admin_edit-domain.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $domain = $_GET['domain'];
   
	$fDescription = $_POST['fDescription'];
	$fAliases = $_POST['fAliases'];
	$fMailboxes = $_POST['fMailboxes'];
	$fMaxquota = $_POST['fMaxquota'];
   $fActive = $_POST['fActive'];

   if ($fActive == "on") $fActive = 1;
   
	$result = db_query ("UPDATE domain SET description='$fDescription',aliases='$fAliases',mailboxes='$fMailboxes',maxquota='$fMaxquota',active='$fActive',modified=NOW() WHERE domain='$domain'");
	if ($result['rows'] == 1)
	{
		header ("Location: list-domain.php");
	}
	else
	{
		$tMessage = $LANG['pAdminEdit_domain_result_error'];
	}

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/admin_edit-domain.tpl");
   include ("../templates/footer.tpl");
}
?>
