<?php
//
// If config.inc.php is called directly, redirect to login.php
//
if (ereg("config.inc.php", $PHP_SELF)) {
	header("Location: login.php");
}

// Login information for the database
$db_host = "localhost";
$db_name = "postfix";
$db_user = "postfixadmin";
$db_pass = "postfixadmin";

// Default aliases that need to be created for all domains
$default_aliases = array (
	"abuse" => "abuse@example.com",
	"postmaster" => "postmaster@example.com",
	"webmaster" => "webmaster@example.com",
);

// Specify the table where you have your quotas, leave empty if you don't
// enforce quotas. For example a 2MB mailbox quota:
// $quota_table = "quota";
// $default_quota = "2000000";
$quota_table = "";
$default_quota = "";

// Back to main website information
$organization_name = "domain.com";
$organization_link = "http://domain.com";

// Admin email address
$admin_email = "admin@domain.com";

// Show Postfix Admin information
$show_postfix_admin_info = "YES";

// Title used for all pages except login.php
$title = "Mail Admin";

// Header used for login.php
$welcome_header = ":: Welcome to Mail Admin ::";

// Title used for login.php
$welcome_title = ":: Welcome to Mail Admin ::";
?>
