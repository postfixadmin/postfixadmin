<?php
/** 
 * Postfix Admin 
 * 
 * LICENSE 
 * This source file is subject to the GPL license that is bundled with  
 * this package in the file LICENSE.TXT. 
 * 
 * Further details on the project are available at : 
 *     http://www.postfixadmin.com or http://postfixadmin.sf.net 
 * 
 * @version $Id$ 
 * @license GNU GPL v2 or later. 
 * 
 * File: common.php
 * All pages should include this file - which itself sets up the necessary
 * environment and ensures other functions are loaded.
 */

if(!defined('POSTFIXADMIN')) { # already defined if called from setup.php
    session_start();
	define('POSTFIXADMIN', 1); # checked in included files
}

$incpath = dirname(__FILE__);
(ini_get('magic_quotes_gpc') ? ini_set('magic_quotes_runtime', '0') : '1');
(ini_get('magic_quotes_gpc') ? ini_set('magic_quotes_sybase', '0') : '1');

if(ini_get('register_globals') == 'on') {
    die("Please turn off register_globals; edit your php.ini");
}
require_once("$incpath/variables.inc.php");

if(!is_file("$incpath/config.inc.php")) {
    die("config.inc.php is missing!");
}
require_once("$incpath/config.inc.php");

if(isset($CONF['configured'])) {
    if($CONF['configured'] == FALSE) {
        die("Please edit config.inc.php - change \$CONF['configured'] to true after setting your database settings");
    }
}


require_once("$incpath/languages/language.php");
require_once("$incpath/functions.inc.php");
require_once("$incpath/languages/" . check_language () . ".lang");

/**
 * @param string $class
 * __autoload implementation, for use with spl_autoload_register().
 */
function postfixadmin_autoload($class) {
    $PATH = dirname(__FILE__) . '/model/' . $class . '.php';

    if(is_file($PATH)) {
        require_once($PATH);
        return true;
    }
    return false;
}
spl_autoload_register('postfixadmin_autoload');

//*****
if(!is_file("$incpath/smarty.inc.php")) {
    die("smarty.inc.php is missing! Something is wrong...");
}
require_once ("$incpath/smarty.inc.php");
//*****
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
