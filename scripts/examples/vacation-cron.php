#!/bin/env php
<?php

/**
 * This script is intended to be run through cron.
 * It should look through the postfixadmin vacation table for vacation entries that should no longer be active. If it
 * finds one that's expired, it should update the alias record to remove the autoreply alias, as well as deactivating the vacation entry.
 * @see https://github.com/postfixadmin/postfixadmin/issues/832
 *
 * How you link it into your cron routine is up to you - you 'could' have an entry in /etc/cron.hourly/postfixadmin that contains something like :
 *
 * #!/bin/bash
 * cd /path/to/postfixadmin/scripts/examples
 * php vacation-cron.php
 *
 *
 * might work.
 */
require_once(__DIR__ . '/../../public/common.php');

define('POSTFIXADMIN_CLI', 1);

$table_vacation = table_by_key('vacation');

$active = db_get_boolean(1);

$vacations_that_need_deactivating = db_query_all("SELECT * FROM $table_vacation WHERE activeuntil <= NOW() AND active = :active ", ['active' => $active]);

foreach ($vacations_that_need_deactivating as $row) {

    try {
        $vh = new VacationHandler($row['email']);
        error_log(__FILE__ . " - I need to disable the postfixadmin vacation stuff for : {$row['email']} as it should end at {$row['activeuntil']}");
        $vh->remove();

    } catch (\Exception $e) {
        error_log(__FILE__ . " - failed to remove postfixadmin vacation settings for user." . $e->getMessage());
    }
}
