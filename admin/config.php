<?php
//
// If config.php is called directly, redirect to login.php
//
if (ereg("config.php", $PHP_SELF)) {
	header("Location: ../login.php");
}

// default aliases that need to be created for all domains
$default_aliases = array (
	"abuse" => "abuse@example.com",
	"postmaster" => "postmaster@localhost",
	"webmaster" => "webmaster@example.com",
);
?>
