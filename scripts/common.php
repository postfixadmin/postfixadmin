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
 * @version $Id: common.php 733 2009-10-20 19:25:20Z christian_boltz $ 
 * @license GNU GPL v2 or later. 
 * 
 * File: common.php
 * All pages should include this file - which itself sets up the necessary
 * environment and ensures other functions are loaded.
 */



$incpath = PATH;
(ini_get('magic_quotes_gpc') ? ini_set('magic_quotes_runtime', '0') : '1');
(ini_get('magic_quotes_gpc') ? ini_set('magic_quotes_sybase', '0') : '1');

if(ini_get('register_globals') == 'on') {
    die("Please turn off register_globals; edit your php.ini");
}
require_once("$incpath/variables.inc.php");
/*
if(!is_file("$incpath/config.inc.php")) {
    die("config.inc.php is missing!");
}
require_once("$incpath/config.inc.php");
*/
if(isset($CONF['configured'])) {
    if($CONF['configured'] == FALSE) {
        die("Please edit config.inc.php - change \$CONF['configured'] to true after setting your database settings");
    }
}

/*
require_once("$incpath/languages/language.php");
require_once("$incpath/functions.inc.php");
require_once("$incpath/languages/en.lang");
*/
/**
 * @param string $class
 * __autoload implementation, for use with spl_autoload_register().
 */
function postfixadmin_autoload2($class) {
    $PATH = CORE_INCLUDE_PATH.'/../model/' . $class . '.php';

    if(is_file($PATH)) {
        require_once($PATH);
        return true;
    }
    return false;
}
spl_autoload_register('postfixadmin_autoload2');




/**
 * Convenience method for strtolower().
 *
 * @param string $str String to lowercase
 * @return string Lowercased string
 */
        function low($str) {
                return strtolower($str);
        }
/**
 * Convenience method for strtoupper().
 *
 * @param string $str String to uppercase
 * @return string Uppercased string
 */
        function up($str) {
                return strtoupper($str);
        }
/**
 * Convenience method for str_replace().
 *
 * @param string $search String to be replaced
 * @param string $replace String to insert
 * @param string $subject String to search
 * @return string Replaced string
 */
        function r($search, $replace, $subject) {
                return str_replace($search, $replace, $subject);
        }
/**
 * Print_r convenience function, which prints out <PRE> tags around
 * the output of given array. Similar to debug().
 *
 * @see debug()
 * @param array $var Variable to print out
 * @param boolean $showFrom If set to true, the method prints from where the function was called
 */
        function pr($var) {
                if (Configure::read() > 0) {
                        echo "<pre>";
                        print_r($var);
                        echo "</pre>";
                }
        }




/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
