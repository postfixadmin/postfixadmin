<?php
include "my_lib.php";

$sessid = check_session();

$check_id = "domain";
$table = $_GET[table];
$where = $_GET[where];
$delete = $_GET[delete];
$url = "main.php?" . session_name() . "=" . session_id();

if ($table == "alias") {
	$query = "DELETE FROM alias WHERE address='$delete' AND domain='$sessid[$check_id]'";
	$result = db_query ("$query");
	if ($result[rows] == 1) {
		header("Location: $url");
	} else {
		print_header();
		print "<hr>\n";
		print "<p class=error>\n";
		print "<b>Unable</b> to delete entry $delete from the $table table!\n";
		print_footer();
	}
}

if ($table == "mailbox") {
	$query = "DELETE FROM mailbox WHERE username='$delete' AND domain='$sessid[$check_id]'";
	$result = db_query ("$query");
	if ($result[rows] != 1) {
		print_header();
		print "<hr>\n";
		print "<p class=error>\n";
		print "<b>Unable</b> to delete entry $delete from the $table table!\n";
		print_footer();
	}

	$query = "DELETE FROM alias WHERE address='$delete' AND domain='$sessid[$check_id]'";
	$result = db_query ("$query");
	if ($result[rows] == 1) {
		header("Location: $url");
	} else {
		print_header();
		print "<hr>\n";
		print "<p class=error>\n";
		print "<b>Unable</b> to delete entry $delete from the $table table!\n";
		print_footer();
	}

}
?>
