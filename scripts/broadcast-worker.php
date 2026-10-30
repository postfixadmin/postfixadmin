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

$dryRun = in_array('--dry-run', $argv, true) ? true : null;
$limit = 50;

foreach ($argv as $arg) {
    if (strpos($arg, '--mode=') === 0) {
        $mode = substr($arg, 7);
        BroadcastQueue::setWorkerMode($mode);
        echo "broadcast mode=" . BroadcastQueue::workerMode() . "\n";
        exit(0);
    }
    if (strpos($arg, '--limit=') === 0) {
        $limit = (int)substr($arg, 8);
    }
}

do {
    $result = BroadcastQueue::processNext($limit, $dryRun);
    echo "job_id={$result['job_id']} processed={$result['processed']} sent={$result['sent']} failed={$result['failed']} status={$result['status']}\n";
} while ($result['job_id'] !== 0);
