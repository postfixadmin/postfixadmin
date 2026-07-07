<?php

/**
 * Postfix Admin
 *
 * LICENSE
 * This source file is subject to the GPL license that is bundled with
 * this package in the file LICENSE.TXT.
 *
 * Further details on the project are available at https://github.com/postfixadmin/postfixadmin
 *
 * @license GNU GPL v2 or later.
 *
 * File: delete-inactive.php
 * Bulk-delete every inactive (active = 0) alias/forward of a single domain.
 * This is the "delete by filter" companion to the inactive filter on
 * list-virtual.php. See https://github.com/postfixadmin/postfixadmin/issues/1038
 *
 * Template File: none
 */

require_once('common.php');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: list-virtual.php');
    exit(0);
}

CsrfToken::assertValid(safepost('CSRF_Token'));

$username = authentication_get_username(); # enforce login
authentication_require_role('admin');
$is_admin = authentication_has_role('admin');

$domain = safepost('fDomain');

# only allow domains this admin actually owns
if (!is_string($domain) || $domain === '' || !in_array($domain, list_domains_for_admin($username))) {
    flash_error(Config::Lang('invalid_parameter'));
    header('Location: list-virtual.php');
    exit(0);
}

# enumerate the inactive aliases; getList() is already restricted to the
# admin's allowed domains, so this cannot reach other domains' aliases.
$handler = new AliasHandler(0, $username, (int) $is_admin);
$handler->getList(array('domain' => $domain, 'active' => 0));
$inactive = $handler->result();

$deleted = 0;
$skipped = 0;


foreach ($inactive as $address => $_) {

    # delete each alias through its handler so per-alias checks (ownership,
    # protected/default aliases, mailbox aliases) are all honoured.
    $one = new AliasHandler(0, $username, (int) $is_admin);
    if ($one->init($address) && $one->delete()) {
        $deleted++;
    } else {
        $skipped++;
    }
}

flash_info(Config::lang_f('pDelete_inactive_success', (string) $deleted));
if ($skipped > 0) {
    flash_error(Config::lang_f('pDelete_inactive_skipped', (string) $skipped));
}

header('Location: list-virtual.php?domain=' . urlencode($domain));
exit;
