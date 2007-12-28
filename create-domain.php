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
 * File: create-domain.php
 * Allows administrators to create new domains.
 * Template File: admin_create-domain.php
 *
 * Template Variables:
 *
 * tMessage
 * tDomain
 * tDescription
 * tAliases
 * tMailboxes
 * tMaxquota
 * tDefaultaliases
 *
 * Form POST \ GET Variables:
 *
 * fDomain
 * fDescription
 * fAliases
 * fMailboxes
 * fMaxquota
 * fDefaultaliases
 */

require_once('common.php');

authentication_require_role('global-admin');


if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   $tAliases = $CONF['aliases'];
   $tMailboxes = $CONF['mailboxes'];
   $tMaxquota = $CONF['maxquota'];
   $tTransport = $CONF['transport_default'];
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   if (isset ($_POST['fDomain'])) $fDomain = escape_string ($_POST['fDomain']);
   $form_fields = array('fDescription' => '', 'fAliases' => '0', 'fMailboxes' => '0', 
      'fMaxquota' => '0', 'fTransport' => 'virtual', 'fDefaultaliases' => '0', 
      'fBackupmx' => '0');
   foreach($form_fields  as $key => $default) {
      if(isset($_POST[$key]) && (!empty($_POST[$key]))) {
         $$key = escape_string($_POST[$key]);
      }
      else {
         $$key = $default;
      }
   }


   if (empty ($fDomain) or domain_exist ($fDomain) or !check_domain ($fDomain))
   {
      $error = 1;
      $tDomain = escape_string ($_POST['fDomain']);
      $tDescription = escape_string ($_POST['fDescription']);
      $tAliases = escape_string ($_POST['fAliases']);
      $tMailboxes = escape_string ($_POST['fMailboxes']);
      if (isset ($_POST['fMaxquota'])) $tMaxquota = escape_string ($_POST['fMaxquota']);
      if (isset ($_POST['fTransport'])) $tTransport = escape_string ($_POST['fTransport']);
      if (isset ($_POST['fDefaultaliases'])) $tDefaultaliases = escape_string ($_POST['fDefaultaliases']);
      if (isset ($_POST['fBackupmx'])) $tBackupmx = escape_string ($_POST['fBackupmx']);
      if (domain_exist ($fDomain)) $pAdminCreate_domain_domain_text = $PALANG['pAdminCreate_domain_domain_text_error'];
      if (empty ($fDomain) or !check_domain ($fDomain)) $pAdminCreate_domain_domain_text = $PALANG['pAdminCreate_domain_domain_text_error2'];
   }

   if ($error != 1)
   {
      $tAliases = $CONF['aliases'];
      $tMailboxes = $CONF['mailboxes'];
      $tMaxquota = $CONF['maxquota'];

      if ($fBackupmx == "on")
      {
         $fAliases = -1;
         $fMailboxes = -1;
         $fMaxquota = -1;
         $fBackupmx = 1;
         $sqlBackupmx = db_get_boolean(true);
      }
      else
      {
         $fBackupmx = 0;
         $sqlBackupmx = db_get_boolean(false);
      }
      $sql_query = "INSERT INTO $table_domain (domain,description,aliases,mailboxes,maxquota,transport,backupmx,created,modified) VALUES ('$fDomain','$fDescription',$fAliases,$fMailboxes,$fMaxquota,'$fTransport',$sqlBackupmx,NOW(),NOW())";
      $result = db_query($sql_query);
      if ($result['rows'] != 1)
      {
         $tMessage = $PALANG['pAdminCreate_domain_result_error'] . "<br />($fDomain)<br />";
      }
      else
      {
         if ($fDefaultaliases == "on")
         {
            foreach ($CONF['default_aliases'] as $address=>$goto)
            {
               $address = $address . "@" . $fDomain;
               $result = db_query ("INSERT INTO $table_alias (address,goto,domain,created,modified) VALUES ('$address','$goto','$fDomain',NOW(),NOW())");
            }
         }
         $tMessage = $PALANG['pAdminCreate_domain_result_success'] . "<br />($fDomain)</br />";
      }
   }
}

include ("templates/header.php");
include ("templates/menu.php");
include ("templates/admin_create-domain.php");
include ("templates/footer.php");

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
