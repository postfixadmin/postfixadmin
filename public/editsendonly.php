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
 * File: editsendonly.php
 * Used to switch sendonly status in mailboxes.
 *
 * Template File: list-virtual_mailbox.tpl
 */

require_once('common.php');

if (safeget('token') != $_SESSION['PFA_token']) {
    die('Invalid token!');
}

$username = authentication_get_username(); # enforce login

$id = safeget('id');
$table = safeget('table');
$sendonly = safeget('sendonly');

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
    if ($sendonly != '0' && $sendonly != '1') {
        die(Config::Lang('invalid_parameter'));
    }

    if ($handler->set(array('sendonly' => $sendonly))) {
        $handler->save();
    }
}

flash_error($handler->errormsg);
flash_info($handler->infomsg);

header("Location: " . $formconf['listview']);
exit;

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
