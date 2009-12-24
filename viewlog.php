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
 * File: viewlog.php
 * Shows entries from the log table to users.
 *
 * Template File: viewlog.tpl
 *
 * Template Variables:
 *
 * tMessage
 * tLog
 *
 * Form POST \ GET Variables:
 *
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

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   if ((is_array ($list_domains) and sizeof ($list_domains) > 0)) $fDomain = $list_domains[0];
} elseif ($_SERVER['REQUEST_METHOD'] == "POST") {
   if (isset ($_POST['fDomain'])) $fDomain = escape_string ($_POST['fDomain']);
} else {
   die('Unknown request method');
}

if (! (check_owner ($SESSID_USERNAME, $fDomain) || authentication_has_role('global-admin')))
{
   $error = 1;
   $tMessage = $PALANG['pViewlog_result_error'];
}

if ($error != 1)
{
   $query = "SELECT timestamp,username,domain,action,data FROM $table_log WHERE domain='$fDomain' ORDER BY timestamp DESC LIMIT 10";
   if ('pgsql'==$CONF['database_type'])
   {
      $query = "SELECT extract(epoch from timestamp) as timestamp,username,domain,action,data FROM $table_log WHERE domain='$fDomain' ORDER BY timestamp DESC LIMIT 10";
   }
   $result=db_query($query);
   if ($result['rows'] > 0)
   {
      while ($row = db_array ($result['result']))
      {
         if ('pgsql'==$CONF['database_type'])
         {
            $row['timestamp']=gmstrftime('%c %Z',$row['timestamp']);
         }
         $tLog[] = $row;
      }
   }
}

for ($i = 0; $i < count ($tLog); $i++)
	$tLog[$i]['action'] = $PALANG ['pViewlog_action_'.$tLog [$i]['action']];

$smarty->assign ('select_options', select_options ($list_domains, array ($fDomain)), false);
$smarty->assign ('tLog', $tLog,false);
$smarty->assign ('fDomain', $fDomain);
$smarty->assign ('smarty_template', 'viewlog');
$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
