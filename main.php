<?php
require "config.inc.php";
require "my_lib.php";

$sessid = check_session();

print_header("menu");

print "Domain: $sessid[domain]\n";
print "<p />\n";

if ($alias_control == "YES") {
	$query = "SELECT alias.address,alias.goto,alias.change_date FROM alias WHERE alias.domain='$sessid[domain]' ORDER BY alias.address";
} else {
	$query = "SELECT alias.address,alias.goto,alias.change_date FROM alias LEFT JOIN mailbox ON alias.address=mailbox.username WHERE alias.domain='$sessid[domain]' AND mailbox.maildir IS NULL ORDER BY alias.address";
}

$result = db_query ("$query");
	
if ($result[rows] > 0) {
	print "<center>\n";
	print "<table border=\"1\">\n";
	print "<tr class=\"header\">";
	print "<td>From</td>";
	print "<td>To</td>";
	print "<td>Last Modified</td>";
	print "<td colspan=\"2\">&nbsp;</td>";
	print "</tr>";
	while ($row = mysql_fetch_array ($result[result])) {
		print "<tr onMouseOver=\"this.bgColor = '#dfdfdf'\" onMouseOut =\"this.bgColor = '#ffffff'\" bgcolor=\"#ffffff\">";
		print "<td>$row[address]</td>";
		print "<td>" . ereg_replace (",", "<br>", $row[goto]) . "</td>";
		print "<td>$row[change_date]</td>";
		print "<td><a href=\"modify.php?" . session_name() . "=" . session_id() . "&modify=$row[address]\">edit</a></td>";
		print "<td><a href=\"delete.php?" . session_name() . "=" . session_id() . "&table=alias" . "&delete=" . urlencode($row[address]) . "\"onclick=\"return confirm ('Are you sure you want to delete this?')\">del</a></td>";
		print "</tr>\n";
	}
	print "</table>\n";
	print "</center>\n";
	print "<p />\n";
} else {
	print "Nothing Found in the Alias Table!\n";
	print "<p />\n";
}

$query = "SELECT * FROM mailbox WHERE domain='$sessid[domain]' ORDER BY username";

$result = db_query ("$query");
	
if ($result[rows] > 0) {
	print "<center>\n";
	print "<table border=\"1\">\n";
	print "<tr class=\"header\">";
	print "<td>Email</td>";
	print "<td>Name</td>";
	if (!empty($quota_table)) print "<td>Quota</td>";
	print "<td>Last Modified</td>";
	print "<td colspan=\"2\">&nbsp;</td>";
	print "</tr>";
	while ($row = mysql_fetch_array ($result[result])) {
		print "<tr onMouseOver=\"this.bgColor = '#dfdfdf'\" onMouseOut =\"this.bgColor = '#ffffff'\" bgcolor=\"#ffffff\">";
		print "<td>$row[username]</td>";
		print "<td><a href=\"changename.php?" . session_name() . "=" . session_id() . "&username=$row[username]&name=$row[name]\">$row[name]</a></td>";
		if (!empty($quota_table)) print "<td>$row[$quota_table]</td>";
		print "<td>$row[change_date]</td>";
		print "<td><a href=\"pwd.php?" . session_name() . "=" . session_id() . "&username=$row[username]\">edit</a></td>";
		print "<td><a href=\"delete.php?" . session_name() . "=" . session_id() . "&table=mailbox" . "&delete=" . urlencode($row[username]) . "\"onclick=\"return confirm ('Are you sure you want to delete this?')\">del</a></td>";
		print "</tr>\n";
	}
	print "</table>\n";
	print "</center>\n";
	print "<p />\n";
} else {
	print "Nothing Found in the Mailbox Table!\n";
	print "<p />\n";
}
print_footer();
?>
