<?php
//
// File: sendmail.php
//
// Template File: sendmail.tpl
//
// Template Variables:
//
// tMessage
// tFrom
// tSubject
// tBody
//
// Form POST \ GET Variables:
//
// fTo
// fSubject
// fBody
//
require ("./config.inc.php");
require ("./functions.inc.php");
include ("./languages/" . $CONF['language'] . ".lang");

$SESSID_USERNAME = check_session ();

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/sendmail.tpl");
   include ("./templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $fTo = $_POST['fTo'];
   $fSubject = $_POST['fSubject'];
   $fBody = $_POST['fBody'];

   if (empty ($fTo) or !check_email ($fTo))
	{
      $error = 1;
      $tTo = $fTo;
      $tSubject = $fSubject;
      $tBody = $fBody;
      $tMessage = $PALANG['pSendmail_to_text_error'];
	}

   if ($error != 1)
   {
      if (!mail ($fTo, $fSubject, $fBody, "From: $SESSID_USERNAME"))
      {
         $tMessage = $PALANG['pSendmail_result_error'];
      }
      else
      {
         $tMessage = $PALANG['pSendmail_result_succes'];
      }
   }
   
   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/sendmail.tpl");
   include ("./templates/footer.tpl");
}
?>
