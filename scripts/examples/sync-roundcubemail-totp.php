#!/bin/env php
<?php

// Get positional arguments
if ($argc !== 3) {
    fwrite(STDERR, "Usage: {$argv[0]} <username> <domain>\n");
    exit(1);
}

$USERNAME = $argv[1];
$DOMAIN = $argv[2];

// Get TOTP shared secret from stdin
$SHARED_SECRET = trim(fgets(STDIN));

// Include database configuration
include_once "/etc/postfixadmin/rcm-totp-sync.php";

// connect to Roundcubemail database and update user preferences with TOTP secret
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli = new mysqli($CONFIG["host"], $CONFIG["user"], $CONFIG["password"], $CONFIG["database"]);

$stmt = $mysqli->prepare("SELECT user_id, preferences FROM users WHERE username=?");
$stmt->bind_param("s", $USERNAME);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    echo "Updating TOTP secret for $USERNAME\n";
    $row = $result->fetch_assoc();
    $preferences = unserialize($row['preferences'], ['allowed_classes' => false]);
    if (!is_array($preferences)) {
        $preferences = [];
    }
    $preferences['twofactor_gauthenticator']['secret'] = $SHARED_SECRET;
    $serialized_preferences = serialize($preferences);
    $user_id = (int)$row['user_id'];
    $stmt_update = $mysqli->prepare("UPDATE users SET preferences=? WHERE user_id=?");
    $stmt_update->bind_param("si", $serialized_preferences, $user_id);
    $stmt_update->execute();
} else {
    echo "Could not find user $USERNAME in Roundcubemail.\n";
}
$mysqli->close();
