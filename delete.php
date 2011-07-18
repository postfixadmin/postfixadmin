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
 * File: delete.php
 * Used to delete admins, domains, mailboxes and aliases.
 * Note: if a domain is deleted, all mailboxes and aliases belonging 
 * to the domain are also removed.
 *
 * Template File: message.tpl
 *
 * Template Variables:
 *
 * none
 *
 * Form POST \ GET Variables:
 *
 * fTable
 * fDelete
 * fDomain
 */

require_once('common.php');

authentication_require_role('admin');

$SESSID_USERNAME = authentication_get_username();
$error = 0;

$fTable  = escape_string (safeget('table') ); # see the if blocks below for valid values
$fDelete = escape_string (safeget('delete'));
$fDomain = escape_string (safeget('domain'));

$error=0;

if ($fTable == "admin")
{
    authentication_require_role('global-admin');
    $fWhere = 'username';
    $result_admin = db_delete ('admin',$fWhere,$fDelete);
    $result_domain_admins = db_delete ('domain_admins',$fWhere,$fDelete);

    if ($result_admin != 1) {
        flash_error($PALANG['pAdminDelete_admin_error']);
    }
    header ("Location: list-admin.php");
    exit;
} # ($fTable == "admin")
elseif ($fTable == "domain")
{
    authentication_require_role('global-admin');
    $fWhere = 'domain';
    $result_domain_admins = db_delete ('domain_admins',$fWhere,$fDelete);
    $result_alias = db_delete ('alias',$fWhere,$fDelete);
    $result_mailbox = db_delete ('mailbox',$fWhere,$fDelete);
    $result_alias_domain = db_delete('alias_domain','alias_domain',$fDelete);
    $result_log = db_delete ('log',$fWhere,$fDelete);
    if ($CONF['vacation'] == "YES")
    {
        $result_vacation = db_delete ('vacation',$fWhere,$fDelete);
    }
    $result_domain = db_delete ('domain',$fWhere,$fDelete);

    if (!$result_domain || !domain_postdeletion($fDelete))
    {
        flash_error($PALANG['pAdminDelete_domain_error']);
    }
    header ("Location: list-domain.php");
    exit;
} # ($fTable == "domain")
elseif ($fTable == "alias_domain")
{
    authentication_require_role('global-admin');
    $table_domain_alias = table_by_key('alias_domain');
    $fWhere = 'alias_domain';
    $fDelete = $fDomain;
    if(db_delete('alias_domain',$fWhere,$fDelete) != 1) {
        flash_error($PALANG['pAdminDelete_alias_domain_error']);
    }
    header ("Location: list-domain.php");
    exit;
} # ($fTable == "alias_domain")

elseif ($fTable == "alias" or $fTable == "mailbox")
{

    if (!check_owner ($SESSID_USERNAME, $fDomain))
    {
        $error = 1;
        flash_error($PALANG['pDelete_domain_error'] . "($fDomain)!");
    }
    elseif (!check_alias_owner ($SESSID_USERNAME, $fDelete))
    {
        $error = 1;
        flash_error($PALANG['pDelete_alias_error'] . "($fDelete)!");
    }
    else
    {
        db_begin();
		/* there may be no aliases to delete */
		$result = db_query("SELECT * FROM $table_alias WHERE address = '$fDelete' AND domain = '$fDomain'");
		if($result['rows'] == 1) {
			$result = db_query ("DELETE FROM $table_alias WHERE address='$fDelete' AND domain='$fDomain'");
			db_log ($fDomain, 'delete_alias', $fDelete);
		}
            /* is there a mailbox? if do delete it from orbit; it's the only way to be sure */
            $result = db_query ("SELECT * FROM $table_mailbox WHERE username='$fDelete' AND domain='$fDomain'");
            if ($result['rows'] == 1)
            {
                $result = db_query ("DELETE FROM $table_mailbox WHERE username='$fDelete' AND domain='$fDomain'");
                $postdel_res=mailbox_postdeletion($fDelete,$fDomain);
                if ($result['rows'] != 1 || !$postdel_res)
                {
                    $error = 1;
                    $deletionMessage = $PALANG['pDelete_delete_error'] . "$fDelete (";
                    if ($result['rows']!=1)
                    {
                        $deletionMessage.='mailbox';
                        if (!$postdel_res) $deletionMessage.=', ';
                    }
                    if (!$postdel_res)
                    {
                        $deletionMessage.='post-deletion';
                    }
                    $deletionMessage.=')';
                    flash_error($deletionMessage);
                }
				db_log ($fDomain, 'delete_mailbox', $fDelete);
            }
            $result = db_query("SELECT * FROM $table_vacation WHERE email = '$fDelete' AND domain = '$fDomain'");
            if($result['rows'] == 1) {
                db_query ("DELETE FROM $table_vacation WHERE email='$fDelete' AND domain='$fDomain'");
                db_query ("DELETE FROM $table_vacation_notification WHERE on_vacation ='$fDelete' "); /* should be caught by cascade, if PgSQL */
            }
            $result = db_query("SELECT * FROM $table_quota WHERE username='$fDelete'");
            if($result['rows'] >= 1) {
                db_query ("DELETE FROM $table_quota WHERE username='$fDelete'");
            }
            $result = db_query("SELECT * FROM $table_quota2 WHERE username='$fDelete'");
            if($result['rows'] == 1) {
                db_query ("DELETE FROM $table_quota2 WHERE username='$fDelete'");
            }
        }

        if ($error != 1)
        {
            db_commit();
        } else {
            flash_error($PALANG['pDelete_delete_error'] . "$fDelete (physical mail)!");
            db_rollback();
        }
        header ("Location: list-virtual.php?domain=$fDomain");
        exit;
}
else
{
    flash_error($PALANG['invalid_parameter']);
    header("Location: main.php");
    exit;
}

# we should most probably never reach this point
$smarty->assign ('smarty_template', 'message');
flash_error("If you see this, please open a bugreport and include the exact delete.php parameters.");
$smarty->display ('index.tpl');


/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
