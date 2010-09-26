<?php
/** 
 * Postfix Admin 
 * 
 * LICENSE 
 * This source file is subject to the GPL license that is bundled with  
 * this package in the file LICENSE.TXT. 
 * 
 * Further details on the project are available at : 
 *     http://www.postfixadmin.com or http://postfixadmin.sf.net 
 * 
 * @version $Id$ 
 * @license GNU GPL v2 or later. 
 * 
 * File: list-virtual.php
 * List virtual users for a domain.
 *
 * Template File: list-virtual.php
 *
 * Template Variables:
 *
 * tMessage
 * tAlias
 * tMailbox
 *
 * Form POST \ GET Variables:
 *
 * fDomain
 * fDisplay
 */
require_once('common.php');


authentication_require_role('admin');

$fDomain = false;
$SESSID_USERNAME = authentication_get_username();

if (authentication_has_role('global-admin')) {
   $list_domains = list_domains ();
   $is_superadmin = 1;
} else {
   $list_domains = list_domains_for_admin(authentication_get_username());
   $is_superadmin = 0;
}
$tAlias = array();
$tMailbox = array();
$fDisplay = 0;
$page_size = $CONF['page_size'];

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   if (isset ($_GET['domain'])) $fDomain = escape_string ($_GET['domain']);
   if (isset ($_GET['limit'])) $fDisplay = intval ($_GET['limit']);
   $search = escape_string(safeget('search'));
}
else
{
   if (isset ($_POST['fDomain'])) $fDomain = escape_string ($_POST['fDomain']);
   if (isset ($_POST['limit'])) $fDisplay = intval ($_POST['limit']);
   $search = escape_string(safepost('search'));
}

if (count($list_domains) == 0) {
#   die("no domains");
   flash_error( $PALANG['invalid_parameter'] );
   header("Location: list-domain.php"); # no domains (for this admin at least) - redirect to domain list
   exit;
}

if ((is_array ($list_domains) and sizeof ($list_domains) > 0)) {
    if (empty ($fDomain)) {
        $fDomain = $list_domains[0];
    }
}

if(!in_array($fDomain, $list_domains)) {
   flash_error( $PALANG['invalid_parameter'] );
   header("Location: list-domain.php"); # invalid domain, or not owned by this admin
   exit;
}

if (!check_owner(authentication_get_username(), $fDomain)) { 
   flash_error( $PALANG['invalid_parameter'] . " If you see this message, please open a bugreport"); # this check is most probably obsoleted by the in_array() check above
   header("Location: list-domain.php"); # domain not owned by this admin
   exit(0);
}

// store fDomain in $_SESSION so after adding/editing aliases/mailboxes we can
// take the user back to the appropriate domain listing. (see templates/menu.tpl)
if($fDomain) {
    $_SESSION['list_virtual_sticky_domain'] = $fDomain;
}

#
# alias domain
#

# TODO: add search support for alias domains

if (boolconf('alias_domain')) {
   # Alias-Domains
   # first try to get a list of other domains pointing
   # to this currently chosen one (aka. alias domains)
   $query = "SELECT $table_alias_domain.alias_domain,$table_alias_domain.target_domain,$table_alias_domain.modified,$table_alias_domain.active FROM $table_alias_domain WHERE target_domain='$fDomain' ORDER BY $table_alias_domain.alias_domain LIMIT $fDisplay, $page_size";
   if ('pgsql'==$CONF['database_type'])
   {
      $query = "SELECT alias_domain,target_domain,extract(epoch from modified) as modified,active FROM $table_alias_domain WHERE target_domain='$fDomain' ORDER BY alias_domain LIMIT $page_size OFFSET $fDisplay";
   }
   $result = db_query ($query);
   $tAliasDomains = array();
   if ($result['rows'] > 0)
   {
      while ($row = db_array ($result['result']))
      {
         if ('pgsql'==$CONF['database_type'])
         {
            $row['modified']=gmstrftime('%c %Z',$row['modified']);
            $row['active']=('t'==$row['active']) ? 1 : 0;
         }
         $tAliasDomains[] = $row;
      }
   } 
   # now let's see if the current domain itself is an alias for another domain
   $query = "SELECT $table_alias_domain.alias_domain,$table_alias_domain.target_domain,$table_alias_domain.modified,$table_alias_domain.active FROM $table_alias_domain WHERE alias_domain='$fDomain'";
   if ('pgsql'==$CONF['database_type'])
   {
      $query = "SELECT alias_domain,target_domain,extract(epoch from modified) as modified,active FROM $table_alias_domain WHERE alias_domain='$fDomain'";
   }
   $result = db_query ($query);
   $tTargetDomain = "";
   if ($result['rows'] > 0)
   {
      if($row = db_array ($result['result']))
      {
         if ('pgsql'==$CONF['database_type'])
         {
            $row['modified']=gmstrftime('%c %Z',$row['modified']);
            $row['active']=('t'==$row['active']) ? 1 : 0;
         }
         $tTargetDomain = $row;
      }
   }
}

#
# aliases
#

if ($search == "") {
   $sql_domain = " $table_alias.domain='$fDomain' ";
   $sql_where  = "";
} else {
   $sql_domain = db_in_clause("$table_alias.domain", $list_domains);
   $sql_where  = " AND ( address LIKE '%$search%' OR goto LIKE '%$search%' ) ";
}

$query = "SELECT $table_alias.address,
                 $table_alias.goto,
                 $table_alias.modified,
                 $table_alias.active
          FROM $table_alias LEFT JOIN $table_mailbox ON $table_alias.address=$table_mailbox.username
          WHERE ($sql_domain AND $table_mailbox.maildir IS NULL $sql_where)
          ORDER BY $table_alias.address LIMIT $fDisplay, $page_size";
if ('pgsql'==$CONF['database_type'])
{
   # TODO: is the different query for pgsql really needed? The mailbox query below also works with both...
   $query = "SELECT address,
                    goto,
                    extract(epoch from modified) as modified,
                    active
                    FROM $table_alias
					WHERE $sql_domain AND NOT EXISTS(SELECT 1 FROM $table_mailbox WHERE username=$table_alias.address $sql_where)
                    ORDER BY address LIMIT $page_size OFFSET $fDisplay";
}

$result = db_query ($query);
if ($result['rows'] > 0)
{
   while ($row = db_array ($result['result']))
   {
      if ('pgsql'==$CONF['database_type'])
      {
		 //. at least in my database, $row['modified'] already looks like : 2009-04-11 21:38:10.75586+01,
		 // while gmstrftime expects an integer value. strtotime seems happy though.
		 //$row['modified']=gmstrftime('%c %Z',$row['modified']);
		 $row['modified'] = date('Y-m-d H:i', strtotime($row['modified']));
         $row['active']=('t'==$row['active']) ? 1 : 0;
      }
      $tAlias[] = $row;
   }
}


#
# mailboxes
#

$display_mailbox_aliases = boolconf('alias_control_admin');

# build the sql query
$sql_select = "SELECT $table_mailbox.* ";
$sql_from   = " FROM $table_mailbox ";
$sql_join   = "";
$sql_where  = " WHERE ";
$sql_order  = " ORDER BY $table_mailbox.username ";
$sql_limit  = " LIMIT $page_size OFFSET $fDisplay";

if ($search == "") {
    $sql_where  .= " $table_mailbox.domain='$fDomain' ";
} else {
    $sql_where  .=  db_in_clause("$table_mailbox.domain", $list_domains) . " ";
    $sql_where  .= " AND ( $table_mailbox.username LIKE '%$search%' OR $table_mailbox.name LIKE '%$search%' ";
    if ($display_mailbox_aliases) {
        $sql_where  .= " OR $table_alias.goto LIKE '%$search%' ";
    } 
    $sql_where  .= " ) "; # $search is already escaped
}
if ($display_mailbox_aliases) {
    $sql_select .= ", $table_alias.goto ";
    $sql_join   .= " LEFT JOIN $table_alias ON $table_mailbox.username=$table_alias.address ";
}

if (boolconf('vacation_control_admin')) {
    $sql_select .= ", $table_vacation.active AS v_active ";
    $sql_join   .= " LEFT JOIN $table_vacation ON $table_mailbox.username=$table_vacation.email ";
}

if (boolconf('used_quotas') && boolconf('new_quota_table')) {
    $sql_select .= ", $table_quota2.bytes as current ";
    $sql_join   .= " LEFT JOIN $table_quota2 ON $table_mailbox.username=$table_quota2.username ";
}

if (boolconf('used_quotas') && ( ! boolconf('new_quota_table') ) ) {
    $sql_select .= ", $table_quota.current ";
    $sql_join   .= " LEFT JOIN $table_quota ON $table_mailbox.username=$table_quota.username ";
    $sql_where  .= " AND ( $table_quota.path='quota/storage' OR  $table_quota.path IS NULL ) ";
}

$query = "$sql_select\n$sql_from\n$sql_join\n$sql_where\n$sql_order\n$sql_limit";

$result = db_query ($query);

if ($result['rows'] > 0)
{
   while ($row = db_array ($result['result']))
   {
	  if ($display_mailbox_aliases) {
            $goto_split = split(",", $row['goto']);
            $row['goto_mailbox'] = 0;
            $row['goto_other'] = array();
            
            foreach ($goto_split as $goto_single) {
                if ($goto_single == $row['username']) { # delivers to mailbox
                    $row['goto_mailbox'] = 1;
                } elseif (boolconf('vacation') && strstr($goto_single, '@' . $CONF['vacation_domain']) ) { # vacation alias - TODO: check for full vacation alias
                    # skip the vacation alias, vacation status is detected otherwise
                } else { # forwarding to other alias
                    $row['goto_other'][] = $goto_single;
                }
            }
      }
      if ('pgsql'==$CONF['database_type'])
      {
         // XXX
         $row['modified'] = date('Y-m-d H:i', strtotime($row['modified']));
         $row['created'] = date('Y-m-d H:i', strtotime($row['created']));
         $row['active']=('t'==$row['active']) ? 1 : 0;
         if($row['v_active'] == NULL) { 
            $row['v_active'] = 'f';
         }
         $row['v_active']=('t'==$row['v_active']) ? 1 : 0; 
      }
      $tMailbox[] = $row;
   }
}

$tCanAddAlias = false;
$tCanAddMailbox = false;

# TODO: needs reworking for $search...
$limit = get_domain_properties($fDomain);
if (isset ($limit)) {
   if ($fDisplay >= $page_size) {
      $tDisplay_back_show = 1;
      $tDisplay_back = $fDisplay - $page_size;
   }
   if (($limit['alias_count'] > $page_size) or ($limit['mailbox_count'] > $page_size)) {
      $tDisplay_up_show = 1;
   }      
   if ((($fDisplay + $page_size) < $limit['alias_count']) or 
      (($fDisplay + $page_size) < $limit['mailbox_count'])) 
   {
      $tDisplay_next_show = 1;
      $tDisplay_next = $fDisplay + $page_size;
   }

   if($limit['aliases'] == 0) {
      $tCanAddAlias = true;
   }
   elseif($limit['alias_count'] < $limit['aliases']) {
      $tCanAddAlias = true;
   }
   if($limit['mailboxes'] == 0) {
      $tCanAddMailbox = true;
   }
   elseif($limit['mailbox_count'] < $limit['mailboxes']) {
      $tCanAddMailbox = true;
   }

   $limit ['aliases']	= eval_size ($limit ['aliases']);
   $limit ['mailboxes']	= eval_size ($limit ['mailboxes']);
   $limit ['maxquota']	= eval_size ($limit ['maxquota']);
}

$gen_show_status = array ();
$check_alias_owner = array ();

if ((is_array ($tAlias) and sizeof ($tAlias) > 0))
	for ($i = 0; $i < sizeof ($tAlias); $i++)
	{
		$gen_show_status [$i] = gen_show_status($tAlias[$i]['address']);
		$check_alias_owner [$i] = check_alias_owner($SESSID_USERNAME, $tAlias[$i]['address']);
	}

$gen_show_status_mailbox = array ();
$divide_quota = array ();
if ((is_array ($tMailbox) and sizeof ($tMailbox) > 0))
	for ($i = 0; $i < sizeof ($tMailbox); $i++)
	{
		$gen_show_status_mailbox [$i] = gen_show_status($tMailbox[$i]['username']);
		$divide_quota ['current'][$i] = divide_quota ($tMailbox[$i]['current']);
		$divide_quota ['quota'][$i] = divide_quota ($tMailbox[$i]['quota']);
	}
	
class cNav_bar
{
	var $count, $title, $limit, $page_size, $pages;	//* arguments
	var $url;	//* manually
	var $fInit, $arr_prev, $arr_next, $arr_top;	//* internal
	var $anchor;
	function cNav_bar ($aCount, $aTitle, $aLimit, $aPage_size, $aPages)
	{
		$this->count = $aCount;
		$this->title = $aTitle;
		$this->limit = $aLimit;
		$this->page_size = $aPage_size;
		$this->pages = $aPages;
		$this->url = '';
		$this->fInit = false;
	}
	function init ()
	{
		$this->anchor = 'a'.substr ($this->title, 3);
		$this->url .= '#'.$this->anchor;
		($this->limit >= $this->page_size) ? $this->arr_prev = '&nbsp;<a href="?limit='.($this->limit - $this->page_size).$this->url.'"><img border="0" src="images/arrow-l.png" title="'.$GLOBALS ['PALANG']['pOverview_left_arrow'].'" alt="'.$GLOBALS ['PALANG']['pOverview_left_arrow'].'"/></a>&nbsp;' : $this->arr_prev = '';
		($this->limit > 0) ? $this->arr_top = '&nbsp;<a href="?limit=0'.$this->url.'"><img border="0" src="images/arrow-u.png" title="'.$GLOBALS ['PALANG']['pOverview_up_arrow'].'" alt="'.$GLOABLS ['PALANG']['pOverview_up_arrow'].'"/></a>&nbsp;' : $this->arr_top = '';
		(($this->limit + $this->page_size) < ($this->count * $this->page_size)) ? $this->arr_next = '&nbsp;<a href="?limit='.($this->limit + $this->page_size).$this->url.'"><img border="0" src="images/arrow-r.png" title="'.$GLOBALS ['PALANG']['pOverview_right_arrow'].'" alt="'.$GLOBALS ['PALANG']['pOverview_right_arrow'].'"/></a>&nbsp;' : $this->arr_next = '';
		$this->fInit = true;
	}
	function display_pre ()
	{
		$ret_val = '<div class="nav_bar"';
//$ret_val .= ' style="background-color:#ffa;"';
		$ret_val .= '>';
		$ret_val .= '<table width="730"><colgroup span="1"><col width="550"></col></colgroup> ';
		$ret_val .= '<tr><td align="left">';
		return $ret_val;
	}
	function display_post ()
	{
		$ret_val = '</td></tr></table></div>';
		return $ret_val;
	}
	function display_top ()
	{
		$ret_val = '';
		if ($this->count < 1)
			return $ret_val;
		if (!$this->fInit)
			$this->init ();
			
		$ret_val .= '<a name="'.$this->anchor.'"></a>';
		$ret_val .= $this->display_pre ();
		$ret_val .= '<b>'.$this->title.'</b>&nbsp;&nbsp;';
		($this->limit >= $this->page_size) ? $highlight_at = $this->limit / $this->page_size : $highlight_at = 0;

		for ($i = 0; $i < count ($this->pages); $i++)
		{
			$lPage = $this->pages [$i];
			if ($i == $highlight_at)
				$lPage = '<b>'.$lPage.'</b>';
			$ret_val .= '<a href="?limit='.($i * $this->page_size).$this->url.'">'.$lPage.'</a>'."\n";
		}
		$ret_val .= '</td><td valign="middle" align="right">';

		$ret_val .= $this->arr_prev;
		$ret_val .= $this->arr_top;
		$ret_val .= $this->arr_next;

		$ret_val .= $this->display_post ();
		return $ret_val;
	}
	function display_bottom ()
	{
		$ret_val = '';
		if ($this->count < 1)
			return $ret_val;
		if (!$this->fInit)
			$this->init ();
		$ret_val .= $this->display_pre ();
		$ret_val .= '</td><td valign="middle" align="right">';

		$ret_val .= $this->arr_prev;
		$ret_val .= $this->arr_top;
		$ret_val .= $this->arr_next;

		$ret_val .= $this->display_post ();
		return $ret_val;
	}
}

$nav_bar_alias = new cNav_bar ($limit['alias_pgindex_count'], $PALANG['pOverview_alias_title'], $fDisplay, $CONF['page_size'], $limit['alias_pgindex']);
$nav_bar_alias->url = '&amp;domain='.$fDomain;

$nav_bar_mailbox = new cNav_bar ($limit['mbox_pgindex_count'], $PALANG['pOverview_mailbox_title'], $fDisplay, $CONF['page_size'], $limit['mbox_pgindex']);
$nav_bar_mailbox->url = '&amp;domain='.$fDomain;
//print $nav_bar_alias->display_top ();

	
// this is why we need a proper template layer.
$fDomain = htmlentities($fDomain, ENT_QUOTES);

$smarty->assign ('select_options', select_options ($list_domains, array ($fDomain)), false);
$smarty->assign ('nav_bar_alias', array ('top' => $nav_bar_alias->display_top (), 'bottom' => $nav_bar_alias->display_bottom ()), false);
$smarty->assign ('nav_bar_mailbox', array ('top' => $nav_bar_mailbox->display_top (), 'bottom' => $nav_bar_mailbox->display_bottom ()), false);

$smarty->assign ('fDomain', $fDomain, false);

$smarty->assign ('search', $search);

$smarty->assign ('list_domains', $list_domains);
$smarty->assign ('limit', $limit);
$smarty->assign ('tDisplay_back_show', $tDisplay_back_show);
$smarty->assign ('tDisplay_back', $tDisplay_back);
$smarty->assign ('tDisplay_up_show', $tDisplay_up_show);
$smarty->assign ('tDisplay_next_show', $tDisplay_next_show);
$smarty->assign ('tDisplay_next', $tDisplay_next);

if(sizeof ($tAliasDomains) > 0)
	$smarty->assign ('tAliasDomains', $tAliasDomains);

if(is_array($tTargetDomain))
{
	$smarty->assign ('tTargetDomain', $tTargetDomain);
	$smarty->assign ('PALANG_pOverview_alias_domain_target', sprintf($PALANG['pOverview_alias_domain_target'], $fDomain));
}
$smarty->assign ('tAlias', $tAlias);
$smarty->assign ('gen_show_status', $gen_show_status, false);
$smarty->assign ('check_alias_owner', $check_alias_owner);
$smarty->assign ('tCanAddAlias', $tCanAddAlias);
$smarty->assign ('tMailbox', $tMailbox);
$smarty->assign ('gen_show_status_mailbox', $gen_show_status_mailbox, false);
$smarty->assign ('boolconf_used_quotas', boolconf('used_quotas'));
$smarty->assign ('divide_quota', $divide_quota);
$smarty->assign ('tCanAddMailbox', $tCanAddMailbox);
$smarty->assign ('display_mailbox_aliases', $display_mailbox_aliases);
if (isset ($_GET ['tab']))
	$_SESSION ['tab'] = $_GET ['tab'];
//if (empty ($_GET ['tab']))
//	unset ($_SESSION ['tab']);
if (!isset ($_SESSION ['tab']))
		$_SESSION ['tab'] = 'mailbox';
$smarty->assign ('tab', $_SESSION ['tab']);
$smarty->assign ('smarty_template', 'list-virtual');
$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
