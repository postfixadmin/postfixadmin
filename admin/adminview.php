<?php
require "../config.inc.php";
require "../my_lib.php";

print_header("admin");

$result = db_query("SELECT * FROM admin ORDER BY domain,username");
	
if ($result['rows'] > 0) {
	print "<table border=\"1\" cellpadding=\"2\" cellspacing=\"2\" width=\"75%\">\n";
	print "<tr class=\"header\">";
	print "<td>Domain Name</td>";
	print "<td>Admin Alias</td>";
	print "<td>Last Modified</td>";
	print "<td>Active</td>";
	print "<td colspan=\"2\">&nbsp;</td>";
	print "</tr>";
	while ($row = mysql_fetch_array ($result['result'])) {
		print "<tr onMouseOver=\"this.bgColor = '#dfdfdf'\" onMouseOut =\"this.bgColor = '#ffffff'\" bgcolor=\"#ffffff\">";
		print "<td><a href=\"virtualview.php?where=" . $row['domain'] . "\">" . $row['domain'] . "</a></td>";
		print "<td>" . $row['username'] . "</td>";
		print "<td>" . $row['change_date'] . "</td>";
		print "<td>" . $row['active'] . "</td>";
		print "<td><a href=\"passwd.php?username=" . $row['username'] . "\">edit</a></td>";
		print "<td><a href=\"delete.php?table=admin&where=username&delete=" . $row['username'] . "\" onclick=\"return confirm ('Are you sure you want to delete this?')\">del</a></td>";
		print "</tr>\n";
	}
	print "</table>\n";
	print "<p />\n";
	print "Found: " . $result['rows'] . "<br />\n";
} else {
	print "<p />\n";
	print "Nothing Found in the Admin Table!\n";
}
print_footer();
?>
