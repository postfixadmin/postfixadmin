<?php
include "my_lib.php";

$sessid = check_session();

$username = $_GET[username];

print_header();

print_menu();

print "<hr>\n";

if (!empty($_POST[submit])) {
        $form_new_passwd1 = $_POST[form_new_passwd1];
        $form_new_passwd2 = $_POST[form_new_passwd2];

	if (empty($form_new_passwd1) or empty($form_new_passwd2)) {
		print "<p class=error>\n";
		print "You will need to fill all fields!\n";
		print_footer();
		exit;
	}

	if ($form_new_passwd1 != $form_new_passwd2) {
		print "<p class=error>\n";
		print "The new passwords that you supplied don't match!\n";
		print_footer();
		exit;
	}
	
	$new_passwd = md5crypt($form_new_passwd1);
	$result = db_query ("UPDATE mailbox SET password='$new_passwd',change_date=NOW() WHERE username='$username' AND domain='$sessid[domain]'");
	if ($result[rows] == 1) {
		print "The password has been updated!\n";
		print "<p>\n";
		print "<a href=main.php?" . session_name() . "=" . session_id() . ">Go Back</a>\n";
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
Change password.
<p>
<form name=passwd method=post>
<table class=form>
<tr><td>Login:</td><td><?php print "$username"; ?></td></tr>
<tr><td>New Password:</td><td><input type=password name=form_new_passwd1></td></tr>
<tr><td>New Password (again):</td><td><input type=password name=form_new_passwd2></td></tr>
<tr><td colspan=2 align=center><input type=submit name=submit value='Enter'></td></tr>
</table>
</form>
<?php
print_footer();
?>
