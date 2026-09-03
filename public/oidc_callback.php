<?php
/**
 * OIDC callback handler
 * Receives authorization code from Keycloak, validates token, logs user in
 */

require_once('common.php');

$CONF = Config::getInstance()->getAll();

if (!in_array('oidc', $CONF['additional_auth'] ?? [])) {
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

// Require verified email if configured
if (($CONF['oidc_require_verified_email'] ?? false) && !($claims['email_verified'] ?? false)) {
    flash_error('OIDC authentication failed: email not verified by provider');
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

    // Auto-provision new admin user atomically
    $randomPassword = generate_password();
    $hashedPassword = pacrypt($randomPassword);

    $table_admin = table_by_key('admin');

    if (db_pgsql() || db_sqlite()) {
        // PostgreSQL 9.5+ and SQLite 3.24+: atomic upsert
        db_execute(
            "INSERT INTO $table_admin (username, password, active, created, modified) VALUES (?, ?, true, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) ON CONFLICT (username) DO NOTHING",
            [$email, $hashedPassword]
        );
    } else {
        // MySQL: INSERT IGNORE (ON DUPLICATE KEY syntax)
        db_execute(
            "INSERT IGNORE INTO $table_admin (username, password, active, created, modified) VALUES (?, ?, true, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
            [$email, $hashedPassword]
        );
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

// Check if MFA was used at the IdP (via amr claim)
$amr = $claims['amr'] ?? [];
$mfa_used = in_array('mfa', $amr) || in_array('otp', $amr) || in_array('hwk', $amr);

if ($mfa_used) {
    // MFA completed at IdP — full session
    init_session($username, true, true);
} else {
    $oidc_mfa = $CONF['oidc_mfa'] ?? 'none';

    if ($oidc_mfa === 'none') {
        // No MFA check — allow login
        init_session($username, true, true);
    } elseif ($oidc_mfa === 'required') {
        // Must have MFA at IdP — TOTP is not a fallback
        flash_error('MFA required at identity provider. Please authenticate with multi-factor authentication at your IdP.');
        header('Location: login.php');
        exit;
    } else {
        // 'totp_fallback' — try local TOTP
        $totppf = new TotpPf('admin', new Login('admin'));
        if ($totppf->usesTOTP($username)) {
            // User has local TOTP — redirect to MFA page
            init_session($username, true, false);
            header('Location: login-mfa.php');
            exit;
        }

        // No local TOTP
        flash_error('MFA required. Please authenticate with multi-factor authentication at your IdP or configure local TOTP.');
        header('Location: login.php');
        exit;
    }
}

if ($isSuperadmin) {
    $_SESSION['sessid']['roles'][] = 'global-admin';
}

// Redirect to main page
header('Location: main.php');
exit;
