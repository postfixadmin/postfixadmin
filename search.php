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
        if (authentication_has_role('global-admin'))
        {
            header("Location: list-virtual.php?domain=" . $fDomain ) && exit;
        }
        else
        {
            header("Location: overview.php?domain=" . $fDomain ) && exit;
        }
    }

    if ($CONF['alias_control_admin'] == "YES")
    {
        $query = "SELECT $table_alias.address,$table_alias.goto,$table_alias.modified,$table_alias.domain,$table_alias.active FROM $table_alias WHERE $table_alias.address LIKE '%$fSearch%' OR $table_alias.goto LIKE '%$fSearch%' ORDER BY $table_alias.address";
    }
    else
    {
        $query = "SELECT $table_alias.address,$table_alias.goto,$table_alias.modified,$table_alias.domain,$table_alias.active FROM $table_alias LEFT JOIN $table_mailbox ON $table_alias.address=$table_mailbox.username WHERE $table_alias.address LIKE '%$fSearch%' AND $table_mailbox.maildir IS NULL ORDER BY $table_alias.address";
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


/*

   */
    if ($CONF['vacation_control_admin'] == 'YES')
    {
        $query = ("SELECT $table_mailbox.*, $table_vacation.active AS v_active FROM $table_mailbox LEFT JOIN $table_vacation ON $table_mailbox.username=$table_vacation.email WHERE $table_mailbox.username LIKE '%$fSearch%' OR $table_mailbox.name LIKE '%$fSearch%' ORDER BY $table_mailbox.username");
        # TODO: special query for pgsql needed?
    }
    else
    {
        $query = "SELECT * FROM $table_mailbox WHERE username LIKE '%$fSearch%' OR name LIKE '%$fSearch%' ORDER BY username";
        /* TODO: special query for pgsql really needed?
		if ('pgsql'==$CONF['database_type'])
        {
            $query = "SELECT *,extract(epoch from created) as uts_created,extract(epoch from modified) as uts_modified FROM $table_mailbox WHERE username LIKE '%$fSearch%' OR name LIKE '%$fSearch%' ORDER BY username";
        } 
		*/
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
                    $row['created']=gmstrftime('%c %Z',$row['created']);
                    $row['modified']=gmstrftime('%c %Z',$row['modified']);
                    # TODO: code from admin/search.php
                    # $row['created']=gmstrftime('%c %Z',$row['uts_created']);
                    # $row['modified']=gmstrftime('%c %Z',$row['uts_modified']);
                    $row['active']=('t'==$row['active']) ? 1 : 0;
                    # TODO: code from admin/search.php
                    # unset($row['uts_created']);
                    # unset($row['uts_modified']);
				}         	
                $tMailbox[] = $row;
            }
        }
    }

include ("$incpath/templates/header.tpl");
include ("$incpath/templates/menu.tpl");
include ("$incpath/templates/search.tpl");
include ("$incpath/templates/footer.tpl");

?>
