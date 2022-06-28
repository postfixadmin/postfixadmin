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
 * File: list.php
 * List all items as a quick overview.
 *
 */

require_once('common.php');

# if (safeget('token') != $_SESSION['PFA_token']) die('Invalid token!');

$username = authentication_get_username(); # enforce login

$CONF = Config::getInstance()->getAll();
$smarty = PFASmarty::getInstance();

$table = safeget('table');

if (empty($table)) {
    die("table parameter missing or invalid.");
}

$handlerclass = ucfirst($table) . 'Handler';

if (!preg_match('/^[a-z]+$/', $table) || !file_exists(dirname(__FILE__) . "/../model/$handlerclass.php")) { # validate $table
    die("Invalid table name given!");
}

# default: domain admin restrictions
$is_superadmin = 0;

if (authentication_has_role('global-admin')) { # more permissions? Fine!
    $is_superadmin = 1;
    $username = safepost('username', safeget('username', $username)); # prefer POST over GET variable
}

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
        die($handlerclass . ' is not available for users');
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

if (count($search)) {
    $handler->getList($search, $searchmode);
} else {
    $handler->getList('');
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
        if ($field['display_in_list'] && $label != '') { # don't show fields without a label
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
} else { # HTML output

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
    $smarty->assign('domain_selected', ''); /* stop list-virtual.tpl triggering a PHP notice */

    $smarty->display('index.tpl');
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
