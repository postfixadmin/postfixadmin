#!/usr/bin/env php
<?php

define('POSTFIXADMIN_CLI', 1);

require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../model/BroadcastQueue.php');

$lock = fopen(sys_get_temp_dir() . '/postfixadmin-broadcast-worker.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "broadcast worker already running\n";
    exit(0);
}

$dryRun = in_array('--dry-run', $argv, true);
$limit = 50;

foreach ($argv as $arg) {
    if (strpos($arg, '--limit=') === 0) {
        $limit = (int)substr($arg, 8);
    }
}

do {
    $result = BroadcastQueue::processNext($limit, $dryRun);
    echo "job_id={$result['job_id']} processed={$result['processed']} sent={$result['sent']} failed={$result['failed']} status={$result['status']}\n";
} while ($result['job_id'] !== 0);
