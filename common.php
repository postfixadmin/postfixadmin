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
$incpath = dirname(__FILE__);
(ini_get('magic_quotes_gpc') ? ini_set('magic_quotes_runtime', '0') : '1');
(ini_get('magic_quotes_gpc') ? ini_set('magic_quotes_sybase', '0') : '1');

if(ini_get('register_globals')) {
    die("Please turn off register_globals; edit your php.ini");
}
require_once("$incpath/variables.inc.php");
if(!is_file("$incpath/config.inc.php")) {
    // incorrectly setup...
    header("Location: setup.php");    
    exit(0);
}
require_once("$incpath/config.inc.php");
if(isset($CONF['configured'])) {
    if($CONF['configured'] == FALSE) {
        header("Location: setup.php");
        exit(0);
    }
}
require_once("$incpath/functions.inc.php");
require_once("$incpath/languages/" . check_language () . ".lang");

session_start();
