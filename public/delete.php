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
 * File: delete.php
 * Used to delete admins, domains, mailboxes, aliases etc.
 *
 * Template File: none
 */

require_once('common.php');


if (safepost('token') != $_SESSION['PFA_token']) {
    die('Invalid token!');
}

$username = authentication_get_username(); # enforce login

$id    = safepost('delete');
$table = safepost('table');

if (empty($table)) {
    die('Invalid call');
}

$handlerclass = ucfirst($table) . 'Handler';

if (!preg_match('/^[a-z]+$/', $table) || !file_exists(dirname(__FILE__) . "/../model/$handlerclass.php")) { # validate $table
    die("Invalid table name given!");
}

$is_admin = authentication_has_role('admin');

$handler  = new $handlerclass(0, $username, $is_admin);
$formconf = $handler->webformConfig();

if ($is_admin) {
    authentication_require_role($formconf['required_role']);
} else {
    if (empty($formconf['user_hardcoded_field'])) {
        die($handlerclass . ' is not available for users');
    }
}

if ($handler->init($id)) { # errors will be displayed as last step anyway, no need for duplicated code ;-)
    $handler->delete();
}

flash_error($handler->errormsg);
flash_info($handler->infomsg);

header("Location: " . $formconf['listview']);
exit;

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
