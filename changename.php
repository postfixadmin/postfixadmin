<?php
require "config.inc.php";
require "my_lib.php";

$sessid = check_session();

$url = "main.php?" . session_name() . "=" . session_id();
$username = $_GET[username];
$name = $_GET[name];

if (!empty($_POST[submit])) {
	$form_new_name = $_POST[form_new_name];

	if (empty($form_new_name))  print_error("You will need to enter a name!", "YES", "MENU");

	$result = db_query("UPDATE mailbox SET name='$form_new_name',change_date=NOW() WHERE username='$username' AND domain='$sessid[domain]'");
	if ($result[rows] == 1) {
		header("Location: $url");
	} else {
		print_error("Unable to update your name!", "YES", "MENU");
	}
} 

print_header("menu");
?>
Change password.
<p>
<form name="changename" method="post">
<table class="form">
<tr><td>Login:</td><td><?php print "$username"; ?></td></tr>
<tr><td>Name:</td><td><input type="text" name="form_new_name" value="<?php print "$name"; ?>"></td></tr>
<tr><td colspan="2" align="center"><input type="submit" name="submit" value="Enter"></td></tr>
</table>
</form>
<?php
print_footer();
?>
