<?php
require "../config.inc.php";
require "../my_lib.php";

print_header("admin");

if (!empty($_POST['submit'])) {
	$username = $_POST['username'];
	$password = $_POST['password'];
	$domain = $_POST['domain'];

	$passwd = md5crypt("$password");
	
	if (empty($username) or empty($password) or empty($domain)) print_error("You will need to fill all fields.");

	if (!check_email($username)) print_error("The email address that you have supplied at <b>Email</b> is not a valid email address, please go back.");

	$result = db_query ("SELECT * FROM domain WHERE domain='$domain'");
	if ($result[rows] != 1) print_error("The domain <b>$domain</b> is not present in the domain table!");

	$result = db_query ("SELECT * FROM admin WHERE username='$username'");
	if ($result[rows] == 1) print_error("This email address already exists, please choose a different one.");

	$result = db_query ("INSERT INTO admin (username,password,domain,create_date,change_date) VALUES('$username','$passwd','$domain',NOW(),NOW())");
	if ($result[rows] == 1) {
		print "<i>$username</i> has been <b>added</b> to the admin table!\n";
		print "<p>\n";
	} else {
		print_error("Unable to add <i>$username</i> to the mailbox table!");
	}
}
?>
Create a new admin for a domain.
<p />
<form method="post">
<table class="form">
<tr><td>Email:</td><td><input type="text" name="username"></td></tr>
<tr><td>Passwd:</td><td><input type="text" name="password"></td></tr>
<tr><td>Domain:</td><td>
<select name="domain">
<?php
$result = db_query("SELECT domain FROM domain ORDER BY domain");
while ($row = mysql_fetch_array ($result['result'])) {
	print "<option>$row[domain]</option>";
}
?>
</select>
</td></tr>
<!--
<tr><td>Add mail aliases:</td><td><input type="checkbox" name="admin_aliases"> (for domain admin)</td></tr>
<tr><td>Add mail aliases:</td><td><input type="checkbox" name="uber_aliases"> (for uber admin)</td></tr>
-->
<tr><td colspan="2" align="center"><input type="submit" name="submit" value="Add Admin"></td></tr>
</table>
</form>
<?php
print_footer();
?>
