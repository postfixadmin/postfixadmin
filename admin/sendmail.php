<?php
require "../config.inc.php";
require "../my_lib.php";

print_header("admin");

if (!empty($_POST[submit])) {
	$to = $_POST[to];

	if (empty($to)) print_error("You need to select an email address.");

	$headers = "From: $admin_email";
	$subject = "Welcome";
	$message = "Hi,\n\nWelcome to your new email account.\n\n";
	if (!mail($to, $subject, $message, $headers)) {
		print_error("Unable to send message to $to!");
	}
	print "Successfully send email to $to\n";
	print "<p>\n";
}
?>
Send test message to a new mailbox.
<p>
<form name="mailbox" method="post">
<table class="form">
<tr><td>From:</td><td><?php print "$admin_email"; ?></td></tr>
<tr><td>To:</td><td>
<select name="to">
<?php
$result = db_query("SELECT username FROM mailbox ORDER BY domain");
while ($row = mysql_fetch_array ($result[result])) {
	print "<option>$row[username]</option>";
}
?>
</select>
<tr><td colspan="2" align="center"><input type="submit" name="submit" value="Send Message"></td></tr>
</table>
</form>
<?php
print_footer();
?>
