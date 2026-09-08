<?php

require_once('common.php');

$username = authentication_get_username();
authentication_require_role('admin');
CsrfToken::assertValid(safepost('CSRF_Token'));

if (DomainDnsStatus::configuredMode() === 0) {
    header('Location: list.php?table=domain');
    exit;
}

if (authentication_has_role('global-admin')) {
    $requested_username = safepost('username', $username);
    if (array_key_exists($requested_username, list_admins())) {
        $username = $requested_username;
    }
}

$domain_handler = new DomainHandler(0, $username);
$status = new DomainDnsStatus(1.0, null, $domain_handler);
$domains = list_domains_for_admin($username);
$domain = safepost('domain');
if ($domain !== '') {
    // Validate ownership before performing any network or database operation.
    $domain_handler->dnsStatus($domain);
    $status->refresh([$domain]);
    header('Location: list-virtual.php?' . http_build_query(['domain' => $domain]));
    exit;
}
$status->refresh($domains);

$params = ['table' => 'domain'];
if (authentication_has_role('global-admin')) {
    $params['username'] = $username;
}
if (safepost('search_dns_active') === '0') {
    $params['search'] = ['dns_active' => 0];
}
header('Location: list.php?' . http_build_query($params));
