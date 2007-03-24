<?
include "my_lib.php";

print_header();

print_menu();
print "<hr>\n";

$order = $_GET[order];
$where = $_GET[where];

if (empty($order)) $order = "domain,address";
if (!empty($where)) $where = "WHERE " . "domain='$where'";

$query = "SELECT * FROM alias $where ORDER BY $order";

print "$query\n";
print "<p>\n";

$result = db_query ("$query");
	
if ($result[rows] > 0) {
	print "<center>\n";
	print "<table border=1>\n";
	while ($row = mysql_fetch_array ($result[result])) {
		print "<tr onMouseOver=\"this.bgColor = '#dfdfdf'\" onMouseOut =\"this.bgColor = '#ffffff'\" bgcolor=\"#ffffff\">";
		print "<td>$row[address]</td>";
		print "<td>" . ereg_replace (",", "<br>", $row[goto]) . "</td>";
		print "<td>$row[change_date]</td>";
		print "<td>$row[active]</td>";
		print "</tr>\n";
	}

	print "</table>\n";
	print "</center>\n";
	print "Found: $result[rows]\n";
	print "<p>\n";

} else {
	print "Nothing Found!\n";
	print "<p>\n";
}

$query = "SELECT * FROM mailbox $where";

print "$query\n";

$result = db_query ("$query");
if ($result[rows] > 0) {
	print "<center>\n";
	print "<table border=1>\n";
	while ($row = mysql_fetch_array ($result[result])) {
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
	print "Found: $result[rows]<br>\n";
} else {
	print "<p>\n";
	print "Nothing Found!\n";
}
print_footer();
?>
