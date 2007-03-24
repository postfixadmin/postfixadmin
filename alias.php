<?php
include "my_lib.php";

$sessid = check_session();

print_header();

print_menu();

print "<hr>\n";

if (!empty($_POST[submit])) {
        $address = $_POST[address];
        $goto = $_POST[goto];

	$address_value = $address;
	$address = $address . "@" . $sessid[domain];
	
	if (empty($address) or empty($goto)) {
		print "<p class=error>\n";
		print "You will need to fill both fields.\n";
		print_footer();
		exit;
	}

	if (!check_email($address)) {
		print "<p class=error>\n";
		print "The email address that you have supplied at <b>Alias</b> is not a valid email address, please go back.\n";
		print_footer();
		exit;
	}

	if (!check_email($goto)) {
		print "<p class=error>\n";
		print "The email address that you have supplied at <b>To</b> is not a valid email address, please go back.\n";
		print_footer();
		exit;
	}
	
	if ($address_value == "none") {
		$address = "@" . $sessid[domain];
	}

	$result = db_query ("SELECT * FROM alias WHERE address='$address'");
	if ($result[rows] == 1) {
		print "<p class=error>\n";
		print "This email address already exists, please choose a different one.\n";
		print_footer();
		exit;
	}

	$result = db_query ("INSERT INTO alias (address,goto,domain,create_date,change_date) VALUES('$address','$goto','$sessid[domain]',NOW(),NOW())");
	if ($result[rows] == 1) {
		print "<p>\n";
		print "<i>$address</i> -> <i>$goto</i> has been <b>added</b> to the alias table!\n";
		print "<p>\n";
	} else {
		print "<p class=error>\n";
		print "<b>Unable</b> to add: <i>$address</i> -> <i>$goto</i> to the alias table!\n";
		print_footer();
		exit;
	}
}
?>
Create a new alias for your domain.
<p>
<form name=alias method=post>
<table class=form>
<tr><td>Alias:</td><td><input type=text name=address></td><td>@<?php print "$sessid[domain]" ?></td></tr>
<tr><td>To:</td><td><input type=text name=goto></td><td>Where the mail needs to be send to.<br>Use "edit" in the overview to add more<br>then one email address.</td></tr>
<tr><td colspan=3 align=center><input type=submit name=submit value='Enter'></td></tr>
</table>
</form>
If you want to add a catchall enter "none" in the alias field.
<?php
print_footer();
?>
