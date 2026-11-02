<?php
/**
 * Initiate OIDC login flow
 * Redirects user to Keycloak authorization endpoint
 */

require_once('common.php');

$CONF = Config::getInstance()->getAll();

if (!in_array('oidc', $CONF['additional_auth'] ?? [])) {
    header('Location: login.php');
    exit;
}

$oidc = new OIDC();
if (!$oidc->isConfigured()) {
    die('OIDC not configured');
}

$oidc->authorize();
