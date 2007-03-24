<?php
include "my_lib.php";

print_header();

print "<h1>Mail Admin</h1>\n";
print "<hr>\n";

if (!empty($_POST[submit])) {
        $form_login = $_POST[form_login];
        $form_passwd = $_POST[form_passwd];
        $form_new_passwd1 = $_POST[form_new_passwd1];
        $form_new_passwd2 = $_POST[form_new_passwd2];
	
	if (empty($form_login) or empty($form_passwd) or empty($form_new_passwd1) or empty($form_new_passwd2)) {
		print "<p class=error>\n";
		print "You didn't enter all fields!\n";
		print_footer();
		exit;
	}

	if ($form_new_passwd1 != $form_new_passwd2) {
		print "<p class=error>\n";
		print "The passwords that you supplied don't match!\n";
		print_footer();
		exit;
	}

	$result = db_query ("SELECT * FROM mailbox WHERE username='$form_login' AND active='1'");

	if ($result[rows] != 1) {
		print "<p class=error>\n";
		print "The mailbox does not exist!\n";
		print_footer();
		exit;
	}

	$result = db_query ("SELECT password FROM mailbox WHERE username='$form_login'");
	
	if ($result[rows] == 1) {
		$row = mysql_fetch_array($result[result]);
		$db_passwd = $row[password];
		$keys = preg_split('/\$/', $row[password]);
		$checked_passwd = md5crypt($form_passwd, $keys[2]);

		$result = db_query ("SELECT * FROM mailbox WHERE username='$form_login' AND password='$checked_passwd' AND active='1'");

		if ($result[rows] != 1) {
			print "<p class=error>\n";
			print "The password that you have entered doesn't match your current password!\n";
			print_footer();
			exit;
		}
	}

	$new_passwd = md5crypt($form_new_passwd1);
	
	$result = db_query ("UPDATE mailbox SET password='$new_passwd',change_date=NOW() WHERE username='$form_login'");
	
	if ($result[rows] == 1) {
		print "Your password has been updated!\n";
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
Change your mailbox password.
<p>
<form name=vcp method=post>
<table class=form>
<tr><td>Email:</td><td><input type=text name=form_login></td></tr>
<tr><td>Current Password:</td><td><input type=password name=form_passwd></td></tr>
<tr><td>New Password:</td><td><input type=password name=form_new_passwd1></td></tr>
<tr><td>New Password (again):</td><td><input type=password name=form_new_passwd2></td></tr>
<tr><td colspan=2 align=center><input type=submit name=submit value='Enter'></td></tr>
</table>
</form>
<?php
print_footer();
?>
