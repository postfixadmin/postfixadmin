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

$status = new DomainDnsStatus();
$status->refresh(list_domains_for_admin($username));

$params = ['table' => 'domain'];
if (authentication_has_role('global-admin')) {
    $params['username'] = $username;
}
if (safepost('dns_filter') === 'inactive') {
    $params['dns_filter'] = 'inactive';
}
header('Location: list.php?' . http_build_query($params));
