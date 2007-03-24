<?php
require "../config.inc.php";
require "../my_lib.php";

print_header("admin");

if (!empty($_POST[submit])) {
	$domain = $_POST[domain];
	$description = $_POST[description];
	$aliases = $_POST[aliases];

	if (empty($domain) or empty($description)) print_error ("You will need to fill all fields.");

	if (check_email($domain)) print_error("The domain that you have supplied is not a valid domain, please go back.");

	$result = db_query ("SELECT * FROM domain WHERE domain='$domain'");
	if ($result[rows] == 1) print_error ("This domain already exists, please choose a different one.");

	$result = db_query ("INSERT INTO domain (domain,description,create_date,change_date) VALUES('$domain','$description',NOW(),NOW())");
	if ($result[rows] == 1) {
		print "<i>$domain</i> has been <b>added</b> to the domain table!\n";
		print "<p>\n";
	} else {
		print_error ("Unable to add: <i>$domain</i> to the domain table!");
	}

	if ($aliases == "on") {
		$alias_keys = array_keys($default_aliases);
		$alias_values = array_values($default_aliases);
		for ($i = 0; $i < count($alias_keys); $i++) {
			$address = $alias_keys[$i] . "@" . $domain;
			$result = db_query ("INSERT INTO alias (address,goto,domain,create_date,change_date) VALUES('$address','$alias_values[$i]','$domain',NOW(),NOW())");
			if ($result[rows] == 1) {
				print "<i>$address</i> has been <b>added</b> to the alias table!<br>\n";
			} else {
				print_error ("Unable to add: <i>$address</i> to the alias table!");
			}	
		}
		print "<p />\n";
	}
}
?>
Create a new domain.
<p />
<form method="post">
<table class="form">
<tr><td>Domain:</td><td><input type="text" name="domain"></td></tr>
<tr><td>Description:</td><td><input type="text" name="description"></td></tr>
<tr><td>Add default mail aliases:</td><td><input type="checkbox" name="aliases"></td></tr>
<tr><td colspan="2" align="center"><input type="submit" name="submit" value="Add Domain"></td></tr>
</table>
</form>
<?php
print_footer();
?>
