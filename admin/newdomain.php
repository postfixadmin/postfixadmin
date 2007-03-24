<?
include "my_lib.php";

print_header();

print_menu();
print "<hr>\n";

if (!empty($_POST[submit])) {
	$domain = $_POST[domain];
	$description = $_POST[description];

	if (empty($domain) or empty($description)) {
		print "<p>\n";
		print "You will need to fill all fields.\n";
		print "<p>\n";
		print_footer();
		exit;
	}

	$result = db_query ("SELECT * FROM domain WHERE domain='$domain'");
	if ($result[rows] == 1) {
		print "<p>\n";
		print "This domain already exists, please choose a different one.\n";
		print "<p>\n";
		print_footer();
		exit;
	}

	$result = db_query ("INSERT INTO domain (domain,description,create_date,change_date) VALUES('$domain','$description',NOW(),NOW())");
	if ($result[rows] == 1) {
		print "<i>$domain</i> has been <b>added</b> to the mailbox table!\n";
		print "<p>\n";
	} else {
		print "<b>Unable</b> to add: <i>$domain</i> to the mailbox table!\n";
		print "<p>\n";
		print_footer();
		exit;
	}
}
?>

Create a new domain.
<p>
<form method=post>
<table class=form>
<tr><td>Domain:</td><td><input type=text name=domain></td></tr>
<tr><td>Description:</td><td><input type=text name=description></td><td></tr>
<tr><td colspan=3 align=center><input type=submit name=submit value='Add Entry'></td></tr>
</table>
</form>

<?
print "<p>\n";
print_footer();
?>
