<?
//
// login.php
//
include "my_lib.php";

if (!empty($_POST[submit])) {
	$form_login = $_POST[form_login];
	$form_passwd = $_POST[form_passwd];

	$result = db_query ("SELECT password FROM admin WHERE username='$form_login'");
	
	if ($result[rows] == 1) {
		$row = mysql_fetch_array($result[result]);
		$db_passwd = $row[password];
		$salt = preg_split('/\$/', $row[password]);
		$checked_passwd = md5crypt($form_passwd, $salt[2]);

		$result = db_query ("SELECT * FROM admin WHERE username='$form_login' AND password='$checked_passwd' AND active='1'");

		if ($result[rows] == 1) {
			session_name("SessID");
			session_start();
			session_register("sessid");

			$row = mysql_fetch_array($result[result]);

			$sessid = array (
				"domain" => $row[domain],
				"username" => $row[username]
			);

		} else {
			print_header();
			print "<h1>Mail Admin</h1>\n";
			print "<hr>\n";
			print "<p class=error>\n";
			print "Either the password that you supplied is incorrect, go back and try again.<p>\n";
			print "Or you are not authorized to view this page.\n";
			print_footer();
			exit;
		}

	} else {
		print_header();
		print "<h1>Mail Admin</h1>\n";
		print "<hr>\n";
		print "<p class=error>\n";
		print "The login that you supplied is not correct, please press BACK and try again.\n";
		print_footer();
		exit;
	}

	header("Location: main.php?" .  session_name() . "=" . session_id());
} 
print_header("Welcome to Mail Admin");
?>
<h1>Welcome to Mail Admin</h1>
<hr>
<form name=login method=post>
<table class=form>
<tr><td>Login:</td><td><input type=text name=form_login></td><td>(email address)</td></tr>
<tr><td>Password:</td><td><input type=password name=form_passwd></td></tr>
<tr><td colspan=3 align=center><input type=submit name=submit value=Enter></td></tr>
</table>
</form>
<p>
<a href=vcp.php>Mailbox Password Change</a>
<?
print_footer();
?>
