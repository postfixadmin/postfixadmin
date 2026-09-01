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
$adminHandler = new AdminHandler();
$adminHandler->init($email);

$username = '';
$isSuperadmin = false;

if (!$adminHandler->view()) {
    // Admin user not found - auto-provision if enabled
    if (!($CONF['oidc_auto_provision'] ?? true)) {
        flash_error('You are not authorized to access this system. Contact an administrator.');
        header('Location: login.php');
        exit;
    }

    // Auto-provision new admin user using AdminHandler
    $randomPassword = generate_password();
    
    $handler = new AdminHandler(1, 'oidc_callback.php');
    $values = [
        'username' => $email,
        'password' => $randomPassword,
        'active' => true,
    ];
    
    if (!$handler->init($email)) {
        flash_error('Failed to initialize admin account.');
        header('Location: login.php');
        exit;
    }
    
    if (!$handler->set($values)) {
        flash_error('Failed to set admin account values.');
        header('Location: login.php');
        exit;
    }
    
    if (!$handler->save()) {
        flash_error('Failed to create admin account: ' . implode(', ', $handler->errormsg));
        header('Location: login.php');
        exit;
    }
    
    $username = $email;
    $isSuperadmin = false;
} else {
    $adminProperties = $adminHandler->result();
    $username = $adminProperties['username'];
    $isSuperadmin = ($adminProperties['superadmin'] ?? 0) == 1;
}

// Check if user is active
$checkAdmin = new AdminHandler();
$checkAdmin->init($username);
if (!$checkAdmin->view()) {
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
