<?php

/**
 * Initiate OIDC login flow
 * Redirects user to IdP authorization endpoint
 * Supports both master (global) and per-domain OIDC providers
 */

require_once('common.php');

$CONF = Config::getInstance()->getAll();

if (!in_array('oidc', $CONF['additional_auth'] ?? [])) {
    header('Location: login.php');
    exit;
}

// Check if domain-specific OIDC is requested
$domain = $_GET['domain'] ?? '';

if ($domain) {
    // Domain-specific login — query domain table directly
    $table_domain = table_by_key('domain');
    $domainConfig = db_query_one("SELECT * FROM $table_domain WHERE domain = ?", [$domain]);
    if (!$domainConfig || !db_get_boolean($domainConfig['oidc_enabled'])) {
        header('Location: login.php');
        exit;
    }
    $oidcConfig = [
        'client_id' => $domainConfig['oidc_client_id'],
        'client_secret' => base64_decode($domainConfig['oidc_client_secret']),
        'issuer_url' => $domainConfig['oidc_issuer_url'],
        'redirect_uri' => $CONF['oidc']['redirect_uri'] ?? '',
        'scopes' => $domainConfig['oidc_scopes'] ?? 'openid email profile',
    ];
    $oidc = new OIDC($oidcConfig);

    // Store domain in session for callback
    $_SESSION['oidc_domain'] = $domain;
} else {
    // Master (global) login
    $oidc = new OIDC();
    unset($_SESSION['oidc_domain']);
}

if (!$oidc->isConfigured()) {
    die('OIDC not configured');
}

$oidc->authorize();
