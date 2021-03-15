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
 * File: list-virtual.php
 * List virtual users for a domain.
 *
 * Template File: list-virtual.php
 *
 * Form POST \ GET Variables:
 *
 * fDomain
 * fDisplay
 * search
 */
require_once('common.php');


authentication_require_role('admin');

$admin_username = authentication_get_username();

$list_domains = list_domains_for_admin($admin_username);

$CONF = Config::getInstance()->getAll();
$smarty = PFASmarty::getInstance();

$page_size = $CONF['page_size'];

$fDomain = safepost('fDomain', safeget('domain', safesession('list-virtual:domain')));
if (safesession('list-virtual:domain') != $fDomain) {
    unset($_SESSION['list-virtual:limit']);
}
$fDisplay = (int) safepost('limit', safeget('limit', safesession('list-virtual:limit')));
$search   = [];

if (isset($_POST['search']) && is_array($_POST['search'])) {
    $search = $_POST['search'];
} elseif (isset($_GET['search']) && is_array($_GET['search'])) {
    $search = $_GET['search'];
}

if (count($list_domains) == 0) {
    if (authentication_has_role('global-admin')) {
        flash_error($PALANG['no_domains_exist']);
    } else {
        flash_error($PALANG['no_domains_for_this_admin']);
    }
    header("Location: list.php?table=domain"); # no domains (for this admin at least) - redirect to domain list
    exit;
}

if ((is_array($list_domains) and sizeof($list_domains) > 0)) {
    if (empty($fDomain)) {
        $fDomain = escape_string($list_domains[0]);
    }
}

if (!is_string($fDomain)) {
    die(Config::Lang('invalid_parameter'));
}

if (!in_array($fDomain, $list_domains)) {
    flash_error($PALANG['invalid_parameter']);
    unset($_SESSION['list-virtual:domain']);
    header("Location: list.php?table=domain"); # invalid domain, or not owned by this admin
    exit;
}

if (!check_owner(authentication_get_username(), $fDomain)) {
    flash_error($PALANG['invalid_parameter'] . " If you see this message, please open a bugreport"); # this check is most probably obsoleted by the in_array() check above
    unset($_SESSION['list-virtual:domain']);
    header("Location: list.php?table=domain"); # domain not owned by this admin
    exit(0);
}

// store domain and page browser offset in $_SESSION so after adding/editing aliases/mailboxes we can
// take the user back to the appropriate domain listing.
$_SESSION['list-virtual:domain'] = $fDomain;
$_SESSION['prefill:alias:domain'] = $fDomain;
$_SESSION['prefill:mailbox:domain'] = $fDomain;
$_SESSION['prefill:aliasdomain:target_domain'] = $fDomain;

$_SESSION['list-virtual:limit'] = $fDisplay;

$tAliasDomains = [];
$aliasdomain_data = [];

#
# alias domain
#

if (Config::bool('alias_domain')) {
    $handler = new AliasdomainHandler(0, $admin_username);
    $formconf = $handler->webformConfig(); # might change struct
    $aliasdomain_data = array(
        'struct'    => $handler->getStruct(),
        'msg'       => $handler->getMsg(),
        'formconf'  => $formconf,
    );
    $aliasdomain_data['msg']['show_simple_search'] = false; # hide search box

    $aliasdomain_data['msg']['can_create'] = 1;

    # hide create button if all domains (of this admin) are already used as alias domains
    $handler->getList("");
    if (count($handler->result()) + 1 >= count($list_domains)) {
        $aliasdomain_data['msg']['can_create'] = 0;
    } # all domains (of this admin) are already alias domains

    # get the really requested list
    if (count($search) == 0) {
        $list_param = "alias_domain='$fDomain' OR target_domain='$fDomain'";
    } else {
        $list_param = $search;
    }

    $handler->getList($list_param);
    $tAliasDomains = $handler->result();

    foreach ($tAliasDomains as $row) {
        if ($row['alias_domain'] == $fDomain) {
            $aliasdomain_data['struct']['target_domain']['linkto'] = 'target';
            if (count($search) == 0) {
                $aliasdomain_data['struct']['alias_domain']['linkto'] = '';
                $aliasdomain_data['msg']['can_create'] = 0; # domain is already an alias domain
            }
        }
    }

    if (count($search) > 0) {
        $aliasdomain_data['struct']['target_domain']['linkto'] = 'target';
    }
}

#
# aliases
#

$table_alias = table_by_key('alias');
$table_mailbox = table_by_key('mailbox');

if (count($search) == 0 || !isset($search['_'])) {
    $search_alias = array('domain' => $fDomain);
} else {
    $search_alias = array('_' => $search['_']);
}

$handler = new AliasHandler(0, $admin_username);
$formconf = $handler->webformConfig(); # might change struct
$alias_data = array(
    'formconf'  => $formconf,
    'struct'    => $handler->getStruct(),
    'msg'       => $handler->getMsg(),
);
$alias_data['struct']['goto_mailbox']['display_in_list'] = 0; # not useful/defined for non-mailbox aliases
$alias_data['struct']['on_vacation']['display_in_list'] = 0;
$alias_data['msg']['show_simple_search'] = false; # hide search box

$handler->getList($search_alias, array(), $page_size, $fDisplay);
$pagebrowser_alias = $handler->getPagebrowser($search_alias, array());
$tAlias = $handler->result();


#
# mailboxes
#

$display_mailbox_aliases = Config::bool('alias_control_admin');
$password_expiration = Config::bool('password_expiration');

# build the sql query
$sql_select = "SELECT $table_mailbox.* ";
$sql_from   = " FROM $table_mailbox ";
$sql_join   = "";
$sql_where  = " WHERE ";
$sql_order  = " ORDER BY $table_mailbox.username ";
$sql_limit  = " LIMIT $page_size OFFSET $fDisplay";
$sql_params = [];

if (count($search) == 0 || !isset($search['_'])) {
    $sql_where .= " $table_mailbox.domain= :domain ";
    $sql_params['domain'] = $fDomain;
} else {
    $searchterm = escape_string($search['_']);
    $sql_where  .=  db_in_clause("$table_mailbox.domain", $list_domains) . " ";
    $sql_where  .= " AND ( $table_mailbox.username LIKE :searchterm OR $table_mailbox.name LIKE :searchterm ";
    $sql_params['searchterm'] = "%$searchterm%";

    if ($display_mailbox_aliases) {
        $sql_where  .= " OR $table_alias.goto LIKE :searchterm ";
    }
    $sql_where  .= " ) ";
}
if ($display_mailbox_aliases) {
    $sql_select .= ", $table_alias.goto ";
    $sql_join   .= " LEFT JOIN $table_alias ON $table_mailbox.username=$table_alias.address ";
}

if ($password_expiration) {
    $sql_select .= ", $table_mailbox.password_expiry as password_expiration ";
}

if (Config::bool('vacation_control_admin')) {
    $table_vacation = table_by_key('vacation');
    $sql_select .= ", $table_vacation.active AS v_active ";
    $sql_join   .= " LEFT JOIN $table_vacation ON $table_mailbox.username=$table_vacation.email ";
}

if (Config::bool('used_quotas') && Config::bool('new_quota_table')) {
    $table_quota2 = table_by_key('quota2');
    $sql_select .= ", $table_quota2.bytes as current ";
    $sql_join   .= " LEFT JOIN $table_quota2 ON $table_mailbox.username=$table_quota2.username ";
}

if (Config::bool('used_quotas') && (! Config::bool('new_quota_table'))) {
    $table_quota = table_by_key('quota');
    $sql_select .= ", $table_quota.current ";
    $sql_join   .= " LEFT JOIN $table_quota ON $table_mailbox.username=$table_quota.username ";
    $sql_where  .= " AND ( $table_quota.path='quota/storage' OR  $table_quota.path IS NULL ) ";
}

$mailbox_pagebrowser_query = "$sql_from\n$sql_join\n$sql_where\n$sql_order" ;

$query = "$sql_select\n$mailbox_pagebrowser_query\n$sql_limit";

$result = db_query_all($query, $sql_params);

$tMailbox = array();


$delimiter = preg_quote($CONF['recipient_delimiter'], "/");
$goto_single_rec_del = "";

foreach ($result as $row) {
    if ($display_mailbox_aliases) {
        $goto_split = explode(",", $row['goto']);
        $row['goto_mailbox'] = 0;
        $row['goto_other'] = array();

        foreach ($goto_split as $goto_single) {
            if (!empty($CONF['recipient_delimiter'])) {
                $goto_single_rec_del = preg_replace('/' .$delimiter. '[^' .$delimiter. '@]*@/', "@", $goto_single);
            }

            if ($goto_single == $row['username'] || $goto_single_rec_del == $row['username']) { # delivers to mailbox
                $row['goto_mailbox'] = 1;
            } elseif (Config::bool('vacation') && strstr($goto_single, '@' . $CONF['vacation_domain'])) { # vacation alias - TODO: check for full vacation alias
                # skip the vacation alias, vacation status is detected otherwise
            } else { # forwarding to other alias
                $row['goto_other'][] = $goto_single;
            }
        }
    }
    if (db_pgsql()) {
        $row['modified'] = date('Y-m-d H:i', strtotime($row['modified']));
        $row['created'] = date('Y-m-d H:i', strtotime($row['created']));
        $row['active']=('t'==$row['active']) ? 1 : 0;

        if (Config::bool('vacation_control_admin')) {
            if ($row['v_active'] == null) {
                $row['v_active'] = 'f';
            }
            $row['v_active']=('t'==$row['v_active']) ? 1 : 0;
        }
    }
    $tMailbox[] = $row;
}


$alias_data['msg']['can_create'] = false;
$tCanAddMailbox = false;

$tDisplay_back = "";
$tDisplay_back_show = "";
$tDisplay_up_show = "";
$tDisplay_next = "";
$tDisplay_next_show = "";

$limit = get_domain_properties($fDomain);

if (isset($limit)) {
    if ($fDisplay >= $page_size) {
        $tDisplay_back_show = 1;
        $tDisplay_back = $fDisplay - $page_size;
    }
    if (($limit['alias_count'] > $page_size) or ($limit['mailbox_count'] > $page_size)) {
        $tDisplay_up_show = 1;
    }
    if (
        (($fDisplay + $page_size) < $limit['alias_count']) or
        (($fDisplay + $page_size) < $limit['mailbox_count'])
    ) {
        $tDisplay_next_show = 1;
        $tDisplay_next = $fDisplay + $page_size;
    }

    if ($limit['aliases'] == 0) {
        $alias_data['msg']['can_create'] = true;
    } elseif ($limit['alias_count'] < $limit['aliases']) {
        $alias_data['msg']['can_create'] = true;
    }

    if ($limit['mailboxes'] == 0) {
        $tCanAddMailbox = true;
    } elseif ($limit['mailbox_count'] < $limit['mailboxes']) {
        $tCanAddMailbox = true;
    }

    $limit ['aliases']    = eval_size($limit ['aliases']);
    $limit ['mailboxes']    = eval_size($limit ['mailboxes']);
    if (Config::bool('quota')) {
        $limit ['maxquota']    = eval_size($limit ['maxquota']);
    }
}

$gen_show_status_mailbox = array();
$divide_quota = array('current' => [], 'quota' => [], 'percent' => [], 'quota_width' => []);

for ($i = 0; $i < sizeof($tMailbox); $i++) {
    $gen_show_status_mailbox[$i] = gen_show_status($tMailbox[$i]['username']);

    $divide_quota['current'][$i] = Config::Lang('unknown');
    $divide_quota['quota_width'][$i] = 0;
    $divide_quota['percent'][$i] = null;
    $divide_quota['quota'][$i] = Config::Lang('unknown');

    if (isset($tMailbox[$i]['current'])) {
        $divide_quota['current'][$i] = divide_quota($tMailbox[$i]['current']);
    }
    if (isset($tMailbox[$i]['quota'])) {
        $divide_quota['quota'][$i] = divide_quota($tMailbox[$i]['quota']);
    }
    if (isset($tMailbox[$i]['quota']) && isset($tMailbox[$i]['current'])) {
        $divide_quota['percent'][$i] = min(100, round(($divide_quota ['current'][$i]/max(1, $divide_quota ['quota'][$i]))*100));
        $divide_quota['quota_width'][$i] = ($divide_quota['percent'][$i] / 100 ) * 120; // because 100px wasn't wide enough?
    }
}



class cNav_bar {
    protected $count;
    protected $title;
    protected $limit;
    protected $page_size;
    protected $pages;
    protected $search; //* arguments

    /* @var string - appended to page link href */
    public $append_to_url = '';

    protected $have_run_init = false;
    protected $arr_prev;
    protected $arr_next;
    protected $arr_top; //* internal
    protected $anchor;

    public function __construct($aTitle, $aLimit, $aPage_size, $aPages, $aSearch) {
        $this->count = count($aPages);
        $this->title = $aTitle;
        $this->limit = $aLimit;
        $this->page_size = $aPage_size;
        $this->pages = $aPages;
        if (is_array($aSearch) && isset($aSearch['_']) && $aSearch['_'] != "") {
            $this->search = "&search[_]=" . htmlentities($aSearch['_']);
        } else {
            $this->search = "";
        }
    }

    private function init() {
        $this->anchor = 'a'.substr($this->title, 3);
        $this->append_to_url .= '#'.$this->anchor;
        ($this->limit >= $this->page_size) ? $this->arr_prev = '&nbsp;<a href="?limit='.($this->limit - $this->page_size).$this->search.$this->append_to_url.'"><img border="0" src="images/arrow-l.png" title="'.$GLOBALS ['PALANG']['pOverview_left_arrow'].'" alt="'.$GLOBALS ['PALANG']['pOverview_left_arrow'].'"/></a>&nbsp;' : $this->arr_prev = '';
        ($this->limit > 0) ? $this->arr_top = '&nbsp;<a href="?limit=0' .$this->search.$this->append_to_url.'"><img border="0" src="images/arrow-u.png" title="'.$GLOBALS ['PALANG']['pOverview_up_arrow'].'" alt="'.$GLOBALS ['PALANG']['pOverview_up_arrow'].'"/></a>&nbsp;' : $this->arr_top = '';
        (($this->limit + $this->page_size) < ($this->count * $this->page_size)) ? $this->arr_next = '&nbsp;<a href="?limit='.($this->limit + $this->page_size).$this->search.$this->append_to_url.'"><img border="0" src="images/arrow-r.png" title="'.$GLOBALS ['PALANG']['pOverview_right_arrow'].'" alt="'.$GLOBALS ['PALANG']['pOverview_right_arrow'].'"/></a>&nbsp;' : $this->arr_next = '';
        $this->have_run_init = true;
    }

    private function display_pre() {
        $ret_val = '<div class="nav_bar"';
        //$ret_val .= ' style="background-color:#ffa;"';
        $ret_val .= '>';
        $ret_val .= '<table width="730"><colgroup span="1"><col width="550"></col></colgroup> ';
        $ret_val .= '<tr><td align="left">';
        return $ret_val;
    }

    private function display_post() {
        $ret_val = '</td></tr></table></div>';
        return $ret_val;
    }

    public function display_top() {
        $ret_val = '';
        if ($this->count < 1) {
            return $ret_val;
        }
        if (!$this->have_run_init) {
            $this->init();
        }

        $ret_val .= '<a name="'.$this->anchor.'"></a>';
        $ret_val .= $this->display_pre();
        $ret_val .= '<b>'.$this->title.'</b>&nbsp;&nbsp;';

        $highlight_at = 0;

        if ($this->limit >= $this->page_size) {
            $highlight_at = $this->limit / $this->page_size ;
        }

        for ($i = 0; $i < count($this->pages); $i++) {
            $lPage = $this->pages [$i];
            if ($i == $highlight_at) {
                $ret_val .= '<b>'.$lPage.'</b>'."\n";
            } else {
                $ret_val .= '<a href="?limit='.($i * $this->page_size).$this->search.$this->append_to_url.'">'.$lPage.'</a>'."\n";
            }
        }
        $ret_val .= '</td><td valign="middle" align="right">';

        $ret_val .= $this->arr_prev;
        $ret_val .= $this->arr_top;
        $ret_val .= $this->arr_next;

        $ret_val .= $this->display_post();
        return $ret_val;
    }

    public function display_bottom() {
        $ret_val = '';
        if ($this->count < 1) {
            return $ret_val;
        }
        if (!$this->have_run_init) {
            $this->init();
        }
        $ret_val .= $this->display_pre();
        $ret_val .= '</td><td valign="middle" align="right">';

        $ret_val .= $this->arr_prev;
        $ret_val .= $this->arr_top;
        $ret_val .= $this->arr_next;

        $ret_val .= $this->display_post();
        return $ret_val;
    }
}


$nav_bar_alias = new cNav_bar($PALANG['pOverview_alias_title'], $fDisplay, $CONF['page_size'], $pagebrowser_alias, $search);
$nav_bar_alias->append_to_url = '&amp;domain='.$fDomain;

$pagebrowser_mailbox = create_page_browser("$table_mailbox.username", $mailbox_pagebrowser_query, $sql_params);
$nav_bar_mailbox = new cNav_bar($PALANG['pOverview_mailbox_title'], $fDisplay, $CONF['page_size'], $pagebrowser_mailbox, $search);
$nav_bar_mailbox->append_to_url = '&amp;domain='.$fDomain;


// this is why we need a proper template layer.
$fDomain = htmlentities($fDomain, ENT_QUOTES);

if (empty($_GET['domain'])) {
    $_GET['domain'] = '';
}
$smarty->assign('admin_list', array());
$smarty->assign('domain_list', $list_domains);
$smarty->assign('domain_selected', $fDomain);
$smarty->assign('nav_bar_alias', array('top' => $nav_bar_alias->display_top(), 'bottom' => $nav_bar_alias->display_bottom()), false);
$smarty->assign('nav_bar_mailbox', array('top' => $nav_bar_mailbox->display_top(), 'bottom' => $nav_bar_mailbox->display_bottom()), false);

$smarty->assign('fDomain', $fDomain, false);

$smarty->assign('search', $search);

$smarty->assign('list_domains', $list_domains);
$smarty->assign('limit', $limit);
$smarty->assign('tDisplay_back_show', $tDisplay_back_show);
$smarty->assign('tDisplay_back', $tDisplay_back);
$smarty->assign('tDisplay_up_show', $tDisplay_up_show);
$smarty->assign('tDisplay_next_show', $tDisplay_next_show);
$smarty->assign('tDisplay_next', $tDisplay_next);

if (Config::bool('alias_domain')) {
    $smarty->assign('tAliasDomains', $tAliasDomains);
    $smarty->assign('aliasdomain_data', $aliasdomain_data);
}

$smarty->assign('tAlias', $tAlias);
$smarty->assign('alias_data', $alias_data);

$smarty->assign('tMailbox', $tMailbox);
$smarty->assign('gen_show_status_mailbox', $gen_show_status_mailbox, false);
$smarty->assign('boolconf_used_quotas', Config::bool('used_quotas'));
$smarty->assign('divide_quota', $divide_quota);
$smarty->assign('tCanAddMailbox', $tCanAddMailbox);
$smarty->assign('display_mailbox_aliases', $display_mailbox_aliases);
if (isset($_GET ['tab'])) {
    $_SESSION ['tab'] = $_GET ['tab'];
}
//if (empty ($_GET ['tab']))
// unset ($_SESSION ['tab']);
if (!isset($_SESSION ['tab'])) {
    $_SESSION ['tab'] = 'all';
}
$smarty->assign('tab', $_SESSION ['tab']);
$smarty->assign('smarty_template', 'list-virtual');
$smarty->display('index.tpl');

function eval_size($aSize) {
    if ($aSize == 0) {
        $ret_val = Config::Lang('pOverview_unlimited');
    } elseif ($aSize < 0) {
        $ret_val = Config::Lang('pOverview_disabled');
    } else {
        $ret_val = $aSize;
    }
    return $ret_val;
}
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
