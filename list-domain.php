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
   $fUsername = safepost('fUsername', safeget('username')); # prefer POST over GET variable
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

if ($list_all_domains == 1) {
	$where = " WHERE domain.domain != 'ALL' "; # TODO: the ALL dummy domain is annoying...
} else {
	$list_domains = escape_string($list_domains);
	$where = " WHERE domain.domain IN ('" . join("','", $list_domains) . "') ";
}

# fetch domain data and number of mailboxes
# (PgSQL requires the extensive GROUP BY statement, https://sourceforge.net/forum/message.php?msg_id=7386240)
$query = "
   SELECT domain.* , COUNT( DISTINCT mailbox.username ) AS mailbox_count
   FROM domain
   LEFT JOIN mailbox ON domain.domain = mailbox.domain
   $where
   GROUP BY domain.domain, domain.description, domain.aliases, domain.mailboxes,
   domain.maxquota, domain.quota, domain.transport, domain.backupmx, domain.created,
   domain.modified, domain.active
   ORDER BY domain.domain
   ";
$result = db_query($query);

while ($row = db_array ($result['result'])) {
   $domain_properties[$row['domain']] = $row;
}

# fetch number of aliases
# doing this separate is much faster than doing it in one "big" query
$query = "
   SELECT domain.domain, COUNT( DISTINCT alias.address ) AS alias_count 
   FROM domain
   LEFT JOIN alias ON domain.domain = alias.domain
   $where
   GROUP BY domain.domain
   ORDER BY domain.domain
   ";

$result = db_query($query);

while ($row = db_array ($result['result'])) {
   # add number of aliases to $domain_properties array. mailbox aliases do not count.
   $domain_properties [$row['domain']] ['alias_count'] = $row['alias_count'] - $domain_properties [$row['domain']] ['mailbox_count'];
}

$smarty->assign ('domain_properties', $domain_properties);
if ($is_superadmin)
{
	$smarty->assign('select_options', select_options($list_admins, array ($fUsername)), false);
	$smarty->assign('smarty_template', 'adminlistdomain');
}
else
{
	$smarty->assign ('select_options', select_options ($list_domains, array ($_GET['domain'])));
	$smarty->assign ('smarty_template', 'overview-get');
}

$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
