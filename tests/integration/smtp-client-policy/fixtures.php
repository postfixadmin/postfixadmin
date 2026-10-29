<?php

/**
 * Test data helper for run.sh (integration test only).
 *
 *   php fixtures.php setup
 *   php fixtures.php set-ips <domain> "<ips>"
 *   php fixtures.php set-active <domain> 0|1
 *   php fixtures.php db-break | db-restore
 *
 * db-break renames the domain table, so every query of an already running
 * policy process fails as well (renaming or removing the SQLite file would
 * not affect a process that has it open).
 */

define('POSTFIXADMIN_CLI', 1);

// public/upgrade.php loads 'common.php' with a relative path, which PHP resolves
// against the current directory first - make sure that is this checkout.
chdir(__DIR__ . '/../../..');

require_once(__DIR__ . '/../../../common.php');

$cmd = $argv[1] ?? '';

switch ($cmd) {
    case 'setup':
        require_once(__DIR__ . '/../../../public/upgrade.php');
        foreach ([['example.com', '192.0.2.10'], ['example.org', '192.0.2.11']] as [$domain, $ips]) {
            db_insert('domain', ['domain' => $domain, 'description' => '', 'transport' => '', 'smtp_client_ips' => $ips, 'active' => db_get_boolean(true)]);
            db_insert('alias', ['address' => "noreply@$domain", 'goto' => "admin@$domain", 'domain' => $domain, 'active' => db_get_boolean(true)]);
        }
        break;
    case 'set-ips':
        db_update('domain', 'domain', $argv[2], ['smtp_client_ips' => $argv[3]]);
        break;
    case 'set-active':
        db_update('domain', 'domain', $argv[2], ['active' => db_get_boolean($argv[3] === '1')]);
        break;
    case 'db-break':
        db_query('ALTER TABLE ' . table_by_key('domain') . ' RENAME TO domain_unavailable');
        break;
    case 'db-restore':
        db_query('ALTER TABLE domain_unavailable RENAME TO ' . table_by_key('domain'));
        break;
    default:
        fwrite(STDERR, "unknown command\n");
        exit(1);
}
