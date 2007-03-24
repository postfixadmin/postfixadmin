<?php
require "../config.inc.php";
require "../my_lib.php";

$modify = $_GET[modify];

if (!empty($_POST[submit])) {
	$domain = $_POST[domain];
	$description = $_POST[description];
	$aliases = $_POST[aliases];

	if (empty($domain) or empty($description)) print_error("You will need to fill all fields.", "YES", "ADMIN");

	if (check_email($domain)) print_error("The domain that you have supplied is not a valid domain, please go back.", "YES", "ADMIN");

	$result = db_query ("UPDATE domain SET description='$description', change_date=NOW() WHERE domain='$domain'");
	if ($result[rows] == 1) {
		header("Location: domainview.php");
	} else {
		print_error("Unable to modify: <i>$domain</i> to the domain table!", "YES", "ADMIN");
	}
}
print_header("admin");
?>
Create a new domain.
<p />
<form method="post">
<table class="form">
<tr><td>Domain:</td><td><input type="hidden" name="domain" value="<?php print "$modify"; ?>"><?php print "$modify"; ?></td></tr>
<tr><td>Description:</td><td><input type="text" name="description"></td></tr>
<tr><td colspan="2" align="center"><input type="submit" name="submit" value="Modify Domain"></td></tr>
</table>
</form>
<?php
print_footer();
?>
