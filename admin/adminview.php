<?
include "my_lib.php";

print_header();

print_menu();
print "<hr>\n";

$query = "SELECT * FROM admin ORDER BY domain,username";

$result = db_query ("$query");
	
if ($result[rows] > 0) {
	print "<table border=1 cellpadding=2 cellspacing=2 width=75%>\n";
	
	while ($row = mysql_fetch_array ($result[result])) {
		print "<tr onMouseOver=\"this.bgColor = '#dfdfdf'\" onMouseOut =\"this.bgColor = '#ffffff'\" bgcolor=\"#ffffff\">";
		print "<td><a href=virtualview.php?where=$row[domain]>$row[domain]</a></td>";
		print "<td>$row[username]</td>";
		print "<td>$row[change_date]</td>";
		print "<td>$row[active]</td>";
		print "<td><a href=delete.php?table=admin&where=username&delete=$row[username]>del</a></td>";
		print "</tr>\n";
	}

	print "</table>\n";
	print "<p>\n";
	print "Found: $result[rows]<br>\n";

} else {
	print "<p>\n";
	print "Nothing Found!\n";
}

print "<p>\n";
print_footer();
?>
