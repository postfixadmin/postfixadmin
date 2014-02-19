<?php
// vim:ts=4:sw=4:et
ini_set('include_path', get_include_path() . ':' . dirname(__FILE__));

@include_once('Zend/Version.php');
if(!class_exists('Zend_Version', false)) {
	die("Zend Framework not found. Please check the INSTALL File.");
}
chdir("..");


if (!defined('SM_PATH')) 
{
    define('SM_PATH','../');
}
include_once(dirname(__FILE__)  . '/config.php');
include_once(dirname(__FILE__) . '/functions.inc.php');
include_if_exists(dirname(__FILE__) . '/../include/validate.php');
if (file_exists(dirname(__FILE__) . '/../include/validate.php')) 
{
    include_once(dirname(__FILE__) . '/include/validate.php');
}
else { 
    include_if_exists(SM_PATH . 'src/validate.php');
}
include_once(SM_PATH . 'functions/page_header.php');
include_once(SM_PATH . 'functions/display_messages.php');
include_once(SM_PATH . 'functions/imap.php');
include_if_exists(SM_PATH . 'functions/array.php');
if (file_exists(SM_PATH . 'src/load_prefs.php'))
{
    include_once(SM_PATH . 'src/load_prefs.php');
}
else {
    include_if_exists(SM_PATH . 'include/load_prefs.php');
}
// overwrite squirrelmail's content type to utf8...
header("Content-Type: text/html; charset=utf8");


//global $VACCONFMESSAGE;
bindtextdomain('postfixadmin', dirname(__FILE__) . '/postfixadmin/locale');
textdomain('postfixadmin');

