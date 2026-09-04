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
 * File: list.php
 * List all items as a quick overview.
 *
 */

require_once('common.php');


$username = authentication_get_username(); # enforce login

$CONF = Config::getInstance()->getAll();
$smarty = PFASmarty::getInstance();

$table = safeget('table');

if (empty($table)) {
    throw new InvalidArgumentException("table parameter missing or invalid.");
}

$handlerclass = ucfirst($table) . 'Handler';

if (!preg_match('/^[a-z]+$/', $table) || !file_exists(dirname(__FILE__) . "/../model/$handlerclass.php")) { # validate $table
    throw new InvalidArgumentException("Invalid table name given!");
}

# default: domain admin restrictions
$is_superadmin = 0;

if (authentication_has_role('global-admin')) { # more permissions? Fine!
    $is_superadmin = 1;
    $username = safepost('username', safeget('username', $username)); # prefer POST over GET variable
}

// some default, see https://github.com/postfixadmin/postfixadmin/pull/868
$smarty->assign('id_div', 'main_div');

$is_admin = authentication_has_role('admin');

$handler = new $handlerclass(0, $username, $is_admin);

$formconf = $handler->webformConfig();

$list_admins = array($username);
if ($is_superadmin && $formconf['required_role'] != 'global-admin') { # 'simulate admin' dropdown doesn't make sense for superadmin-only modules
    $list_admins = array_keys(list_admins());
}

if ($is_admin) {
    authentication_require_role($formconf['required_role']);
} else {
    if (empty($formconf['user_hardcoded_field'])) {
        throw new InvalidArgumentException($handlerclass . ' is not available for users');
    }
}

$search = [];
$searchmode = [];
if (isset($_GET['search']) && is_array($_GET['search'])) {
    $search = $_GET['search'];
} elseif (isset($_SESSION["search_$table"]) && is_array($_SESSION["search_$table"])) {
    $search = $_SESSION["search_$table"];
}

if (isset($_GET['searchmode']) && is_array($_GET['searchmode'])) {
    $searchmode = $_GET['searchmode'];
} elseif (isset($_SESSION["searchmode_$table"]) && is_array($_SESSION["searchmode_$table"])) {
    $searchmode = $_SESSION["searchmode_$table"];
}

if (array_key_exists('reset_search', $_GET)) {
    $search = array();
    $searchmode = array();
}

$_SESSION["search_$table"] = $search;
$_SESSION["searchmode_$table"] = $searchmode;

$dns_filter = '';
$dns_inactive_count = 0;
$dns_check_mode = DomainDnsStatus::configuredMode();
if ($table === 'domain' && $dns_check_mode > 0) {
    $dns_filter = safeget('dns_filter') === 'inactive' ? 'inactive' : '';
    $dns_inactive_count = DomainDnsStatus::countInactive(list_domains_for_admin($username));
}

$condition = $search;
if ($dns_filter === 'inactive') {
    $condition['dns_active'] = 0;
}
if ($condition === []) {
    $condition = '';
}
$pagination = [];

if (safeget('output') == 'csv') {
    // CSV exports keep the historical behavior and contain the complete list.
    $handler->getList($condition, $searchmode);
} else {
    $page_size = (int)$CONF['page_size'];
    $pagebrowser = $handler->getPagebrowser($condition, $searchmode);
    $last_offset = max(0, (count($pagebrowser) - 1) * $page_size);
    $offset = max(0, (int)safeget('limit', '0'));
    $offset = min($last_offset, intdiv($offset, $page_size) * $page_size);

    $handler->getList($condition, $searchmode, $page_size, $offset);

    $pagination_params = ['table' => $table];
    if ($is_superadmin && $formconf['required_role'] != 'global-admin') {
        $pagination_params['username'] = $username;
    }
    if (count($search)) {
        $pagination_params['search'] = $search;
    }
    if (count($searchmode)) {
        $pagination_params['searchmode'] = $searchmode;
    }
    if ($dns_filter === 'inactive') {
        $pagination_params['dns_filter'] = 'inactive';
    }

    $pagination = page_browser_pagination(
        $pagebrowser,
        $offset,
        $page_size,
        $pagination_params,
        'main_div',
        [
            'first' => $PALANG['pOverview_up_arrow'],
            'previous' => $PALANG['pOverview_left_arrow'],
            'next' => $PALANG['pOverview_right_arrow'],
        ]
    );
}

$items = $handler->result();


if (count($handler->errormsg)) {
    flash_error($handler->errormsg);
}
if (count($handler->infomsg)) {
    flash_error($handler->infomsg);
}

$fDomain = safepost('fDomain', safeget('domain', safesession('list-virtual:domain')));

if (safeget('output') == 'csv') {
    $out = fopen('php://output', 'w');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment;filename=' . $table . '.csv');
    print "\xEF\xBB\xBF"; # utf8 byte-order to indicate the file is utf8 encoded
    print "\n";

    if (!defined('ENT_HTML401')) { # for compability for PHP < 5.4.0
        define('ENT_HTML401', 0);
    }

    # print column headers as csv
    $header = array();
    $columns = array();
    foreach ($handler->getStruct() as $key => $field) {
        $label = trim($field['label']);
        if ($field['display_in_list'] && $label != '' && $label != ' ') { # don't show fields without a label and those with a whitespace
            $header[] = html_entity_decode($label, ENT_COMPAT | ENT_HTML401, 'UTF-8');
            $columns[] = $key;
        }
    }
    fputcsv($out, $header);

    # print items as csv
    foreach ($items as $item) {
        $fields = array();

        // skip domains that do not match selected domain (see: https://github.com/postfixadmin/postfixadmin/issues/404)
        if (!empty($fDomain) && $item['domain'] != $fDomain) {
            continue;
        }
        foreach ($columns as $column) {
            $values = $item[$column];
            if (is_array($values)) {
                $values = implode(',', $values);
            }
            $fields[] = $values;
        }
        fputcsv($out, $fields);
    }
    fclose($out);
    exit(0);
}
# HTML output

$smarty->assign('admin_list', $list_admins);
$smarty->assign('admin_selected', $username);
$smarty->assign('smarty_template', 'list');
$smarty->assign('struct', $handler->getStruct());
$smarty->assign('msg', $handler->getMsg());
$smarty->assign('table', $table);
$smarty->assign('items', $items);
$smarty->assign('id_field', $handler->getId_field());
$smarty->assign('formconf', $formconf);
$smarty->assign('search', $search);
$smarty->assign('searchmode', $searchmode);
$smarty->assign('pagination', $pagination);
$smarty->assign('pagination_label', $PALANG[$handler->getMsg()['list_header'] ?? ''] ?? 'Pagination');
$smarty->assign('dns_filter', $dns_filter);
$smarty->assign('dns_inactive_count', $dns_inactive_count);
$smarty->assign('dns_check_mode', $dns_check_mode);
$smarty->assign('domain_selected', ''); /* stop list-virtual.tpl triggering a PHP notice */

$smarty->display('index.tpl');


/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
