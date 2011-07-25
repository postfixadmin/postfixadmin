<?php /**
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
 * File: list-domain.php
 * List all domains as a quick overview.
 * Template File: adminlistdomain.tpl
 *
 * Template Variables:
 *
 * -none-
 *
 * Form POST \ GET Variables:
 *
 * fUsername
 */

require_once('common.php');

authentication_require_role('admin');

if (authentication_has_role('global-admin')) {
//if (authentication_has_role('admin')) {
   $list_admins = list_admins ();
   $is_superadmin = 1;
   $fUsername = escape_string(safepost('fUsername', safeget('username'))); # prefer POST over GET variable
   if ($fUsername != "") $admin_properties = get_admin_properties($fUsername);
} else {
   $list_admins = array(authentication_get_username());
   $is_superadmin = 0;
   $fUsername = "";
}

$list_all_domains = 0;
if (isset($admin_properties) && $admin_properties['domain_count'] == 'ALL') { # list all domains for superadmins
   $list_all_domains = 1;
} elseif (!empty($fUsername)) {
  $list_domains = list_domains_for_admin ($fUsername);
} elseif ($is_superadmin) {
   $list_all_domains = 1;
} else {
   $list_domains = list_domains_for_admin(authentication_get_username());
}

$table_domain  = table_by_key('domain');
$table_mailbox = table_by_key('mailbox');
$table_alias   = table_by_key('alias');

if ($list_all_domains == 1) {
	$where = " WHERE $table_domain.domain != 'ALL' "; # TODO: the ALL dummy domain is annoying...
} else {
	$list_domains = escape_string($list_domains);
	$where = " WHERE $table_domain.domain IN ('" . join("','", $list_domains) . "') ";
}

# fetch domain data and number of mailboxes
# PgSQL requires the extensive GROUP BY statement (https://sourceforge.net/forum/message.php?msg_id=7386240)
# and also in the field list (https://sourceforge.net/tracker/?func=detail&aid=2859165&group_id=191583&atid=937964)
# Note: future versions should auto-generate the field list based on $struct in DomainHandler (use all fields from the domain table)
$table_domain_fieldlist = "
   $table_domain.domain, $table_domain.description, $table_domain.aliases, $table_domain.mailboxes,
   $table_domain.maxquota, $table_domain.quota, $table_domain.transport, $table_domain.backupmx, $table_domain.created,
   $table_domain.modified, $table_domain.active
";

$query = "
   SELECT $table_domain_fieldlist , COUNT( DISTINCT $table_mailbox.username ) AS mailbox_count, SUM( $table_mailbox.quota ) AS total_quota
   FROM $table_domain
   LEFT JOIN $table_mailbox ON $table_domain.domain = $table_mailbox.domain
   $where
   GROUP BY $table_domain_fieldlist
   ORDER BY $table_domain.domain
   ";
$result = db_query($query);

$domain_properties = array();
while ($row = db_array ($result['result'])) {
   $domain_properties[$row['domain']] = $row;
}

# fetch number of aliases
# doing this separate is much faster than doing it in one "big" query
$query = "
   SELECT $table_domain.domain, COUNT( DISTINCT $table_alias.address ) AS alias_count 
   FROM $table_domain
   LEFT JOIN $table_alias ON $table_domain.domain = $table_alias.domain
   $where
   GROUP BY $table_domain.domain
   ORDER BY $table_domain.domain
   ";

$result = db_query($query);

while ($row = db_array ($result['result'])) {
   # add number of aliases to $domain_properties array. mailbox aliases do not count.
   $domain_properties [$row['domain']] ['alias_count'] = $row['alias_count'] - $domain_properties [$row['domain']] ['mailbox_count'];
   $domain_properties [$row['domain']] ['total_quota'] = (int) divide_quota($domain_properties [$row['domain']] ['total_quota']); # convert to MB
   if ($domain_properties [$row['domain']] ['quota'] == -1) $domain_properties [$row['domain']] ['quota'] = $PALANG['pOverview_unlimited'];
   if ($domain_properties [$row['domain']] ['maxquota'] == -1) $domain_properties [$row['domain']] ['maxquota'] = $PALANG['pOverview_unlimited'];
}

$smarty->assign ('domain_properties', $domain_properties);
if ($is_superadmin)
{
	$smarty->assign('select_options', select_options($list_admins, array ($fUsername)), false);
	$smarty->assign('smarty_template', 'adminlistdomain');
}
else
{
	$smarty->assign ('select_options', select_options($list_admins, array ($fUsername)), false);
	$smarty->assign ('smarty_template', 'overview-get');
}

$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
