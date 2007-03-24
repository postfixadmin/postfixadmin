<?php
include "my_lib.php";

$sessid = check_session();

print_header();

print_menu();

print "<hr>\n";

if (!empty($_POST[submit])) {
	$username = $_POST[username];
	$password = $_POST[password];
	$password2 = $_POST[password2];
	$name = $_POST[name];

	$username = $username . "@" . $sessid[domain];
	$passwd = md5crypt ("$password");
	$maildir = $username . "/";
	
	if (empty($username) or empty($password)) {
		print "<p class=error>\n";
		print "You need to fill all fields.\n";
		print_footer();
		exit;
	}

	if (!check_email($username)) {
		print "<p class=error>\n";
		print "The email address that you have supplied at <b>Email</b> is not a valid email address, please go back.\n";
		print_footer();
		exit;
	}

	if ($password != $password2) {
		print "<p class=error>\n";
		print "The passwords that you supplied don't match!\n";
		print_footer();
		exit;
	}
	
	if (!check_string($name)) {
		print "<p class=error>\n";
		print "The name that you have supplied at <b>Name</b> is not valid, please go back.\n";
		print_footer();
		exit;
	}
		
	$result = db_query ("SELECT * FROM alias WHERE address='$username'");
	if ($result[rows] == 1) {
		print "<p class=error>\n";
		print "This email address already exists, please choose a different one.\n";
		print_footer();
		exit;
	}

	$result = db_query ("INSERT INTO alias (address,goto,domain,create_date,change_date) VALUES('$username','$username','$sessid[domain]',NOW(),NOW())");
	if ($result[rows] != 1) {
		print "<p class=error>\n";
		print "<b>Unable</b> to add: <i>$username</i> to the alias table!\n";
		print_footer();
		exit;
	}

	$result = db_query ("INSERT INTO mailbox (username,password,name,maildir,domain,create_date,change_date) VALUES('$username','$passwd','$name','$maildir','$sessid[domain]',NOW(),NOW())");
	if ($result[rows] == 1) {
		$headers = "From: $sessid[username]";
		$subject = "Welcome";
		$message = "Hi $name,\n\nWelcome to your new email account.\n\n";
		print "<i>$username</i> has been <b>added</b> to the mailbox table!\n";
		print "<p>\n";
		print "<b>NOTE:</b>\n";
		if (!mail($username, $subject, $message, $headers)) {
			print "The user needs to first receive an email in order to use the account.<br>\n";
		}
		print "User needs to login with the full email address, in this case: $username\n";
		print "<p>\n";
	} else {
		print "<p class=error>\n";
		print "<b>Unable</b> to add: <i>$username</i> to the mailbox table!\n";
		print_footer();
		exit;
	}
}
?>
Create a new local mailbox for your domain.
<p>
<form name=mailbox method=post>
<table class=form>
<tr><td>Email:</td><td><input type=text name=username></td><td>@<?php print "$sessid[domain]"; ?></td></tr>
<tr><td>Password:</td><td><input type=password name=password></td><td>Password for POP/IMAP</td></tr>
<tr><td>Password (again):</td><td><input type=password name=password2></td></tr>
<tr><td>Name:</td><td><input type=text name=name></td><td>Full name</td></tr>
<tr><td colspan=3 align=center><input type=submit name=submit value='Enter'></td></tr>
</table>
</form>
<?php
print_footer();
?>
