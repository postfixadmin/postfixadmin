<?php

define('POSTFIXADMIN', 1);
define('POSTFIXADMIN_CLI', 1);

require_once(dirname(__FILE__) . '/../vendor/autoload.php');
require_once(dirname(__FILE__) . '/../common.php');


$CONF['default_language'] = 'en';
$CONF['language_hook'] = '';

if (getenv('DATABASE') == 'sqlite' || getenv('DATABASE') == false) {
    $db_file = dirname(__FILE__) . '/postfixadmin.sqlite.test';
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
    $expand_tilde = function ($path) {
        if (function_exists('posix_getuid') && strpos($path, '~') !== false) {
            $info = posix_getpwuid(posix_getuid());
            $path = str_replace('~', $info['dir'], $path);
        }

        return $path;
    };

    $config = parse_ini_file($expand_tilde('~/.my.cnf'));

    if (empty($config)) {
        $config = ['user' => 'root', 'host' => '127.0.0.1', 'password' => ''];
    }

    if (isset($config['socket'])) {
        $CONF['database_socket'] = $config['socket'];
        Config::write('database_socket', $config['socket']);
    } else {
        $CONF['database_host'] = $config['host'];
        Config::write('database_host', $config['host']);
    }

    $CONF['database_type'] = 'mysql';
    $CONF['database_user'] = $config['user'];
    $CONF['database_password'] = $config['password'];
    $CONF['database_name'] = 'postfixadmin';
    Config::write('database_type', 'mysql');
    Config::write('database_user', $config['user']);
    Config::write('database_password', $config['password']);
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
