<?php
//
// File: logout.php
//
// Template File: -none-
//
// Template Variables:
//
// -none-
//
// Form POST \ GET Variables:
//
// -none-
//
require ("../config.inc.php");
require ("../functions.inc.php");

$USERID_USERNAME = check_user_session ();

session_unset ();
session_destroy ();

header ("Location: login.php");
exit;
?>
