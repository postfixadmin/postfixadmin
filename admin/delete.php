<?php
//
// File: delete.php
//
// Template File: delete.tpl
//
// Template Variables:
//
// tMessage
//
// Form POST \ GET Variables:
//
// fTable
// fWhere
// fDelete
// fDomain
//
require ("../variables.inc.php");
require ("../config.inc.php");
require ("../functions.inc.php");
include ("../languages/" . $CONF['language'] . ".lang");

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   if (isset ($_GET['table'])) $fTable = $_GET['table'];
   if (isset ($_GET['where'])) $fWhere = $_GET['where'];
   if (isset ($_GET['delete'])) $fDelete = $_GET['delete'];
   if (isset ($_GET['domain'])) $fDomain = $_GET['domain'];
   
   if (empty ($fTable))
   {
      $error = 1;
   }
   
   if ($fTable == "domain")
   {
      $result_domain = db_delete ("domain",$fWhere,$fDelete);
      $result_domain_admins = db_delete ("domain_admins",$fWhere,$fDelete);
      $result_alias = db_delete ("alias",$fWhere,$fDelete);
      $result_mailbox = db_delete ("mailbox",$fWhere,$fDelete);
      $result_log = db_delete ("log",$fWhere,$fDelete);
      $result_vacation = db_delete ("vacation",$fWhere,$fDelete);

      if (!($result_domain == 1) and ($result_domain_admins >= 0) and ($result_alias >= 0) and ($result_mailbox >= 0) and ($result_vacation >= 0))
      {
         $error = 1;
         $tMessage = $PALANG['pAdminDelete_domain_error'];
      }
      else
      {
         $url = "list-domain.php";
      }
   }
   
   if ($fTable == "admin")
   {
      $result_admin = db_delete ("admin",$fWhere,$fDelete);
      $result_domain_admins = db_delete ("domain_admins",$fWhere,$fDelete);
      
      if (!($result_admin == 1) and ($result_domain_admins >= 0))
      {
         $error = 1;
         $tMessage = $PALANG['pAdminDelete_admin_error'];
      }
      else
      {
         $url = "list-admin.php";
      }
   }

   if ($fTable == "alias" or $fTable == "mailbox")
   {
      $result = db_query ("DELETE FROM alias WHERE address='$fDelete' AND domain='$fDomain'");
      if ($result['rows'] != 1)
      {
         $error = 1;
         $tMessage = $PALANG['pDelete_delete_error'] . "<b>$fDelete</b> (alias)!</font>";
      }
      else
      {
         $url = "list-virtual.php?domain=$fDomain";
         db_log ($CONF['admin_email'], $fDomain, "delete alias", $fDelete);
      }

      $result = db_query ("SELECT * FROM mailbox WHERE username='$fDelete' AND domain='$fDomain'");
      if ($result['rows'] == 1)
      {
         $result = db_query ("DELETE FROM mailbox WHERE username='$fDelete' AND domain='$fDomain'");
         if ($result['rows'] != 1)
         {
            $error = 1;
            $tMessage = $PALANG['pDelete_delete_error'] . "<b>$fDelete</b> (mailbox)!</font>";
         }
         else
         {
            $url = "list-virtual.php?domain=$fDomain";
            db_log ($CONF['admin_email'], $fDomain, "delete mailbox", $fDelete);
         }
      }
   }

   if ($error != 1)
   {
      header ("Location: $url");
      exit;
   }

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/delete.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   include ("../templates/header.tpl");
   include ("../templates/menu.tpl");
   include ("../templates/delete.tpl");
   include ("../templates/footer.tpl");
}
?>
