<?
include "my_lib.php";

$sessid = check_session();

$url = "main.php?" . session_name() . "=" . session_id();
$modify = $_GET[modify];

if (!empty($_POST[submit])) {
        $goto = $_POST[goto];

	if (empty($goto)) {
		print_header();
		print_menu();
		print "<hr>\n";
		print "<p class=error>\n";
		print "You didn't enter anything at <b>To:</b>.\n";
		print_footer();
		exit;
	}

	$array = preg_split('/,/', $goto);
	for ($i = 0 ; $i < sizeof($array) ; $i++) {
		if (!check_email($array[$i])) {
			print_header();
			print_menu();
			print "<hr>\n";
			print "<p class=error>\n";
			print "The email address <b>$array[$i]</b> is not a valid email address, please go back.\n";
			print_footer();
			exit;
		}
	}

	$result = db_query ("UPDATE alias SET goto='$goto', change_date=NOW() WHERE address='$modify' AND domain='$sessid[domain]'");
	if ($result[rows] == 1) {
		header("Location: $url");
	} else {
		print_header();
		print_menu();
		print "<hr>\n";
		print "<p class=error>\n";
		print "<b>Unable</b> to update: <i>$address</i> -> <i>$goto</i> in the alias table!\n";
		print_footer();
		exit;
	}
}

$query = "SELECT * FROM alias WHERE address='$modify' AND domain='$sessid[domain]'";
$result = db_query ("$query");
if ($result[rows] == 1) {
	$row = mysql_fetch_array ($result[result]);
} else {
	print_header();
	print_menu();
	print "<hr>\n";
	print "<p class=error>\n";
	print "Unable to find the alias!\n";
	print_footer();
	exit;
}
print_header();
print_menu();
print "<hr>\n";
?>
Change an alias for your domain.
<p>
<form name=modify method=post>
<table class=form>
<tr><td>Alias:</td><td><? print "$modify"; ?></td></tr>
<tr><td>To:</td><td><input type=text size=80 maxlength=1024 value=<? print "$row[goto]"; ?> name=goto></td></tr>
<tr><td colspan=2 align=center><input type=submit name=submit value='Enter'></td></tr>
</table>
</form>
<?
print_footer();
?>
