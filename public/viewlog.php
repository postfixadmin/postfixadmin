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
 * File: viewlog.php
 * Shows entries from the log table to users.
 *
 * Template File: viewlog.tpl
 *
 * Template Variables:
 *
 * tLog
 *
 * Form POST \ GET Variables:
 *
 * fDomain
 */

require_once('common.php');

authentication_require_role('admin');

$CONF = Config::getInstance()->getAll();
$smarty = PFASmarty::getInstance();

$PALANG = $CONF['__LANG'];

$SESSID_USERNAME = authentication_get_username();
if (authentication_has_role('global-admin')) {
    $list_domains = list_domains();
} else {
    $list_domains = list_domains_for_admin($SESSID_USERNAME);
}

$fDomain = '';
$error = 0;


if ($_SERVER['REQUEST_METHOD'] == "GET") {
    if (isset($_GET['page']) && $_GET['fDomain']) {
        $fDomain_aux = escape_string($_GET['fDomain']);
        $flag_fDomain = 0;
        if ((is_array($list_domains) and sizeof($list_domains) > 0)) {
            foreach ($list_domains as $domain) {
                if ($domain == $fDomain_aux) {
                    $fDomain = $domain;
                    $flag_fDomain = 1;
                    break;
                }
            }
        }

        if ($flag_fDomain == 0) {
            die('Unknown domain');
        }

        $page_number = (int) ($_GET['page'] ?? 0);
        if ($page_number == 0) {
            die('Unknown page number');
        }
    } else {
        $page_number = 1;
        if ((is_array($list_domains) and sizeof($list_domains) > 0)) {
            $fDomain = $list_domains[0];
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] == "POST") {
    $page_number = 1;
    if (isset($_POST['fDomain'])) {
        $fDomain = escape_string($_POST['fDomain']);
    }
} else {
    die('Unknown request method');
}

if (! (check_owner($SESSID_USERNAME, $fDomain) || authentication_has_role('global-admin'))) {
    $error = 1;
    flash_error($PALANG['pViewlog_result_error']);
}

$tLog = array();

if ($error != 1) {
    $table_log = table_by_key('log');
    $page_size = isset($CONF['page_size']) ? intval($CONF['page_size']) : 35;


    $where = [];
    $params = [];
    if ($fDomain) {
        $where[] = 'domain = :domain' ;
        $params['domain'] = $fDomain;
    }

    $where_sql = '';
    if (!empty($where)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where);
    }



    $number_of_logs = 0;
    $number_of_pages = 0;
    //get number of total logs
    $query = "SELECT count(*) as number_of_logs FROM $table_log $where_sql";


    $result = db_query_all($query, $params);
    foreach ($result as $r) {
        $number_of_logs = $r['number_of_logs'];
    }
    $number_of_pages = ceil($number_of_logs / $page_size);

    if ($page_number > $number_of_pages) {
        die('Unknown page number');
    }

    if ($page_number == 1) {
        $offset = 0;
    } else {
        $offset = ($page_number - 1) * $page_size;
    }

    $query = "SELECT timestamp,username,domain,action,data FROM $table_log $where_sql ORDER BY timestamp DESC LIMIT $page_size OFFSET $offset";

    if (db_pgsql()) {
        $query = "SELECT extract(epoch from timestamp) as timestamp,username,domain,action,data FROM $table_log $where_sql ORDER BY timestamp DESC LIMIT $page_size OFFSET $offset";
    }


    $result = db_query_all($query, $params);
    foreach ($result as $row) {
        if (is_array($row) && db_pgsql()) {
            $row['timestamp'] = gmstrftime('%c %Z', $row['timestamp']);
        }
        $tLog[] = $row;
    }
}

foreach ($tLog as $k => $v) {
    if (isset($v['action'])) {
        $v['action'] = $PALANG['pViewlog_action_' . $v['action']];
        $tLog[$k] = $v;
    }
}
//get url
$url = explode("?",(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]")[0];

$smarty->assign('domain_list', $list_domains);
$smarty->assign('domain_selected', $fDomain);
$smarty->assign('tLog', $tLog, false);
$smarty->assign('fDomain', $fDomain);

$smarty->assign('number_of_pages', $number_of_pages);
$smarty->assign('page_number', $page_number);
$smarty->assign('url',$url);

$smarty->assign('smarty_template', 'viewlog');
$smarty->display('index.tpl');



/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
