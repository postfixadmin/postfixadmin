<?
include "my_lib.php";

$url = "$table" . "view.php";
$table = $_GET[table];
$where = $_GET[where];
$delete = $_GET[delete];

$query = "DELETE FROM $table WHERE $where='$delete'";
$result = db_query ("$query");
if ($result[rows] == 1) {
	header("Location: $url");
} else {
	print_header();
	print "<b>Unable</b> to delete entry $delete from the $table table!\n";
	print "<p>\n";
	print_footer();
}
?>
