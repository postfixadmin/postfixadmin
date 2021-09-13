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
 * File: main.php
 * 'Home page' for logged in users.
 * Template File: main.php
 *
 * Template Variables:
 *
 * tummVacationtext
 *
 * Form POST \ GET Variables: -none-
 */

$rel_path = '../';
require_once('../common.php');
authentication_require_role('user');
$USERID_USERNAME = authentication_get_username();

$smarty = PFASmarty::getInstance();
$smarty->configureTheme('../');

$vh = new VacationHandler($USERID_USERNAME);
if ($vh->check_vacation()) {
    $tummVacationtext = $PALANG['pUsersMain_vacationSet'];
} else {
    $tummVacationtext = $PALANG['pUsersMain_vacation'];
}

$smarty->assign('tummVacationtext', $tummVacationtext);
$smarty->assign('smarty_template', 'users_main');
$smarty->display('index.tpl');
/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
