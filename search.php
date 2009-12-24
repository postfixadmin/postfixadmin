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
 * File: search.php
 * Provides a method for searching for a user/mailbox
 * Template File: search.tpl
 *
 * Template Variables:
 *
 * tAlias
 * tMailbox
 *
 * Form POST \ GET Variables:
 *
 * fSearch
 * fGo
 * fDomain
 */

require_once('common.php');

authentication_require_role('admin');
$SESSID_USERNAME = authentication_get_username();
if(authentication_has_role('global-admin')) {
    $list_domains = list_domains ();
}
else { 
    $list_domains = list_domains_for_admin ($SESSID_USERNAME);
}


$tAlias = array();
$tMailbox = array();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
    if (isset ($_GET['search'])) $fSearch = escape_string ($_GET['search']);
    if (isset ($_GET['fGo'])) $fGo = escape_string ($_GET['fGo']);
    if (isset ($_GET['fDomain'])) $fDomain = escape_string ($_GET['domain']);
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    if (isset ($_POST['search'])) $fSearch = escape_string ($_POST['search']);
    if (isset ($_POST['fGo'])) $fGo = escape_string ($_POST['fGo']);
    if (isset ($_POST['fDomain'])) $fDomain = escape_string ($_POST['fDomain']);
}

if (empty ($fSearch) /* && !empty ($fGo) */)
{
    header("Location: list-virtual.php?domain=" . $fDomain ) && exit;
}

if ($CONF['alias_control_admin'] == "YES")
{
    $query = "SELECT $table_alias.address AS address, $table_alias.goto AS goto, 
        $table_alias.modified AS modified, $table_alias.domain AS domain, $table_alias.active AS active 
        FROM $table_alias 
        WHERE address LIKE '%$fSearch%' OR goto LIKE '%$fSearch%' ORDER BY address";
}
else
{
    // find all aliases which don't have a matching entry in table_mailbox
    $query = "SELECT $table_alias.address AS address, $table_alias.goto AS goto,
        $table_alias.modified AS modified, $table_alias.domain AS domain, $table_alias.active AS active 
        FROM $table_alias LEFT JOIN $table_mailbox ON $table_alias.address=$table_mailbox.username 
        WHERE address LIKE '%$fSearch%' AND $table_mailbox.maildir IS NULL ORDER BY $table_alias.address";

}

$result = db_query ($query);
if ($result['rows'] > 0)
{
    while ($row = db_array ($result['result']))
    {
        if (check_owner ($SESSID_USERNAME, $row['domain']) || authentication_has_role('global-admin'))
        {
            if ('pgsql'==$CONF['database_type'])
            {
                $row['modified']=gmstrftime('%c %Z',$row['modified']);
                $row['active']=('t'==$row['active']) ? 1 : 0;
            }
            $tAlias[] = $row;
        }
    }
}


if ($CONF['vacation_control_admin'] == 'YES' && $CONF['vacation'] == 'YES')
{
    $query = ("SELECT $table_mailbox.*, $table_vacation.active AS v_active FROM $table_mailbox LEFT JOIN $table_vacation ON $table_mailbox.username=$table_vacation.email WHERE $table_mailbox.username LIKE '%$fSearch%' OR $table_mailbox.name LIKE '%$fSearch%' ORDER BY $table_mailbox.username");
}
else
{
    $query = "SELECT * FROM $table_mailbox WHERE username LIKE '%$fSearch%' OR name LIKE '%$fSearch%' ORDER BY username";
}

$result = db_query ($query);
if ($result['rows'] > 0)
{
    while ($row = db_array ($result['result']))
    {
        if (check_owner ($SESSID_USERNAME, $row['domain']) || authentication_has_role('global-admin'))
        {
            if ('pgsql'==$CONF['database_type'])
            {
                $row['created']=gmstrftime('%c %Z',strtotime($row['created']));
                $row['modified']=gmstrftime('%c %Z',strtotime($row['modified']));
                $row['active']=('t'==$row['active']) ? 1 : 0;
            }         	
            $tMailbox[] = $row;
        }
    }
}

$check_alias_owner = array ();

if ((is_array ($tAlias) and sizeof ($tAlias) > 0))
	for ($i = 0; $i < sizeof ($tAlias); $i++)
		$check_alias_owner [$i] = check_alias_owner ($SESSID_USERNAME, $tAlias[$i]['address']);

$divide_quota = array ();
if ((is_array ($tMailbox) and sizeof ($tMailbox) > 0))
	for ($i = 0; $i < sizeof ($tMailbox); $i++)
	{
		$divide_quota ['quota'][$i] = divide_quota ($tMailbox[$i]['quota']);
	}

for ($i = 0; $i < sizeof ($tAlias); $i++)
{
	if ((is_array ($tAlias) and sizeof ($tAlias) > 0))
	{
		$tAlias[$i]['display_address'] = $tAlias[$i]['address'];
		if (stristr($tAlias[$i]['display_address'],$fSearch))
		{
			$new_address = str_ireplace($fSearch, "<span style='background-color: lightgreen'>".$fSearch."</span>", $tAlias[$i]['display_address']);
			$tAlias[$i]['display_address'] = $new_address;
		}
		if (stristr($tAlias[$i]['goto'], $fSearch))
		{
			$tAlias[$i]['goto'] = str_ireplace($fSearch, "<span style='background-color: lightgreen'>".$fSearch."</span>", $tAlias[$i]['goto']);
		}
		($tAlias [$i]['active'] == 1) ? $tAlias [$i]['active'] = $PALANG ['YES'] : $tAlias [$i]['active'] = $PALANG ['NO'];
	}
}
for ($i = 0; $i < sizeof ($tMailbox); $i++)
{
	if ((is_array ($tMailbox) and sizeof ($tMailbox) > 0))
	{
		$tMailbox[$i]['display_username'] = $tMailbox[$i]['username'];
		if (stristr($tMailbox[$i]['display_username'],$fSearch))
		{
			$new_name = str_ireplace ($fSearch, "<span style='background-color: lightgreen'>".$fSearch."</span>", $tMailbox[$i]['display_username']);
			$tMailbox [$i]['display_username'] = $new_name;
		}
		if (stristr($tMailbox[$i]['name'],$fSearch))
		{
			$tMailbox[$i]['name'] = str_ireplace($fSearch, "<span style='background-color: lightgreen'>".$fSearch."</span>", $tMailbox[$i]['name']);
         }
		($tMailbox [$i]['active'] == 1) ? $tMailbox [$i]['active'] = $PALANG ['YES'] : $tMailbox [$i]['active'] = $PALANG ['NO'];
		($tMailbox [$i]['v_active'] == 1) ? $tMailbox [$i]['v_active'] = $PALANG ['pOverview_vacation_edit'] : $tMailbox [$i]['v_active'] = '';
	}
}

$smarty->assign ('fSearch', $fSearch);
$smarty->assign ('select_options', select_options ($list_domains, array ($list_domains[0])), false);
$smarty->assign ('tAlias', $tAlias, false);

$smarty->assign ('check_alias_owner', $check_alias_owner);
$smarty->assign ('tMailbox', $tMailbox, false);
$smarty->assign ('divide_quota', $divide_quota);

$smarty->assign ('smarty_template', 'search');
$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
