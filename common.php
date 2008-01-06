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

define('POSTFIXADMIN', 1); # checked in included files

function incorrect_setup() {
    global $incpath;
    # we ask the user to delete setup.php, which makes a blind redirect a bad idea
    if(!is_file("$incpath/setup.php")) {
        die ("config.inc.php does not exist or is not configured correctly. Please re-install setup.php and create/fix your config.");
    } else {
        header("Location: setup.php");    
        exit(0);
    }
}

$incpath = dirname(__FILE__);
(ini_get('magic_quotes_gpc') ? ini_set('magic_quotes_runtime', '0') : '1');
(ini_get('magic_quotes_gpc') ? ini_set('magic_quotes_sybase', '0') : '1');

if(ini_get('register_globals')) {
    die("Please turn off register_globals; edit your php.ini");
}
require_once("$incpath/variables.inc.php");

if(!is_file("$incpath/config.inc.php")) {
    // incorrectly setup...
    incorrect_setup();
}
require_once("$incpath/config.inc.php");
if(isset($CONF['configured'])) {
    if($CONF['configured'] == FALSE) {
        incorrect_setup();
    }
}
require_once("$incpath/languages/language.php");
require_once("$incpath/functions.inc.php");
require_once("$incpath/languages/" . check_language () . ".lang");

session_start();

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
