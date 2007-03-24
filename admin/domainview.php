<?php
require "../config.inc.php";
require "../my_lib.php";

print_header("admin");

$result = db_query("SELECT * FROM domain ORDER BY domain");
	
if ($result[rows] > 0) {
	print "<table border=\"1\" cellpadding=\"2\" cellspacing=\"2\" width=\"75%\">\n";
	print "<tr class=\"header\">";
	print "<td>Domain</td>";
	print "<td>Description</td>";
	print "<td>Aliases</td>";
	print "<td>Mailboxes</td>";
	print "<td>Last Modified</td>";
	print "<td>&nbsp;</td>";
	print "<td>&nbsp;</td>";
	print "</tr>";
	while ($row = mysql_fetch_array ($result[result])) {
		print "<tr onMouseOver=\"this.bgColor = '#dfdfdf'\" onMouseOut =\"this.bgColor = '#ffffff'\" bgcolor=\"#ffffff\">";
		print "<td><a href=\"virtualview.php?where=$row[domain]\">$row[domain]</a></td>";
		print "<td>$row[description]</td>";
		$aliases = db_query("SELECT * FROM alias WHERE domain='$row[domain]'");
		print "<td>$aliases[rows]</td>";
		$mailbox = db_query("SELECT * FROM mailbox WHERE domain='$row[domain]'");
		print "<td>$mailbox[rows]</td>";
		print "<td>$row[change_date]</td>";
		print "<td><a href=\"modify.php?modify=$row[domain]\">edit</a></td>";
		print "<td><a href=\"delete.php?table=domain&where=domain&delete=$row[domain]\" onclick=\"return confirm ('Do you really want to delete all records for this domain? This can not be undone!')\">del</a></td>";
		print "</tr>\n";
	}
	print "</table>\n";
	print "<p />\n";
	print "Found: $result[rows]<br />\n";
} else {
	print "<p />\n";
	print "Nothing Found in the Domain Table!\n";
}
print_footer();
?>
