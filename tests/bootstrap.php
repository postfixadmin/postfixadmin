<?php

define('POSTFIXADMIN', 1);
define('POSTFIXADMIN_CLI', 1);

require_once(dirname(__FILE__) . '/../vendor/autoload.php');
require_once(dirname(__FILE__) . '/../common.php');


$CONF['default_language'] = 'en';
$CONF['language_hook'] = '';

$db_file = dirname(__FILE__) . '/postfixadmin.sqlite.test';

touch($db_file);

$CONF['database_type'] = 'sqlite';
$CONF['database_name'] = $db_file;

Config::write('database_type', 'sqlite');
Config::write('database_name', $db_file);

clearstatcache();

if (file_exists($db_file)) {
    unlink($db_file);
}


require_once(dirname(__FILE__) . '/../public/upgrade.php');
