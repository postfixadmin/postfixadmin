<?php

/**
 * Postfix Admin
 *
 * LICENSE
 * This source file is subject to the GPL license that is bundled with
 * this package in the file LICENSE.TXT.
 *
 * Further details on the project are available at https://github.com/postfixadmin/postfixadmin
 *
 * @version $Id$
 * @license GNU GPL v2 or later.
 *
 * File: alias-goto-default.php
 * Deletes the default for the alias "To" (goto) field that the logged-in admin stored
 * from the add-alias form. Storing it happens in AliasHandler::postSave(); only the
 * deletion needs an endpoint of its own, because it has to work without creating an alias.
 *
 * Template File: none
 */

require_once('common.php');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: main.php');
    exit(0);
}

CsrfToken::assertValid(safepost('CSRF_Token'));

$username = authentication_get_username(); # enforce login
authentication_require_role('admin');

delete_admin_pref($username, 'alias_goto_default');

flash_info(Config::lang('alias_goto_default_deleted'));

# return to the form the request came from
$domain = safepost('domain');
$target = 'edit.php?table=alias';
if ($domain != '') {
    $target .= '&domain=' . urlencode($domain);
}

header('Location: ' . $target);
exit(0);

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
