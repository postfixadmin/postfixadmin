<?php
include "my_lib.php";

print_header();

print_menu();
print "<hr>\n";

$username = $_GET[username];

if (!empty($_POST[submit])) {
        $form_new_passwd1 = $_POST[form_new_passwd1];

	if (empty($form_new_passwd1)) {
		print "<p class=error>\n";
		print "You will need to fill in the password field!\n";
		print_footer();
		exit;
	}

	$new_passwd = md5crypt($form_new_passwd1);
	$result = db_query ("UPDATE admin SET password='$new_passwd',change_date=NOW() WHERE username='$username'");
	if ($result[rows] == 1) {
		print "The password has been updated!\n";
		print "<p>\n";
		print_footer();
		exit;
	} else {
		print "<p class=error>\n";
		print "<b>Unable</b> to update your password!\n";
		print_footer();
		exit;
	}
} 

?>
Change admin password.
<p>
<form name=passwd method=post>
<table class=form>
<tr><td>Login:</td><td><?php print "$username"; ?></td></tr>
<tr><td>New Password:</td><td><input type=text name=form_new_passwd1></td></tr>
<tr><td colspan=2 align=center><input type=submit name=submit value='Enter'></td></tr>
</table>
</form>
<?php
print_footer();
?>
