<?php
require "config.inc.php";
require "my_lib.php";

$sessid = check_session();
$sessid_domain = $sessid['domain'];
$sessid_username = $sessid['username'];

$url = "main.php?" . session_name() . "=" . session_id();
$modify = $_GET['modify'];

if (!empty($_POST['submit'])) {
        $goto = $_POST['goto'];

	if (empty($goto)) print_error("You didn't enter anything at <b>To:</b>.", "YES", "MENU");

	$goto = preg_replace('/\r\n/', ',', $goto);
	$goto = preg_replace('/\,*$/', '', $goto);
	$array = preg_split('/,/', $goto);
	for ($i = 0; $i < sizeof($array); $i++) {
		if (in_array("$array[$i]", $default_aliases)) continue;
		if (empty($array[$i])) continue;
		if (!check_email($array[$i])) print_error("The email address <b>$array[$i]</b> is not a valid email address, please go back.", "YES", "MENU");
	}
	$result = db_query("UPDATE alias SET goto='$goto', change_date=NOW() WHERE address='$modify' AND domain='$sessid_domain'");
	if ($result['rows'] == 1) {
		header("Location: $url");
	} else {
		print_error("Unable to update: <i>$address</i> -> <i>$goto</i> in the alias table!", "YES", "MENU");
	}
}

$query = "SELECT * FROM alias WHERE address='$modify' AND domain='$sessid_domain'";
$result = db_query("$query");
if ($result['rows'] == 1) {
	$row = mysql_fetch_array ($result['result']);
} else {
	print_error("Unable to find the alias!","YES", "MENU");
}
print_header("menu");
?>
Change an alias for your domain.
<p>
<form name="modify" method="post">
<table class="form">
<tr><td>Alias:</td><td><?php print "$modify"; ?></td></tr>
<tr><td colspan="2">&nbsp;</td></tr>
<tr><td colspan="2" align="center"><b>Enter your email aliases below. One per line!</b></td></tr>
<tr><td valign="top">To:</td><td><textarea rows="24" cols="80" name="goto">
<?php
$array = preg_split('/,/', $row[goto]);
for ($i = 0 ; $i < sizeof($array) ; $i++) {
  if (empty($array[$i])) continue;
  print "$array[$i]\n";
}
?>
</textarea></td></tr>
<tr><td colspan="2" align="center"><input type="submit" name="submit" value="Modify Alias"></td></tr>
</table>
</form>
<?php
print_footer();
?>
