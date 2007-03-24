<?php
require "config.inc.php";
require "my_lib.php";

print_header("", "", "YES");

if (!empty($_POST['submit']) or !empty($_POST['cancel'])) {
        $form_login = $_POST['form_login'];
        $form_passwd = $_POST['form_passwd'];
	$form_subject = $_POST['form_subject'];
	$form_body = $_POST['form_body'];
	
	$result = db_query("SELECT * FROM mailbox WHERE username='$form_login' AND active='1'");
	if ($result['rows'] != 1) print_error("The mailbox <b>$form_login</b> does not exist!", "", "", "YES");

	$result = db_query("SELECT password FROM mailbox WHERE username='$form_login'");
	if ($result['rows'] == 1) {
		$row = mysql_fetch_array($result['result']);
		$db_passwd = $row['password'];
		$keys = preg_split('/\$/', $row['password']);
		$checked_passwd = md5crypt($form_passwd, $keys[2]);

		$result = db_query("SELECT * FROM mailbox WHERE username='$form_login' AND password='$checked_passwd' AND active='1'");
		if ($result['rows'] != 1) print_error("The password that you have entered is not correct!", "", "", "YES");
	}

	$result = db_query("SELECT email FROM vacation WHERE email='$form_login'");
	if ($result['rows'] == 0 and !empty($_POST['cancel'])) print_error("Unable to cancel your \"Out of the Office\" message! (vacation)", "", "", "YES");
	if ($result['rows'] == 1 and empty($_POST['cancel'])) print_error("There is already an \"Out of the Office\" message present! (vacation)", "", "", "YES");
	
	$result = db_query("SELECT goto FROM alias WHERE address='$form_login'");
	if ($result['rows'] == 1) {
		$row = mysql_fetch_array($result['result']);
		if (!empty($_POST['cancel'])) {
			$db_goto = preg_replace("/,$vacation_email/", "", $row['goto']);
		} else {
			$db_goto = $row['goto'] . ",$vacation_email";
		}
	} else {
		if ($result['rows'] != 1) print_error("Unable collect your data! (alias)", "", "", "YES");
	}

	$result = db_query("UPDATE alias SET goto='$db_goto', change_date=NOW() WHERE address='$form_login'");
	if ($result['rows'] != 1) print_error("Unable to create your \"Out of the Office\" message! (alias)", "", "", "YES");

	if (!empty($_POST['cancel'])) $result = db_query("DELETE FROM vacation WHERE email='$form_login'");
	if (!empty($_POST['submit'])) $result = db_query("INSERT INTO vacation (email,subject,body) VALUES('$form_login', '$form_subject', '$form_body')");
	if ($result['rows'] == 1) {
		print "<hr />\n";
		if (!empty($_POST['cancel'])) print "Your \"Out of the Office\" message is removed!\n";
		if (!empty($_POST['submit'])) print "Your \"Out of the Office\" message is active!\n";
		print_footer();
		exit;
	} else {
		print_error("Unable create your \"Out of the Office\" message! (vacation)", "", "", "YES");
	}
} 
?>
Out of the Office.
<p>
<form name="vacation" method="post">
<table class="form">
<tr><td>Email:</td><td><input type="text" name="form_login"></td></tr>
<tr><td>Password:</td><td><input type="password" name="form_passwd"></td></tr>
<tr><td>Subject:</td><td><input type="text" name="form_subject" value="Out of the Office" size="50"></td></tr>
<tr><td valign="top">Body:</td><td><textarea rows="10" cols="80" name="form_body">
I will be away from <date> until <date>.
For urgent matters you can contact <contact person>.
</textarea></td></tr>
<tr><td colspan="2" align="center">
<input type="submit" name="submit" value="Going Away">
<input type="submit" name="cancel" value="Coming Back">
</td></tr>
</table>
</form>
<?php
print_footer("NO");
?>
