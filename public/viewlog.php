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

$username = authentication_get_username();
if (authentication_has_role('global-admin')) {
    $list_domains = list_domains();
} else {
    $list_domains = list_domains_for_admin($username);
}

$fDomain = '';
$error = 0;
$show_all = false;
$all_domains_value = '*'; # dropdown sentinel for the "All domains" option


if ($_SERVER['REQUEST_METHOD'] == "GET") {
    if (isset($_GET['page']) && isset($_GET['fDomain']) && $_GET['fDomain']) {
        $fDomain_aux = $_GET['fDomain'];
        if ($fDomain_aux === $all_domains_value) {
            $show_all = true;
        } else {
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
                throw new InvalidArgumentException('Unknown domain');
            }
        }

        $page_number = (int)($_GET['page'] ?? 0);
        if ($page_number == 0) {
            throw new InvalidArgumentException('Unknown page number');
        }
    } else {
        $page_number = 1;
        if ((is_array($list_domains) and sizeof($list_domains) > 0)) {
            $fDomain = $list_domains[0];
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] == "POST") {
    $page_number = 1;
    if (isset($_POST['fDomain']) && $_POST['fDomain'] === $all_domains_value) {
        $show_all = true;
    } elseif (isset($_POST['fDomain']) && in_array($_POST['fDomain'], $list_domains)) {
        $fDomain = $_POST['fDomain'];
    }
} else {
    throw new InvalidArgumentException('Unsupported request method');
}

# When "All domains" is selected, the query is scoped to the admin's domains
# (see viewlog_domain_condition()), so the per-domain ownership check is skipped.
if (!$show_all && !(check_owner($username, $fDomain) || authentication_has_role('global-admin'))) {
    $error = 1;
    flash_error($PALANG['pViewlog_result_error']);
}

# Number of log entries per page - user-selectable but whitelisted so a crafted
# value can't request an unbounded LIMIT. The configured $CONF['page_size'] is
# always offered so existing setups keep their default.
$page_size_options = array(10, 25, 50, 100, 1000);
$default_page_size = (int)($CONF['page_size'] ?? 10);
if (!in_array($default_page_size, $page_size_options, true)) {
    $page_size_options[] = $default_page_size;
}
sort($page_size_options);

if (isset($_POST['page_size'])) {
    $requested_page_size = (int)$_POST['page_size'];
} elseif (isset($_GET['page_size'])) {
    $requested_page_size = (int)$_GET['page_size'];
} else {
    $requested_page_size = (int)($_SESSION['viewlog:page_size'] ?? $default_page_size);
}
$page_size = in_array($requested_page_size, $page_size_options, true) ? $requested_page_size : $default_page_size;
$_SESSION['viewlog:page_size'] = $page_size;

$tLog = array();
$number_of_pages = 0;
$page_window = array();

if ($error != 1) {
    $table_log = table_by_key('log');

    $params = [];
    $condition = viewlog_domain_condition($show_all, authentication_has_role('global-admin'), $fDomain, $list_domains, $params);
    $where_sql = ($condition !== '') ? "WHERE $condition" : '';


    $number_of_logs = 0;
    //get number of total logs
    $query = "SELECT count(*) as number_of_logs FROM $table_log $where_sql";


    $result = db_query_all($query, $params);
    foreach ($result as $r) {
        $number_of_logs = $r['number_of_logs'];
    }
    $number_of_pages = (int) ceil($number_of_logs / $page_size);

    # An empty result set (no matching log entries) has 0 pages; page 1 should
    # then be accepted (the template simply shows no log rows) rather than throwing.
    if ($number_of_pages > 0 && $page_number > $number_of_pages) {
        throw new InvalidArgumentException('Unknown page number');
    }

    $page_window = pagination_window($page_number, $number_of_pages, 5);

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
$url = explode("?", (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]")[0];

# Build the pagination items for the shared _pagination.tpl partial. Each entry
# is a plain page link (real href, so it works without JavaScript); disabled
# arrows and the current page render as non-clickable spans.
$domain_param = $show_all ? $all_domains_value : $fDomain;
$pagination = array();
if ($number_of_pages > 1) {
    $base = $url . '?fDomain=' . urlencode($domain_param) . '&amp;page_size=' . $page_size . '&amp;page=';
    $on_first = ($page_number <= 1);
    $on_last  = ($page_number >= $number_of_pages);

    $pagination[] = array('label' => '&laquo;',  'url' => $base . 1,                 'disabled' => $on_first, 'aria' => 'First');
    $pagination[] = array('label' => '&lsaquo;', 'url' => $base . ($page_number - 1), 'disabled' => $on_first, 'aria' => 'Previous');
    foreach ($page_window as $p) {
        if ($p === null) {
            $pagination[] = array('ellipsis' => true);
        } else {
            $pagination[] = array('label' => $p, 'url' => $base . $p, 'active' => ($p == $page_number));
        }
    }
    $pagination[] = array('label' => '&rsaquo;', 'url' => $base . ($page_number + 1), 'disabled' => $on_last, 'aria' => 'Next');
    $pagination[] = array('label' => '&raquo;',  'url' => $base . $number_of_pages,   'disabled' => $on_last, 'aria' => 'Last');
}

$smarty->assign('domain_list', $list_domains);
$smarty->assign('domain_selected', $fDomain);
$smarty->assign('tLog', $tLog, false);
$smarty->assign('fDomain', $fDomain);
$smarty->assign('show_all', $show_all);
$smarty->assign('all_domains_value', $all_domains_value);
$smarty->assign('page_size', $page_size);
$smarty->assign('page_size_options', $page_size_options);
$smarty->assign('pagination', $pagination);

$smarty->assign('smarty_template', 'viewlog');
$smarty->display('index.tpl');

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
