<?
//
// If site_lib.php is called directly, redirect to login.php
//
if (ereg("site_lib.php", $PHP_SELF)) {
	header("Location: login.php");
}

$title = "Mail Admin Admin";
$subtitle = "";
$version = "Built on Postfix Admin v1.3.7";
$db_host = "localhost";
$db_name = "postfix";
$db_user = "postfixadmin";
$db_pass = "postfixadmin";



//
// print_menu
// Action: Prints out the requirement menu bar
// Call: print_menu()
//
function print_menu() {
	print "<table>\n";
	print "<tr>\n";
	print "<td width=8>&nbsp;</td>\n";
	print "<td class=menu>\n";
	print "<a target=_top href=adminview.php>Admin View</a>";
	print "</td>\n";
	print "<td width=8>&nbsp;</td>\n";
	print "<td class=menu>\n";
	print "<a target=_top href=domainview.php>Domain View</a>";
	print "</td>\n";
	print "<td width=8>&nbsp;</td>\n";
	print "<td class=menu>\n";
	print "<a target=_top href=virtualview.php>Virtual View</a>";
	print "</td>\n";
	print "<td width=8>&nbsp;</td>\n";
	print "<td class=menu>\n";
	print "<a target=_top href=newadmin.php>New Admin</a>";
	print "</td>\n";
	print "<td width=8>&nbsp;</td>\n";
	print "<td class=menu>\n";
	print "<a target=_top href=newdomain.php>New Domain</a>";
	print "</td>\n";
	print "<td width=8>&nbsp;</td>\n";
	print "</tr>\n";
	print "</table>\n";
}
?>