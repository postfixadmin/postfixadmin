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
 * Displays a menu/home page.
 * Template File: main.php
 *
 * Template Variables: -none-
 *
 * Form POST \ GET Variables: -none-
 */

require_once('common.php');

$SESSID_USERNAME = authentication_get_username();

authentication_require_role('admin');

$CONF = Config::getInstance()->getAll();
$smarty = PFASmarty::getInstance();


$q = safeget('q');

$smarty->assign('q', '');

if (!empty($q)) {

    $table_alias = table_by_key('alias');
    $table_domain = table_by_key('domain');
    $table_mailbox = table_by_key('mailbox');

    $mailboxes = db_query_all("SELECT * FROM $table_mailbox WHERE username LIKE :q ORDER BY username ASC LIMIT 15", ['q' => "%$q%"]);
    $aliases = db_query_all("SELECT * FROM $table_alias WHERE address LIKE :q ORDER BY address ASC LIMIT 15", ['q' => "%$q%"]);
    $domains = db_query_all("SELECT * FROM $table_domain WHERE domain LIKE :q AND domain != 'ALL'  ORDER BY domain ASC LIMIT 15", ['q' => "%$q%"]);

    $smarty->assign('q', $q);
    $smarty->assign('mailboxes', $mailboxes);
    $smarty->assign('aliases', $aliases);
    $smarty->assign('domains', $domains);
}
$smarty->assign('smarty_template', 'main');
$smarty->display('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
