<?php

// vim:ts=4:sw=4:et
ini_set('include_path', get_include_path() . ':' . dirname(__FILE__));

@include_once('Zend/Version.php');
if (!class_exists('Zend_Version', false)) {
    die("Zend Framework not found. Please check the INSTALL File.");
}
chdir("..");


if (!defined('SM_PATH')) {
    define('SM_PATH', '../');
}

$config_file = dirname(__FILE__ ) . '/config.php';
$validate_file = dirname(__FILE__) . '/../include/validate.php';

if (!file_exists($config_file)) {
    die("$config_file is missing");
}

include_once($config_file);
include_once(dirname(__FILE__) . '/functions.inc.php');

if (file_exists($validate_file)) {
    include_once($validate_file);
} else {
    $validate_file = SM_PATH . '/src/validate.php';
    if (file_exists($validate_file)) {
        include_once($validate_file);
    }
}


include_once(SM_PATH . 'functions/page_header.php');
include_once(SM_PATH . 'functions/display_messages.php');
include_once(SM_PATH . 'functions/imap.php');
include_if_exists(SM_PATH . 'functions/array.php');
if (file_exists(SM_PATH . 'src/load_prefs.php')) {
    include_once(SM_PATH . 'src/load_prefs.php');
} else {
    include_if_exists(SM_PATH . 'include/load_prefs.php');
}
// overwrite squirrelmail's content type to utf8...
header("Content-Type: text/html; charset=utf8");


//global $VACCONFMESSAGE;
bindtextdomain('postfixadmin', dirname(__FILE__) . '/postfixadmin/locale');
textdomain('postfixadmin');
