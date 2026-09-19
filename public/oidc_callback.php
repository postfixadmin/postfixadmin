<?php

/**
 * OIDC callback handler
 * Receives authorization code from IdP, validates token, logs user in
 * Supports both master (global) and per-domain OIDC providers
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

// Try to find domain-specific OIDC config by looking at state/session
$domainOidcConfig = null;
if (isset($_SESSION['oidc_domain'])) {
    $domainOidcHandler = new DomainOidcHandler($_SESSION['oidc_domain']);
    if ($domainOidcHandler->exists()) {
        $domainOidcConfig = $domainOidcHandler->get();
    }
}

// Use domain-specific config or fall back to global
if ($domainOidcConfig) {
    $oidcConfig = [
        'client_id' => $domainOidcConfig['client_id'],
        'client_secret' => $domainOidcConfig['client_secret'],
        'issuer_url' => $domainOidcConfig['issuer_url'],
        'redirect_uri' => $CONF['oidc']['redirect_uri'] ?? '',
        'scopes' => $domainOidcConfig['scopes'] ?? 'openid email profile',
    ];
    $oidc = new OIDC($oidcConfig);
} else {
    $oidc = new OIDC();
}

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

// Look up user by configured identity method
$username = '';
$isSuperadmin = false;
$table_admin = table_by_key('admin');

if (($CONF['oidc_identity'] ?? 'issuer_sub') === 'email' && !$domainOidcConfig) {
    // Legacy: identify by email (backward compat) — global IdP only
    try {
        $adminHandler = new AdminHandler();
        $adminHandler->init($email);
        if ($adminHandler->view()) {
            $adminProperties = $adminHandler->result();
            $username = $adminProperties['username'];
            $isSuperadmin = ($adminProperties['superadmin'] ?? 0) == 1;
        }
    } catch (\Exception $e) {
        // User not found
    }
} else {
    // Secure: identify by issuer + sub (recommended)
    $issuer = $claims['iss'] ?? '';
    $sub = $claims['sub'] ?? '';

    if ($issuer && $sub) {
        $adminRecord = db_query_one(
            "SELECT * FROM $table_admin WHERE oidc_issuer = ? AND oidc_sub = ?",
            [$issuer, $sub]
        );
        if ($adminRecord) {
            $username = $adminRecord['username'];
            $isSuperadmin = ($adminRecord['superadmin'] ?? 0) == 1;
        }
    }
}

// User not found - auto-provision if enabled
if (empty($username)) {
    // Check if we should upgrade an existing account (NULL issuer → new issuer+sub)
    if (($CONF['oidc_upgrade_existing'] ?? false) && ($CONF['oidc_identity'] ?? 'issuer_sub') !== 'email') {
        $existing = db_query_one(
            "SELECT * FROM $table_admin WHERE username = ?",
            [$email]
        );
        if ($existing && empty($existing['oidc_issuer']) && empty($existing['oidc_sub'])) {
            // Upgrade: fill in issuer+sub for this previously-local account
            db_execute(
                "UPDATE $table_admin SET oidc_issuer = ?, oidc_sub = ? WHERE username = ?",
                [$issuer, $sub, $email]
            );
            $username = $existing['username'];
            $isSuperadmin = ($existing['superadmin'] ?? 0) == 1;
        }
    }

    if (empty($username)) {
        // Don't auto-provision in issuer_sub mode if account already exists with different issuer
        $existing = db_query_one(
            "SELECT * FROM $table_admin WHERE username = ?",
            [$email]
        );
        if ($existing && !empty($existing['oidc_issuer'])) {
            flash_error('This email is already associated with a different identity provider. Contact an administrator.');
            header('Location: login.php');
            exit;
        }
    }

    $autoProvision = $domainOidcConfig ? ($domainOidcConfig['auto_provision'] ?? 0) : ($CONF['oidc_auto_provision'] ?? false);

    if (!$autoProvision) {
        flash_error('You are not authorized to access this system. Contact an administrator.');
        header('Location: login.php');
        exit;
    }

    // Auto-provision new admin user atomically
    $randomPassword = generate_password();
    $hashedPassword = pacrypt($randomPassword);

    if (db_pgsql() || db_sqlite()) {
        db_execute(
            "INSERT INTO $table_admin (username, password, active, created, modified, oidc_issuer, oidc_sub) VALUES (?, ?, true, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, ?, ?) ON CONFLICT (username) DO NOTHING",
            [$email, $hashedPassword, $issuer, $sub]
        );
    } else {
        db_execute(
            "INSERT IGNORE INTO $table_admin (username, password, active, created, modified, oidc_issuer, oidc_sub) VALUES (?, ?, true, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, ?, ?)",
            [$email, $hashedPassword, $issuer, $sub]
        );
    }

    $username = $email;

    // Domain IdP: create domain-admin, not super-admin
    if ($domainOidcConfig) {
        $isSuperadmin = false;
        // Add to domain_admins for this domain
        $table_domain_admins = table_by_key('domain_admins');
        db_execute(
            "INSERT INTO $table_domain_admins (username, domain, created, active) VALUES (?, ?, CURRENT_TIMESTAMP, true) ON CONFLICT DO NOTHING",
            [$username, $domainOidcConfig['domain']]
        );
    }
    // Global OIDC: don't change permissions - user was already created by super-admin
}

// Check if user is active
$adminRecord = db_query_one("SELECT active FROM $table_admin WHERE username = ?", [$username]);
if (!$adminRecord || !db_get_boolean($adminRecord['active'])) {
    flash_error('Your account is disabled. Contact an administrator.');
    header('Location: login.php');
    exit;
}

// Check if MFA was used at the IdP (via amr claim)
$amr = $claims['amr'] ?? [];
$mfa_used = false;

// Use per-domain MFA methods if available, otherwise global
if ($domainOidcConfig) {
    $whitelist = (new DomainOidcHandler($domainOidcConfig['domain']))->getMfaMethods();
    $blacklist = (new DomainOidcHandler($domainOidcConfig['domain']))->getMfaBlacklist();
} else {
    $whitelist = $CONF['oidc_mfa_methods'] ?? [];
    $blacklist = $CONF['oidc_mfa_blacklist'] ?? [];
}

foreach ($amr as $method) {
    $method = strtolower($method);
    if (in_array($method, $whitelist) && !in_array($method, $blacklist)) {
        $mfa_used = true;
        break;
    }
}

if ($mfa_used) {
    // MFA completed at IdP — full session
    init_session($username, true, true);
} else {
    // Use per-domain MFA policy if available, otherwise global
    $oidc_mfa = $domainOidcConfig ? (new DomainOidcHandler($domainOidcConfig['domain']))->getMfaPolicy() : ($CONF['oidc_mfa'] ?? 'none');

    if ($oidc_mfa === 'idp_mfa') {
        // Must have IdP MFA — TOTP is not a fallback
        flash_error('MFA required at identity provider. Please authenticate with multi-factor authentication at your IdP.');
        header('Location: login.php');
        exit;
    }

    // 'none' or 'mfa_or_totp' — check for local TOTP
    $totppf = new TotpPf('admin', new Login('admin'));
    if ($totppf->usesTOTP($username)) {
        // User has local TOTP configured — always prompt (legacy protection)
        init_session($username, true, false);
        header('Location: login-mfa.php');
        exit;
    }

    // No local TOTP
    if ($oidc_mfa === 'none') {
        // No MFA required — allow login
        init_session($username, true, true);
    } else {
        // 'mfa_or_totp' — no IdP MFA, no local TOTP — reject
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
