#!/usr/bin/env php
<?php

define('POSTFIXADMIN_CLI', 1);
require_once(__DIR__ . '/../public/common.php');

check_db_version();
if (DomainDnsStatus::configuredMode() === 0) {
    echo "DNS status check is disabled.\n";
    exit(0);
}
$status = new DomainDnsStatus();
$result = $status->refresh(list_domains());
printf("DNS status updated: %d active, %d inactive.\n", $result['active'], $result['inactive']);
exit($result['inactive'] > 0 ? 2 : 0);
