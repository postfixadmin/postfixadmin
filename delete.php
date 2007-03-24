<?php
//
// File: delete.php
//
// Template File: message.tpl
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
require ("./variables.inc.php");
require ("./config.inc.php");
require ("./functions.inc.php");
include ("./languages/" . check_language () . ".lang");

$SESSID_USERNAME = check_session();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   if (isset ($_GET['delete'])) $fDelete = $_GET['delete'];
   if (isset ($_GET['domain'])) $fDomain = $_GET['domain'];

   if (!check_owner ($SESSID_USERNAME, $fDomain))
   {
      $error = 1;
      $tMessage = $PALANG['pDelete_domain_error'] . "<b>$fDomain</b>!</div>";
   }
   else
   {

      $result = db_query ("DELETE FROM alias WHERE address='$fDelete' AND domain='$fDomain'");
      if ($result['rows'] != 1)
      {
         $error = 1;
         $tMessage = $PALANG['pDelete_delete_error'] . "<b>$fDelete</b> (alias)!</div>";
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
            $tMessage = $PALANG['pDelete_delete_error'] . "<b>$fDelete</b> (mailbox)!</div>";
         }
         else
         {
            db_query ("DELETE FROM vacation WHERE email='$fDelete' AND domain='$fDomain'");
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
   include ("./templates/message.tpl");
   include ("./templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/message.tpl");
   include ("./templates/footer.tpl");
}
?>
