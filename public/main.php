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
 * File: main.php
 * Displays a menu/home page.
 * Template File: main.php
 *
 * Template Variables: -none-
 *
 * Form POST \ GET Variables: -none-
 */

require_once('common.php');

authentication_require_role('admin');

$username = authentication_get_username();
if (authentication_has_role('global-admin')) {
    $list_domains = list_domains();
} else {
    $list_domains = list_domains_for_admin($username);
}

$CONF = Config::getInstance()->getAll();
$smarty = PFASmarty::getInstance();


$q = safeget('q');

$smarty->assign('q', '');

// do not run this search stuff for an admin who has no domains associated.
if (!empty($q) && !empty($list_domains)) {

    $domains_sql = db_in_clause('domain', $list_domains);


    $domain_filter = " AND domain IN ( $domains_sql ) ";

    $table_alias = table_by_key('alias');
    $table_domain = table_by_key('domain');
    $table_mailbox = table_by_key('mailbox');

    $mailboxes = db_query_all("SELECT * FROM $table_mailbox WHERE username LIKE :q $domain_filter ORDER BY username ASC LIMIT 15", ['q' => "%$q%"]);
    $aliases = db_query_all("SELECT * FROM $table_alias WHERE address LIKE :q $domain_filter ORDER BY address ASC LIMIT 15", ['q' => "%$q%"]);
    $domains = db_query_all("SELECT * FROM $table_domain WHERE domain LIKE :q $domain_filter AND domain != 'ALL'  ORDER BY domain ASC LIMIT 15", ['q' => "%$q%"]);

    $smarty->assign('q', $q);
    $smarty->assign('mailboxes', $mailboxes);
    $smarty->assign('aliases', $aliases);
    $smarty->assign('domains', $domains);
}
$smarty->assign('smarty_template', 'main');
$smarty->display('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
