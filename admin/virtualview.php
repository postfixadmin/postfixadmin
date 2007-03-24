<?php
require "../config.inc.php";
require "../my_lib.php";

print_header("admin");

$where = $_GET['where'];

if (!empty($where)) {
	$query = "SELECT alias.address,alias.goto,alias.change_date,alias.active FROM alias LEFT JOIN mailbox ON alias.address=mailbox.username WHERE alias.domain='$where' AND mailbox.maildir IS NULL ORDER BY alias.address";
	print "Domain: $where\n";
	print "<p />\n";
} else {
	$query = "SELECT * FROM alias $where ORDER BY domain, address";
}
$result = db_query("$query");
if ($result['rows'] > 0) {
	print "<center>\n";
	print "<table border=\"1\">\n";
	print "<tr class=\"header\">";
	print "<td>From</td>";
	print "<td>To</td>";
	print "<td>Last Modified</td>";
	print "<td>Active</td>";
	print "</tr>";
	while ($row = mysql_fetch_array ($result['result'])) {
		print "<tr onMouseOver=\"this.bgColor = '#dfdfdf'\" onMouseOut =\"this.bgColor = '#ffffff'\" bgcolor=\"#ffffff\">";
		print "<td>$row[address]</td>";
		print "<td>" . ereg_replace (",", "<br>", $row[goto]) . "</td>";
		print "<td>$row[change_date]</td>";
		print "<td>$row[active]</td>";
		print "</tr>\n";
	}
	print "</table>\n";
	print "</center>\n";
	print "Found: $result['rows']\n";
	print "<p>\n";
} else {
	print "Nothing Found in the Alias Table!\n";
	print "<p>\n";
}

if (!empty($where)) {
	$query = "SELECT * FROM mailbox WHERE domain='$where'";
} else {
	$query = "SELECT * FROM mailbox ORDER BY domain, username";
}
$result = db_query("$query");
if ($result['rows'] > 0) {
	print "<center>\n";
	print "<table border=\"1\">\n";
	print "<tr class=\"header\">";
	print "<td>Email</td>";
	print "<td>Name</td>";
	print "<td>Mailbox</td>";
	print "<td>Last Modified</td>";
	print "<td>Active</td>";
	print "</tr>";
	while ($row = mysql_fetch_array ($result['result'])) {
		print "<tr>";
		print "<td>$row[username]</td>";
		print "<td>$row[name]</td>";
		print "<td>$row[maildir]</td>";
		print "<td>$row[change_date]</td>";
		print "<td>$row[active]</td>";
		print "</tr>\n";
	}
	print "</table>\n";
	print "</center>\n";
	print "Found: $result['rows']<br>\n";
} else {
	print "<p>\n";
	print "Nothing Found in the Mailbox Table!\n";
}
print_footer();
?>
