<?php
/**
 * OIDC callback handler
 * Receives authorization code from Keycloak, validates token, logs user in
 */

require_once('common.php');

$CONF = Config::getInstance()->getAll();

if (($CONF['auth_provider'] ?? 'local') !== 'oidc') {
    header('Location: login.php');
    exit;
}

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if (empty($code) || empty($state)) {
    flash_error('OIDC authentication failed: missing parameters');
    header('Location: login.php');
    exit;
}

$oidc = new OIDC();
if (!$oidc->isConfigured()) {
    flash_error('OIDC not configured');
    header('Location: login.php');
    exit;
}

$claims = $oidc->handleCallback($code, $state);

if ($claims === false) {
    flash_error('OIDC authentication failed');
    header('Location: login.php');
    exit;
}

// Extract email from claims
$email = $claims['email'] ?? '';

if (empty($email)) {
    flash_error('OIDC authentication failed: no email in token');
    header('Location: login.php');
    exit;
}

// Look up admin user by email
try {
    $adminHandler = new AdminHandler();
    $adminHandler->init($email);
} catch (\Exception $e) {
    flash_error('Failed to look up admin account.');
    header('Location: login.php');
    exit;
}

$username = '';
$isSuperadmin = false;

if (!$adminHandler->view()) {
    // Admin user not found - auto-provision if enabled
    if (!($CONF['oidc_auto_provision'] ?? true)) {
        flash_error('You are not authorized to access this system. Contact an administrator.');
        header('Location: login.php');
        exit;
    }

    // Auto-provision new admin user
    // Use upstream pattern: check then insert (database-agnostic)
    $randomPassword = generate_password();
    $hashedPassword = pacrypt($randomPassword);

    $table_admin = table_by_key('admin');
    $existing = db_query_one("SELECT username FROM $table_admin WHERE username = ?", [$email]);
    if (empty($existing)) {
        db_insert($table_admin, [
            'username' => $email,
            'password' => $hashedPassword,
            'active' => db_get_boolean(true),
        ], ['created', 'modified']);
    }

    $username = $email;
    $isSuperadmin = false;
} else {
    $adminProperties = $adminHandler->result();
    $username = $adminProperties['username'];
    $isSuperadmin = ($adminProperties['superadmin'] ?? 0) == 1;
}

// Check if user is active
$table_admin = table_by_key('admin');
$adminRecord = db_query_one("SELECT active FROM $table_admin WHERE username = ?", [$username]);
if (!$adminRecord || !db_get_boolean($adminRecord['active'])) {
    flash_error('Your account is disabled. Contact an administrator.');
    header('Location: login.php');
    exit;
}

// Initialize session
init_session($username, true, true);

if ($isSuperadmin) {
    $_SESSION['sessid']['roles'][] = 'global-admin';
}

// Redirect to main page
header('Location: main.php');
exit;
