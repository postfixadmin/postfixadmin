<?php
/**
 * Postfix Admin
 *
 * LICENSE
 * This source file is subject to the GPL license that is bundled with
 * this package in the file LICENSE.TXT.
 *
 * Further details on the project are available at http://postfixadmin.sf.net
 *
 * @license GNU GPL v2 or later.
 *
 * File: common.php
 * All pages should include this file - which itself sets up the necessary
 * environment and ensures other functions are loaded.
 */

if (!defined('POSTFIXADMIN')) { # already defined if called from setup.php
    define('POSTFIXADMIN', 1); # checked in included files

    if (!defined('POSTFIXADMIN_CLI')) {
        // this is the default; see also https://sourceforge.net/p/postfixadmin/bugs/347/
        session_cache_limiter('nocache');
        session_name('postfixadmin_session');
        session_start();

        if (empty($_SESSION['flash'])) {
            $_SESSION['flash'] = array();
        }

        // avoid clickjacking attacks?
        header('X-Frame-Options: DENY');
    }
}

$incpath = dirname(__FILE__);

/**
 * @param string $class
 * __autoload implementation, for use with spl_autoload_register().
 */
function postfixadmin_autoload($class) {
    $PATH = dirname(__FILE__) . '/model/' . $class . '.php';

    if (is_file($PATH)) {
        require_once($PATH);
        return true;
    }
    return false;
}

spl_autoload_register('postfixadmin_autoload');

if (!is_file("$incpath/config.inc.php")) {
    die("config.inc.php is missing!");
}

global $CONF;

require_once("$incpath/config.inc.php");


if (isset($CONF['configured']) && !defined('PHPUNIT_TEST')) {
    if ($CONF['configured'] == false) {
        die("Please edit config.local.php - change \$CONF['configured'] to true after specifying appropriate local settings (database_type etc)");
    }
}

Config::getInstance()->setAll($CONF);

$PALANG = [];

require_once("$incpath/languages/language.php");
require_once("$incpath/functions.inc.php");

if (defined('POSTFIXADMIN_CLI')) {
    $language = 'en'; # TODO: make configurable or autodetect from locale settings
} else {
    $language = check_language(); # TODO: storing the language only at login instead of calling check_language() on every page would save some processor cycles ;-)
    $_SESSION['lang'] = $language;
}
if (!empty($language)) {
    require_once("$incpath/languages/" . $language . ".lang");
}

if (!empty($CONF['language_hook']) && function_exists($CONF['language_hook'])) {
    $hook_func = $CONF['language_hook'];
    $PALANG = $hook_func($PALANG, $language);
}

Config::write('__LANG', $PALANG);

if (!defined('POSTFIXADMIN_CLI')) {
    if (!isset($PALANG)) {
        die("environment not setup correctly");
    }
    require_once(__DIR__  . '/lib/smarty/libs/Autoloader.php');
    Smarty_Autoloader::register();
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
