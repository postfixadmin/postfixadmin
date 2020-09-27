<?php

if (!isset($CONF) || !isset($PALANG)) {
    die("environment not setup correctly");
}

require_once(dirname(__FILE__) . '/smarty/libs/Autoloader.php');


Smarty_Autoloader::register();
