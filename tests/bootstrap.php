<?php

define('POSTFIXADMIN', 1);
define('POSTFIXADMIN_CLI', 1);

require_once(dirname(__FILE__) . '/../vendor/autoload.php');
require_once(dirname(__FILE__) . '/../common.php');


$CONF['default_language'] = 'en';
$CONF['language_hook'] = '';

if (getenv('DATABASE') == 'sqlite' || getenv('DATABASE') == false) {
    $version = PHP_VERSION_ID; // try and stop different tests running at the same trying to use the same sqlite db at once
    $db_file = tempnam(sys_get_temp_dir(), 'postfixadmin-test');
    $CONF['database_type'] = 'sqlite';
    $CONF['database_name'] = $db_file;
    Config::write('database_type', 'sqlite');
    Config::write('database_name', $db_file);
    clearstatcache();
    if (file_exists($db_file)) {
        unlink($db_file);
    }
    touch($db_file);

    error_log("Using: SQLite database for tests - $db_file");
}
if (getenv('DATABASE') == 'postgresql') {
    $user = getenv('PGUSER') ?: 'postgres';
    $pass = getenv('PGPASSWORD') ?: '';
    $host = getenv('PGHOST') ?: 'localhost';

    $CONF['database_type'] = 'pgsql';
    $CONF['database_user'] = $user;
    $CONF['database_password'] = $pass;
    $CONF['database_host'] = $host;
    $CONF['database_name'] = 'postfixadmin';
    Config::write('database_type', 'pgsql');
    Config::write('database_user', $user);
    Config::write('database_password', $pass);
    Config::write('database_name', 'postfixadmin');
    Config::write('database_host', $host);

    error_log("Using: PostgreSQL database for tests\n");
}

if (getenv('DATABASE') == 'mysql') {
    $user = getenv('MYSQL_USER') ?: 'root';
    $pass = getenv('MYSQL_PASSWORD') ?: '';
    $host = getenv('MYSQL_HOST') ?: '127.0.0.1';

    $CONF['database_type'] = 'mysql';
    $CONF['database_user'] = $user;
    $CONF['database_password'] = $pass;
    $CONF['database_host'] = $host;
    $CONF['database_name'] = 'postfixadmin';
    Config::write('database_type', 'mysql');
    Config::write('database_user', $user);
    Config::write('database_password', $pass);
    Config::write('database_host', $host);
    Config::write('database_name', 'postfixadmin');

    error_log("Using: MySQL database for tests");
}

try {
    $db = db_connect();
} catch (Exception $e) {
    echo "failed to connect to database\n";
    echo $e->getMessage();
    exit(1);
}

require_once(dirname(__FILE__) . '/../public/upgrade.php');
