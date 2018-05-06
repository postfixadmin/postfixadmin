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
 * @version $Id$
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
    }
}

$incpath = dirname(__FILE__);
(ini_get('magic_quotes_gpc') ? ini_set('magic_quotes_runtime', '0') : '1');
(ini_get('magic_quotes_gpc') ? ini_set('magic_quotes_sybase', '0') : '1');

if (ini_get('register_globals') == 'on') {
    die("Please turn off register_globals; edit your php.ini");
}

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
require_once("$incpath/config.inc.php");

if (isset($CONF['configured'])) {
    if ($CONF['configured'] == false) {
        die("Please edit config.local.php - change \$CONF['configured'] to true after setting your database settings");
    }
}

Config::write($CONF);

require_once("$incpath/languages/language.php");
require_once("$incpath/functions.inc.php");
require_once("$incpath/lib/random_compat.phar");

if (defined('POSTFIXADMIN_CLI')) {
    $language = 'en'; # TODO: make configurable or autodetect from locale settings
} else {
    $language = check_language(); # TODO: storing the language only at login instead of calling check_language() on every page would save some processor cycles ;-)
    $_SESSION['lang'] = $language;
}

require_once("$incpath/languages/" . $language . ".lang");

if (!empty($CONF['language_hook']) && function_exists($CONF['language_hook'])) {
    $hook_func = $CONF['language_hook'];
    $PALANG = $hook_func($PALANG, $language);
}

Config::write('__LANG', $PALANG);

unset($incpath);

if (!defined('POSTFIXADMIN_CLI')) {
    if (!is_file(dirname(__FILE__) . "/lib/smarty.inc.php")) {
        die("smarty.inc.php is missing! Something is wrong...");
    }
    require_once(dirname(__FILE__) . "/lib/smarty.inc.php");
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
