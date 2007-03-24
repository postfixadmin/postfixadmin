<?php
//
// login.php
//
require "config.inc.php";
require "my_lib.php";

if (!empty($_POST['submit'])) {
	$form_login = $_POST['form_login'];
	$form_passwd = $_POST['form_passwd'];

	$result = db_query("SELECT password FROM admin WHERE username='$form_login'");
	if ($result['rows'] == 1) {
		$row = mysql_fetch_array($result['result']);
		$db_passwd = $row[password];
		$salt = preg_split('/\$/', $row[password]);
		$checked_passwd = md5crypt($form_passwd, $salt[2]);

		$result = db_query ("SELECT * FROM admin WHERE username='$form_login' AND password='$checked_passwd' AND active='1'");
		if ($result['rows'] == 1) {
			session_name("SessID");
			session_start();
			session_register("sessid");

			$row = mysql_fetch_array($result['result']);

			$_SESSION['sessid'] = array (
				"domain" => $row[domain],
				"username" => $row[username]
			);
		} else {
			print_header();
			print "<h1>Mail Admin</h1>\n";
			print "<hr>\n";
			print_error ("Either the password that you supplied is incorrect, or you are not authorized to view this page.<br />Go back and try again.\n");
		}
	} else {
		print_header();
		print "<h1>Mail Admin</h1>\n";
		print "<hr>\n";
		print_error ("The login that you supplied is not correct, please press BACK and try again.");
	}
	header("Location: main.php?" .  session_name() . "=" . session_id());
} 
print_header("", $welcome_title, "YES");
?>
<center>
<table width="10%" border="0" cellspacing="0" cellpadding="0" height="100">
<tr bgcolor="#999999"><td colspan="3" height="1">
</tr>
<tr>
<td bgcolor="#999999" width="1">
</td>
<td bgcolor="#EEEEEE" valign="top">
<table border="0" cellspacing="0" cellpadding="6">
</td>
<form name="login" method="post">
<td colspan="2" align="center">  
<br />
<b>Mail admins login here to administrate your domain.</b>
<br />
<br />      
<input type="text" name="form_login" style="width:149px" value="mailadmin@domain.com" size="12" onFocus="if (this.value=='mailadmin@domain.com') this.value='';" onBlur= "if (this.value=='') his.value='mailadmin@domain.com';" />
<br />
<input type="password" name="form_passwd" style="width:149px" value="password" size="12" onFocus="if (this.value=='password') this.value='';" onBlur="if (this.value=='') his.value='password';" />
<br />
<input type="submit" name="submit" value="Login" />
<br />
</td>
</form>
</tr>
</table>
<p />
<a href="vcp.php">Users click here to change your email password.</a>
</td>
<td bgcolor="#999999" width="1">
</td>
</tr>
<tr bgcolor="#999999"><td colspan="3" height="1">
</tr>
</table>
<?php
print_footer("NO");
?>
