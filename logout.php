<?php
//
// logout.php
//
include "my_lib.php";

$sessid = check_session();

session_unset();

session_destroy();

print_header();

print "<h1>Mail Admin</h1>\n";
print "<hr>\n";
print "You are logged out\n";
print "<p>\n";
print "<a href=login.php>Login again</a>\n";
print_footer();
?>
