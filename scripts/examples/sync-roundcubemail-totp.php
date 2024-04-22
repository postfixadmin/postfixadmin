#!/bin/env php
<?php

// Get positional arguments
$USERNAME = $argv[1];
$DOMAIN = $argv[2];

// Get TOTP shared secret from stdin
$SHARED_SECRET = trim(fgets(STDIN));

// Include database configuration
include_once "/etc/postfixadmin/rcm-totp-sync.php";

// connect to Roundcubemail database and update user preferences with TOTP secret
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli = new mysqli($CONFIG["host"], $CONFIG["user"], $CONFIG["password"], $CONFIG["database"]);

$stmt = $mysqli->prepare("SELECT preferences FROM users WHERE username=?");
$stmt->bind_param("s", $USERNAME);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    echo "Updating TOTP secret for $USERNAME\n";
    $row = $result->fetch_assoc();
    $preferences = unserialize($row['preferences']);
    $preferences['twofactor_gauthenticator']['secret'] = $SHARED_SECRET;
    $stmt_update = $mysqli->prepare("UPDATE users SET preferences=?");
    $stmt_update->bind_param("s", serialize($preferences));
    $stmt_update->execute();
} else {
    echo "Could not find user $USERNAME in Roundcubemail.\n";
}
$mysqli->close();
