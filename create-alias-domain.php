<?php
/**
 * Postfix Admin
 *
 * LICENSE
 *
 * This source file is subject to the GPL license that is bundled with 
 * this package in the file LICENSE.TXT.
 *
 * Further details on the project are available at :
 *     http://www.postfixadmin.com or http://postfixadmin.sf.net
 *
 * File: create-alias-domain.php
 * Template File: create-alias-domain.php
 * Responsible for allowing for the creation of alias domains.
 *
 * @version $Id$
 * @license GNU GPL v2 or later.
 *
 * Template Variables:
 *
 * tMessage
 *
 * Form POST \ GET Variables:
 *
 * fAliasDomain
 * fTargetDomain
 * fActive
 *
 */

require_once('common.php');

authentication_require_role('admin');

if (!boolconf('alias_domain')) {
   header("Location: " . $CONF['postfix_admin_url'] . "/main.php");
   exit;
}

$fAliasDomain = '';
$fTargetDomain= '';
$username = authentication_get_username();
$SESSID_USERNAME = $username;
if(authentication_has_role('global-admin')) {
    $list_domains = list_domains ();
}
else {
   $list_domains = list_domains_for_admin ($username);
}

# read alias_domain table to see which domains in $list_domains
# are still available as an alias- or target-domain
$list_aliases = Array();
$result = db_query ("SELECT alias_domain, target_domain FROM $table_alias_domain");
if ($result['rows'] > 0) {
   while ($row = db_array ($result['result']))
   {
      $list_aliases[ $row['alias_domain'] ] = $row['target_domain'];
   }
}

# filter available alias domains
$alias_domains = array();
foreach ($list_domains as $dom) {
   if (isset($list_aliases[$dom]) || in_array($dom,$list_aliases)) continue;
   $alias_domains[] = $dom;
}
if (count($alias_domains) == 0) {
   $error = 1;
   $tMessage = $PALANG['pCreate_alias_domain_error4'];
}

# filter available target domains
foreach ($list_domains as $dom) {
   if (isset($list_aliases[$dom])) continue;
   $target_domains[] = $dom;
}
  

if (isset ($_REQUEST['alias_domain'])) {
   $fAliasDomain = escape_string ($_REQUEST['alias_domain']);
   $fAliasDomain = strtolower ($fAliasDomain);
}
if (isset ($_REQUEST['target_domain'])) {
   $fTargetDomain = escape_string ($_REQUEST['target_domain']);
   $fTargetDomain = strtolower ($fTargetDomain);
}
//*** ?????
if (isset ($_REQUEST['active'])) {
   $fActive = (bool)$_REQUEST['active'];
} else {
   $fActive = false;
}
if (!isset ($_REQUEST['submit']))
   $fActive = true;

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    if(!authentication_has_role ('global-admin') && 
       !(check_owner ($SESSID_USERNAME, $fAliasDomain) &&
         check_owner ($SESSID_USERNAME, $fTargetDomain)))
    {
        $error = 1;
        $tMessage = $PALANG['pCreate_alias_domain_error1'];
    }

    if (isset($list_aliases[$fAliasDomain]) ||      // alias_domain is unique (primary key, a domain can't be an alias for multiple others)
        in_array($fAliasDomain,$list_aliases) ||    // an alias_domain can't be a target_domain for a third domain.
        isset($list_aliases[$fTargetDomain]) ||     // same as above, other way around
        ($fAliasDomain == $fTargetDomain) ||           // i really don't have to 
        empty($fAliasDomain) || empty($fTargetDomain)) // explain this, do i?
    {
        $error = 1;
        $tMessage = $PALANG['pCreate_alias_domain_error2'];
    }

    $sqlActive = db_get_boolean($fActive);

    if ($error != 1) {
        $result = db_query ("INSERT INTO $table_alias_domain (alias_domain,target_domain,created,modified,active) VALUES ('$fAliasDomain','$fTargetDomain',NOW(),NOW(),'$sqlActive')");
        if ($result['rows'] != 1) {
            $error = 1;
            $tMessage = $PALANG['pCreate_alias_domain_error3'];
        }
        else {
            db_log ($SESSID_USERNAME, $fAliasDomain, 'create_alias_domain', "$fAliasDomain -> $fTargetDomain");

            flash_info($PALANG['pCreate_alias_domain_success']);
            # we would have to update the list of domains available for aliasing. Doing a redirect is easier.
            header("Location: " . $CONF['postfix_admin_url'] . "/create-alias-domain.php");
            exit;
        }
    }

    $tMessage .= "<br />($fAliasDomain -> $fTargetDomain)<br />\n";
}
$smarty->assign ('alias_domains', (count($alias_domains) > 0));
$smarty->assign ('select_options_alias', select_options ($alias_domains, array ($fAliasDomain)));
$smarty->assign ('select_options_target', select_options ($target_domains, array ($fTargetDomain)));
if ($fActive)	$smarty->assign ('fActive', ' checked="checked"');
if ($error == 1)	$tMessage = '<span class="error_msg">'.$tMessage.'</span>';
$smarty->assign ('tMessage', $tMessage);
$smarty->assign ('smarty_template', 'create-alias-domain');
$smarty->display ('index.tpl');
/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
