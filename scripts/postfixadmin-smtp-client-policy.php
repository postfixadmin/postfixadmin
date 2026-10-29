#!/usr/bin/env php
<?php

/**
 * Postfix policy service for $CONF['domain_smtp_client_ips'].
 *
 * Run by Postfix via spawn(8), see DOCUMENTS/SMTP_CLIENT_IPS.md:
 *
 *   postfixadmin-smtp-client-policy unix - n n - 0 spawn
 *     user=pfa-smtp-policy argv=/usr/bin/php /srv/postfixadmin/scripts/postfixadmin-smtp-client-policy.php
 *
 * Reads Postfix policy requests on stdin and answers on stdout. Uses the
 * database settings of PostfixAdmin (config.local.php); optional read-only
 * credentials can be set in $CONF['smtp_client_policy'].
 *
 *   --check [--cidr-map=FILE] [--cidr-class=NAME]
 *             validate the configuration, test the database query and exit
 *             (exit code 0 = ok, 1 = error). Also checks that the Postfix cidr
 *             map (FILE, default: client_networks_map if set) sends all policy
 *             networks and all assigned addresses to the policy (class NAME,
 *             default client_networks_map_class / pfa_smtp_client_policy),
 *             and warns about map entries outside client_networks.
 *   --print-cidr-map [--cidr-class=NAME]
 *             print that cidr map, generated from client_networks (not
 *             needed with client_networks_map)
 */

define('POSTFIXADMIN_CLI', 1);

// stdout belongs to the policy protocol: never print PHP notices/warnings there
ini_set('display_errors', 'stderr');
ini_set('log_errors', '1');

openlog('postfix/postfixadmin-smtp-client-policy', LOG_PID, LOG_MAIL);

$policy = null;
$startupError = null;
$warnings = [];

try {
    require_once(__DIR__ . '/../common.php');

    if (!Config::bool('domain_smtp_client_ips')) {
        throw new RuntimeException("\$CONF['domain_smtp_client_ips'] is not enabled");
    }

    $options = Config::read('smtp_client_policy');
    if ($options === null) {
        $options = [];
    }
    if (!is_array($options)) {
        throw new InvalidArgumentException("\$CONF['smtp_client_policy'] must be an array");
    }

    $policy = new SmtpClientPolicy($options);
    $o = $policy->options();

    // Credentials: the policy should use its own read-only database user, and
    // the PostfixAdmin credentials (write access) should not be readable by
    // the policy user. See DOCUMENTS/SMTP_CLIENT_IPS.md, "Database credentials".
    $pfaPassword = (string)Config::read('database_password');
    // shipped default from config.inc.php - read as text, not executed
    // (executing it again would reset the global $CONF)
    $defaultPassword = '';
    if (preg_match("/^\\\$CONF\\['database_password'\\]\\s*=\\s*'([^']*)';/m", (string)file_get_contents(__DIR__ . '/../config.inc.php'), $m)) {
        $defaultPassword = $m[1];
    }
    if ($o['database_user'] === null) {
        $warnings[] = "no smtp_client_policy database_user set - the policy uses the PostfixAdmin database user";
    } elseif ($pfaPassword !== '' && $pfaPassword !== $defaultPassword && $pfaPassword !== (string)$o['database_password']) {
        $warnings[] = "the PostfixAdmin database password is readable by this user - keep it in a file the policy user cannot read";
    }

    if ($o['database_user'] !== null) {
        Config::write('database_user', $o['database_user']);
        $CONF['database_user'] = $o['database_user'];
    }
    if ($o['database_password'] !== null) {
        Config::write('database_password', $o['database_password']);
        $CONF['database_password'] = $o['database_password'];
    }
} catch (Throwable $e) {
    $startupError = $e->getMessage();
    syslog(LOG_ERR, 'configuration error: ' . $startupError);
}

$args = $argv ?? [];
$cliOption = function (string $name) use ($args): ?string {
    foreach ($args as $arg) {
        if (strpos($arg, "--$name=") === 0) {
            return substr($arg, strlen($name) + 3);
        }
    }
    return null;
};
$cidrClass = $cliOption('cidr-class') ?? ($policy !== null ? $policy->options()['client_networks_map_class'] : SmtpClientPolicy::CIDR_MAP_CLASS);

if (in_array('--print-cidr-map', $args, true)) {
    if ($policy === null || $startupError !== null) {
        fwrite(STDERR, "ERROR: $startupError\n");
        exit(1);
    }
    if ($policy->options()['client_networks_map'] !== null) {
        fwrite(STDERR, "ERROR: the networks are read from {$policy->options()['client_networks_map']} (client_networks_map) - nothing to generate\n");
        exit(1);
    }
    echo $policy->cidrMap($cidrClass);
    exit(0);
}

if (in_array('--check', $args, true)) {
    if ($policy === null || $startupError !== null) {
        fwrite(STDERR, "ERROR: $startupError\n");
        exit(1);
    }
    try {
        $data = SmtpClientPolicy::loadFromDatabase();
    } catch (Throwable $e) {
        fwrite(STDERR, 'ERROR: database: ' . $e->getMessage() . "\n");
        exit(1);
    }
    foreach ($warnings as $warning) {
        fwrite(STDERR, "WARNING: $warning\n");
    }
    printf("OK: %d assignment(s), %d alias domain(s)\n", count($data['assignments']), count($data['aliases']));
    $o = $policy->options();
    if ($o['client_networks_map'] !== null) {
        printf("OK: %d policy network(s) read from %s\n", count($o['client_networks']), $o['client_networks_map']);
    }

    $mapFile = $cliOption('cidr-map') ?? $o['client_networks_map'];
    if ($mapFile === null) {
        fwrite(STDERR, "NOTE: Postfix cidr map not checked - add --cidr-map=/etc/postfix/smtp_client_networks.cidr\n");
        exit(0);
    }
    try {
        $map = SmtpClientPolicy::readCidrMapFile($mapFile);
    } catch (RuntimeException $e) {
        fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
        exit(1);
    }
    $result = $policy->checkCidrMap($map, $data['assignments'], $cidrClass);
    foreach ($result['warnings'] as $warning) {
        fwrite(STDERR, "WARNING: $mapFile: $warning\n");
    }
    foreach ($result['errors'] as $error) {
        fwrite(STDERR, "ERROR: $mapFile: $error\n");
    }
    if ($result['errors'] !== []) {
        exit(1);
    }
    echo "OK: $mapFile sends all policy networks and assigned addresses to $cidrClass\n";
    exit(0);
}

// On a configuration error keep answering (with a temporary failure) instead
// of exiting: Postfix would otherwise only log "451 4.3.5 Server configuration
// problem" without the reason, and retry the spawn for every request.
SmtpClientPolicy::serve(STDIN, STDOUT, $policy, $startupError);
exit(0);
