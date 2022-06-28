<?php

// vim:ts=4:sw=4:et
if (!defined('SM_PATH')) {
    die("Invalid internal state (don't access file directly)");
}
include_once(SM_PATH . 'functions/i18n.php');

function squirrelmail_plugin_init_postfixadmin() {
    include(dirname(__FILE__) . '/config.php');
    global $squirrelmail_plugin_hooks;

    $squirrelmail_plugin_hooks['optpage_register_block']['postfixadmin'] = 'postfixadmin_optpage_register_block';
}

function postfixadmin_version() {
    return '2.3.0';
}

function postfixadmin_optpage_register_block() {
    // Gets added to the user's OPTIONS page.
    global $optpage_blocks;
    global $AllowVacation;
    global $AllowChangePass;

    //  if ( !soupNazi() ) {

    bindtextdomain('postfixadmin', SM_PATH . 'plugins/postfixadmin/locale');
    textdomain('postfixadmin');
    $optpage_blocks[] = array(
        'name' => _("Forwarding"),
        'url'  => '../plugins/postfixadmin/postfixadmin_forward.php',
        'desc' => _("Here you can create and edit E-Mail forwards."),
        'js'   => false
    );
    bindtextdomain('squirrelmail', SM_PATH . 'locale');
    textdomain('squirrelmail');

    bindtextdomain('postfixadmin', SM_PATH . 'plugins/postfixadmin/locale');
    textdomain('postfixadmin');
    if ($AllowVacation) {
        $optpage_blocks[] = array(
            'name' => _("Auto Response"),
            'url'  => '../plugins/postfixadmin/postfixadmin_vacation.php',
            'desc' => _("Set an OUT OF OFFICE message or auto responder for your mail."),
            'js'   => false
        );
        bindtextdomain('squirrelmail', SM_PATH . 'locale');
        textdomain('squirrelmail');
    }
    bindtextdomain('postfixadmin', SM_PATH . 'plugins/postfixadmin/locale');
    textdomain('postfixadmin');
    if ($AllowChangePass) {
        $optpage_blocks[] = array(
            'name' => _("Change Password"),
            'url'  => '../plugins/postfixadmin/postfixadmin_changepass.php',
            'desc' => _("Change your mailbox password."),
            'js'   => false
        );
        bindtextdomain('squirrelmail', SM_PATH . 'locale');
        textdomain('squirrelmail');
    }
}
