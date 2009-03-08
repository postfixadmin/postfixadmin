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
 * Template File: message.php
 *
 * Template Variables:
 *
 * tMessage
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

/**
 * delete_alias
 *  Action: Delete an alias
 * @param String $alias- alias to delete.
 * @param String $domain - domain of the alias
 * @param boolean $force_delete - deletes the alias from the table if true,
 *                                checks if the alias is real and act accordlying if false.
 *                                Default is false. 
 * @return String username (e.g. foo@example.com)
 */
function delete_alias ($alias, $domain, $force_delete = false)
{
    global $table_alias, $table_mailbox;
    $real_alias = true;

    if (! $force_delete)
    {
        $result = db_query ("SELECT 1 FROM $table_mailbox
            WHERE username='$alias' AND domain='$domain'");
        if ($result['rows'] != 0)
        {
            /* If the alias is a real mailbox as well, remove all its real aliases and keep
             * only the address */
            $real_alias = false;
        }
    }

    if ($force_delete or $real_alias)
    {
        $result = db_query ("DELETE FROM $table_alias WHERE address='$alias' AND domain='$domain'");
    }
    else
    {
        $result = db_query ("UPDATE $table_alias SET goto='$alias',modified=NOW()
            WHERE address='$alias' AND domain='$domain'");
    }

    if ($result['rows'] != 1)
    {
        $tMessage = $PALANG['pDelete_delete_error'] . "<b>$alias</b> (alias)!</span>";

        return false;
    }
    else
    {
        db_log ($SESSID_USERNAME, $fDomain, 'delete_alias', $fDelete);
    }

    return true;
}

if ($fTable == "admin")
{
    authentication_require_role('global-admin');
    $fWhere = 'username';
    $result_admin = db_delete ($table_admin,$fWhere,$fDelete);
    $result_domain_admins = db_delete ($table_domain_admins,$fWhere,$fDelete);

    if (!($result_admin == 1) and ($result_domain_admins >= 0))
    {
        $error = 1;
        $tMessage = $PALANG['pAdminDelete_admin_error'];
    }
    else
    {
        $url = "list-admin.php";
        header ("Location: $url");
    }
} # ($fTable == "admin")
elseif ($fTable == "domain")
{
    authentication_require_role('global-admin');
    $fWhere = 'domain';
    $result_domain_admins = db_delete ($table_domain_admins,$fWhere,$fDelete);
    $result_alias = db_delete ($table_alias,$fWhere,$fDelete);
    $result_mailbox = db_delete ($table_mailbox,$fWhere,$fDelete);
    $result_log = db_delete ($table_log,$fWhere,$fDelete);
    if ($CONF['vacation'] == "YES")
    {
        $result_vacation = db_delete ($table_vacation,$fWhere,$fDelete);
    }
    $result_domain = db_delete ($table_domain,$fWhere,$fDelete);

    if (!$result_domain || !domain_postdeletion($fDelete))
    {
        $error = 1;
        $tMessage = $PALANG['pAdminDelete_domain_error'];
    }
    else
    {
        $url = "list-domain.php";
        header ("Location: $url");
    }
} # ($fTable == "domain")
elseif ($fTable == "alias_domain")
{
    authentication_require_role('global-admin');
    $table_domain_alias = table_by_key('alias_domain');
    $fWhere = 'alias_domain';
    $fDelete = $fDomain;
    if(db_delete($table_domain_alias,$fWhere,$fDelete)) {
        $url = "list-domain.php";
        header ("Location: $url");
    }
} # ($fTable == "alias_domain")

elseif ($fTable == "mailbox")
{

    if (!check_owner ($SESSID_USERNAME, $fDomain))
    {
        $error = 1;
        $tMessage = $PALANG['pDelete_domain_error'] . "<b>$fDomain</b>!</span>";
    }
    elseif (!check_alias_owner ($SESSID_USERNAME, $fDelete))
    {
        $error = 1;
        $tMessage = $PALANG['pDelete_alias_error'] . "<b>$fDelete</b>!</span>";
    }
    else
    {
        if ($CONF['database_type'] == "pgsql") db_query('BEGIN');

        $error = delete_alias ($fDelete, $fDomain, $force_delete = true) ? 0 : 1;
        if (! $error)
        {
            /* is there a mailbox? if do delete it from orbit; it's the only way to be sure */
            $result = db_query ("SELECT * FROM $table_mailbox WHERE username='$fDelete' AND domain='$fDomain'");
            if ($result['rows'] == 1)
            {
                $result = db_query ("DELETE FROM $table_mailbox WHERE username='$fDelete' AND domain='$fDomain'");
                $postdel_res=mailbox_postdeletion($fDelete,$fDomain);
                if ($result['rows'] != 1 || !$postdel_res)
                {
                    $error = 1;
                    $tMessage = $PALANG['pDelete_delete_error'] . "<b>$fDelete</b> (";
                    if ($result['rows']!=1)
                    {
                        $tMessage.='mailbox';
                        if (!$postdel_res) $tMessage.=', ';
                    }
                    if (!$postdel_res)
                    {
                        $tMessage.='post-deletion';
                    }
                    $tMessage.=')</span>';
                }
            }
            $result = db_query("SELECT * FROM $table_vacation WHERE email = '$fDelete' AND domain = '$fDomain'");
            if($result['rows'] == 1) {
                db_query ("DELETE FROM $table_vacation WHERE email='$fDelete' AND domain='$fDomain'");
                db_query ("DELETE FROM $table_vacation_notification WHERE on_vacation ='$fDelete' "); /* should be caught by cascade, if PgSQL */
            }
        }

        if ($error != 1)
        {
            if ($CONF['database_type'] == "pgsql") db_query('COMMIT');
            header ("Location: list-virtual.php?domain=$fDomain");
            exit;
        } else {
            $tMessage = $PALANG['pDelete_delete_error'] . "<b>$fDelete</b> (physical mail)!</span>";
            if ($CONF['database_type'] == "pgsql") db_query('ROLLBACK');
        }
    } # ($fTable == "mailbox")
}
elseif ($fTable == "alias") {
    $error = delete_alias ($fDelete, $fDomain) ? 0 : 1;

    if ($error != 1)
    {
        header ("Location: list-virtual.php?domain=$fDomain");
        exit;
    } else {
        $tMessage = $PALANG['pDelete_delete_error'] . "<b>$fDelete</b> (alias)!</span>";
    }
}
else
{
    flash_error($PALANG['invalid_parameter']);
}


include ("templates/header.php");
include ("templates/menu.php");
include ("templates/message.php");
include ("templates/footer.php");

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
