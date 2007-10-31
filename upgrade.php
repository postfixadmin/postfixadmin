<?php
require_once('common.php');
$sql = "SELECT * FROM config WHERE name = 'version'";

// create table config (name varchar(20), value varchar(20));
// insert into config('version', '01');
// Should really query the db to see if the 'config' table exists first!

$r = db_query($sql);

if($r['rows'] == 1) {
    $rs = $r['result'];
    $row = db_array($rs);
    $version = $row['value'];
    _do_upgrade($version);
}


function _do_upgrade($current_version) {
    $all_functions = get_defined_functions();
    $upgrade_functions = array();
    foreach($all_functions['user'] as $function_name) {
        if(preg_match('!upgrade_(\d+)!', $function_name, $matches)) {
            $version = $matches[1];
            if($version <= $current_version) {
                continue;
            }
            $upgrade_functions[$matches[1]] = $function_name;
        }
    }

    ksort($upgrade_functions);
    foreach($upgrade_functions as $version => $function) {
        $function();
    }
}

function upgrade_00() {
    global $CONF;
    if($CONF['database_type'] == 'mysql') {
        echo 'mysql 00';
    }
    if($CONF['database_type'] == 'pgsql') {
        echo 'pgsql 00';
    }
}

function upgrade_01() {
    global $CONF;
    if($CONF['database_type'] == 'mysql') {
        echo 'mysql 01';
    }
    if($CONF['database_type'] == 'pgsql') {
        echo 'pgsql 01';
    }
}
function upgrade_02() {
    global $CONF;
    if($CONF['database_type'] == 'mysql') {
        echo 'mysql 02';
    }
    if($CONF['database_type'] == 'pgsql') {
        echo 'pgsql 02';
    }
}
function upgrade_03() {
    echo 'woof 03';
}
