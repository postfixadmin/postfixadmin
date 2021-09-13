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
 * File: vacation.php
 * Responsible for allowing users to update their vacation status.
 *
 */

require_once('../common.php');

$smarty = PFASmarty::getInstance();
$smarty->configureTheme('../');
require_once('../vacation.php');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
