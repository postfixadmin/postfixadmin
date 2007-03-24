<?php
require "config.inc.php";
require "my_lib.php";

$sessid = check_session();

$url = "main.php?" . session_name() . "=" . session_id();
$username = $_GET['username'];

if (!empty($_POST['submit'])) {
        $form_new_passwd1 = $_POST['form_new_passwd1'];
        $form_new_passwd2 = $_POST['form_new_passwd2'];

	if (empty($form_new_passwd1) or empty($form_new_passwd2)) print_error("You will need to fill both password fields!", "YES", "MENU");

	if ($form_new_passwd1 != $form_new_passwd2) print_error("The new passwords that you supplied don't match!", "YES", "MENU");
	
	$new_passwd = md5crypt($form_new_passwd1);

	$result = db_query("UPDATE mailbox SET password='$new_passwd',change_date=NOW() WHERE username='$username' AND domain='$sessid[domain]'");
	if ($result['rows'] == 1) {
		header("Location: $url");
	} else {
		print_error("Unable to update your password!", "YES", "MENU");
	}
}

print_header("menu");
?>
Change password.
<p>
<form name="passwd" method="post">
<table class="form">
<tr><td>Login:</td><td><?php print "$username"; ?></td></tr>
<tr><td>New Password:</td><td><input type="password" name="form_new_passwd1"></td></tr>
<tr><td>New Password (again):</td><td><input type="password" name="form_new_passwd2"></td></tr>
<tr><td colspan="2" align="center"><input type="submit" name="submit" value="Enter"></td></tr>
</table>
</form>
<?php
print_footer();
?>
