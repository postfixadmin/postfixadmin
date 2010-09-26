<?php
// vim:ts=4:sw=4:et
ini_set('include_path', get_include_path() . ':' . dirname(__FILE__));
chdir("..");


if (!defined('SM_PATH')) 
{
    define('SM_PATH','../');
}
include_once(SM_PATH . 'plugins/postfixadmin/config.php');
include_once(SM_PATH . 'plugins/postfixadmin/functions.inc.php');
include_if_exists(SM_PATH . 'include/validate.php');
if (file_exists(SM_PATH . 'include/validate.php')) 
{
    include_once(SM_PATH . 'include/validate.php');
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
bindtextdomain('postfixadmin', SM_PATH . 'plugins/postfixadmin/locale');
textdomain('postfixadmin');

