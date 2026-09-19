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
    // Domain-specific login
    $domainOidcHandler = new DomainOidcHandler($domain);
    if (!$domainOidcHandler->exists()) {
        header('Location: login.php');
        exit;
    }

    $domainConfig = $domainOidcHandler->get();
    $oidcConfig = [
        'client_id' => $domainConfig['client_id'],
        'client_secret' => $domainConfig['client_secret'],
        'issuer_url' => $domainConfig['issuer_url'],
        'redirect_uri' => $CONF['oidc']['redirect_uri'] ?? '',
        'scopes' => $domainConfig['scopes'] ?? 'openid email profile',
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
