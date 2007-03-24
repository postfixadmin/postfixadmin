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
// fDelete
// fDomain
//
require ("./config.inc.php");
require ("./functions.inc.php");
include ("./languages/" . $CONF['language'] . ".lang");

$SESSID_USERNAME = check_session();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   $fDelete = $_GET['delete'];
   $fDomain = $_GET['domain'];

   if (!check_owner ($SESSID_USERNAME, $fDomain))
   {
      $error = 1;
      $tMessage = $PALANG['pDelete_domain_error'] . "<b>$fDomain</b>!</font>";
   }
   else
   {

      $result = db_query ("DELETE FROM alias WHERE address='$fDelete' AND domain='$fDomain'");
      if ($result['rows'] != 1)
      {
         $error = 1;
         $tMessage = $PALANG['pDelete_delete_error'] . "<b>$fDelete</b> (alias)!</font>";
      }
      else
      {
         db_log ($SESSID_USERNAME, $fDomain, "delete alias", $fDelete);
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
            db_log ($SESSID_USERNAME, $fDomain, "delete mailbox", $fDelete);
         }
      }
   }

   if ($error != 1)
   {
      header ("Location: overview.php?domain=$fDomain");
      exit;
   }

   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/delete.tpl");
   include ("./templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/delete.tpl");
   include ("./templates/footer.tpl");
}
?>
