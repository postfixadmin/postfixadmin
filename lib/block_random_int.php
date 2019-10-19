<?php

/**
 * This file should only be loaded if you're : 
 *  a. running PHP < 7.0, and
 *  b. have the php_crypt password hash configured, and
 *  c. have not loaded paragonie's random_compat library.
 *
 */

if(function_exists('random_int')) {
    return;
}

function random_int() { // someone might not be using php_crypt or ask for password generation, in which case random_int() won't be called
        die(__FILE__ . " Postfixadmin security: Please install https://github.com/paragonie/random_compat OR enable the 'Phar' extension.");
}
