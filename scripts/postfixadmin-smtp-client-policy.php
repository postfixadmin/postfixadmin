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
 *   --check   validate the configuration, test the database query and exit
 *             (exit code 0 = ok, 1 = error)
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

if (in_array('--check', $argv ?? [], true)) {
    if ($startupError !== null) {
        fwrite(STDERR, "ERROR: $startupError\n");
        exit(1);
    }
    try {
        $data = SmtpClientPolicy::loadFromDatabase();
        foreach ($warnings as $warning) {
            fwrite(STDERR, "WARNING: $warning\n");
        }
        printf("OK: %d assignment(s), %d alias domain(s)\n", count($data['assignments']), count($data['aliases']));
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, 'ERROR: database: ' . $e->getMessage() . "\n");
        exit(1);
    }
}

// On a configuration error keep answering (with a temporary failure) instead
// of exiting: Postfix would otherwise only log "451 4.3.5 Server configuration
// problem" without the reason, and retry the spawn for every request.
SmtpClientPolicy::serve(STDIN, STDOUT, $policy, $startupError);
exit(0);
