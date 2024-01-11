<?php
/**
 * Postfix Admin
 *
 * LICENSE
 * This source file is subject to the GPL license that is bundled with
 * this package in the file LICENSE.TXT
 *
 * Further details on the project are available at http://postfixadmin.sf.net
 *
 * @version $Id$
 * @license GNU GPL v2 or later.
 *
 * File: editactive.php
 * Used to switch active status for  admins, domains, mailboxes, aliases and aliasdomains, etc.
 *
 * Template File:
 *		  none - redirects to $formconf['listview']
 */

require_once('common.php');

if (safeget('token') != $_SESSION['PFA_token']) {
    die('Invalid token!');
}

$username = authentication_get_username(); # enforce login

$id = safeget('id');
$table = safeget('table');
$field = safeget('field');
$active = safeget('active');
if ($field === '') {
    $field = 'active';
}

if (empty($table)) {
    die("Invalid table name given");
}

$handlerclass = ucfirst($table) . 'Handler';

if (!preg_match('/^[a-z]+$/', $table) || !file_exists(dirname(__FILE__) . "/../model/$handlerclass.php")) { # validate $table
    die("Invalid table name given!");
}

$handler = new $handlerclass(0, $username);

$formconf = $handler->webformConfig();

authentication_require_role($formconf['required_role']);

if ($handler->init($id)) { # errors will be displayed as last step anyway, no need for duplicated code ;-)
    if ($table == 'mailbox') {
        if ($field != 'active' && $field != 'smtp_active') {
            die(Config::Lang('invalid_parameter'));
        }
    } else {
        if ($field != 'active') {
            die(Config::Lang('invalid_parameter'));
        }
    }

    if ($active != '0' && $active != '1') {
        die(Config::Lang('invalid_parameter'));
    }

    if ($handler->set(array($field => $active))) {
        $handler->save();
    }
}

flash_error($handler->errormsg);
flash_info($handler->infomsg);

if ($formconf['listview'] == 'list-virtual.php') {
    $bits = [];
    $bits['domain'] = $_SESSION['list-virtual:domain'] ?? null;
    $bits['limit'] = $_SESSION['list-virtual:limit'] ?? null;
    header("Location: " . $formconf['listview'] . '?' . http_build_query(array_filter($bits)));
    exit(0);
}

header("Location: " . $formconf['listview']);
exit;

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
