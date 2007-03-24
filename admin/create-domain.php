<?php
//
// File: create-domain.php
//
// Template File: admin_create-domain.tpl
//
// Template Variables:
//
// tMessage
// tDomain
// tDescription
// tAliases
// tMailboxes
// tMaxquota
// tDefaultaliases
//
// Form POST \ GET Variables:
//
// fDomain
// fDescription
// fAliases
// fMailboxes
// fMaxquota
// fDefaultaliases
//
require ("../variables.inc.php");
require ("../config.inc.php");
require ("../functions.inc.php");
include ("../languages/" . $CONF['language'] . ".lang");

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   $tAliases = $CONF['aliases'];
   $tMailboxes = $CONF['mailboxes'];
   $tMaxquota = $CONF['maxquota'];
   
   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/admin_create-domain.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $fDomain = $_POST['fDomain'];
   $fDescription = $_POST['fDescription'];
   $fAliases = $_POST['fAliases'];
   $fMailboxes = $_POST['fMailboxes'];
   if (isset ($_POST['fMaxquota'])) $fMaxquota = $_POST['fMaxquota'];
   if (isset ($_POST['fDefaultaliases'])) $fDefaultaliases = $_POST['fDefaultaliases'];
   
   if (empty ($fDomain) or domain_exist ($fDomain))
   {
      $error = 1;
      $tDomain = $_POST['fDomain'];
      $tDescription = $_POST['fDescription'];
      $tAliases = $_POST['fAliases'];
      $tMailboxes = $_POST['fMailboxes'];
      $tMaxquota = $_POST['fMaxquota'];
      $tDefaultaliases = $_POST['fDefaultaliases'];
      $pAdminCreate_domain_domain_text = $PALANG['pAdminCreate_domain_domain_text_error'];
   }
      
   if ($error != 1)
   {
      $tAliases = $CONF['aliases'];
      $tMailboxes = $CONF['mailboxes'];
      $tMaxquota = $CONF['maxquota'];

      $result = db_query ("INSERT INTO domain (domain,description,aliases,mailboxes,maxquota,created,modified) VALUES ('$fDomain','$fDescription','$fAliases','$fMailboxes','$fMaxquota',NOW(),NOW())");
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
               $result = db_query ("INSERT INTO alias (address,goto,domain,created,modified) VALUES ('$address','$goto','$fDomain',NOW(),NOW())");
            }
         }
         $tMessage = $PALANG['pAdminCreate_domain_result_succes'] . "<br />($fDomain)</br />";
      }
   }

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/admin_create-domain.tpl");
   include ("../templates/footer.tpl");
}
?>
