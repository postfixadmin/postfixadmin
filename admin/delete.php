<?php
require "../config.inc.php";
require "../my_lib.php";

$table = $_GET['table'];
$where = $_GET['where'];
$delete = $_GET['delete'];
$url = "$table" . "view.php";

if ($table == "domain") {
	$r_domain = db_delete("domain",$where,$delete);
	$r_admin = db_delete("admin",$where,$delete);
	$r_alias = db_delete("alias",$where,$delete);
	$r_mailbox = db_delete("mailbox",$where,$delete);
	if (($r_domain == 1) and ($r_admin >= 0) and ($r_alias >= 0) and ($r_mailbox >= 0)) {
		header("Location: $url");
	} else {
		print_header();
		print "<hr />\n";
		print "<b>Unable</b> to delete all entries for complete domain deletion!<p>\n";
		print "Domain delete: $r_domain<br>\n";
		print "Admin delete: $r_admin<br>\n";
		print "Alias delete: $r_alias<br>\n";
		print "Mailbox delete: $r_mailbox<br>\n";
		print "<p />\n";
		print_footer();
	}
} else {
	$result = db_delete ($table,$where,$delete);
	if ($result == 1) {
		header("Location: $url");
	} else {
		print_header();
		print "<hr />\n";
		print "<b>Unable</b> to delete entry $delete from the $table table!\n";
		print "<p />\n";
		print_footer();
	}
}

function db_delete ($table,$where,$delete) {
	$result = db_query("DELETE FROM $table WHERE $where='$delete'");
	if ($result['rows'] >= 1) {
		return $result['rows'];
	} else {
		return true;
	}
}
?>
