<?php
// Postfix Admin
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// Licensed under GPL for more info check GPL-LICENSE.TXT
//
// File: common.php.php
//
// Template File: -none-
//
// Template Variables: -none-
//
// Form POST \ GET Variables: -none-
// 

$incpath = dirname(__FILE__);
(ini_get('magic_quotes_gpc') ? ini_set('magic_quotes_runtime', '0') : '1');
(ini_get('magic_quotes_gpc') ? ini_set('magic_quotes_sybase', '0') : '1');

require_once("$incpath/variables.inc.php");
if(!is_file("$incpath/config.inc.php")) {
    // incorrectly setup...
    header("Location: setup.php");    
    exit(0);
}
require_once("$incpath/config.inc.php");
require_once("$incpath/functions.inc.php");
require_once("$incpath/languages/" . check_language () . ".lang");

session_start();
