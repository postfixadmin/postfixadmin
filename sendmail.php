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
require ("./variables.inc.php");
require ("./config.inc.php");
require ("./functions.inc.php");
include ("./languages/" . check_language () . ".lang");

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
   $fFrom = $SESSID_USERNAME;
   $fHeaders = "To: " . $fTo . "\n";
   $fHeaders .= "From: " . $fFrom . "\n";
   
   if (!empty ($PALANG['charset']))
   {
      $fHeaders .= encode_header ($_POST['fSubject'], $PALANG['charset']) . "\n";
      $fHeaders .= "MIME-Version: 1.0\n";
      $fHeaders .= "Content-Type: text/plain; charset=" . $PALANG['charset'] . "\n";
      $fHeaders .= "Content-Transfer-Encoding: 8bit\n";
   }
   else
   {
      $fHeaders .= "Subject: " . $_POST['fSubject'] . "\n\n";
   }
   
   $fHeaders .= $_POST['fBody'];

   if (empty ($fTo) or !check_email ($fTo))
	{
      $error = 1;
      $tTo = $_POST['fTo'];
      $tSubject = $_POST['fSubject'];
      $tBody = $_POST['fBody'];
      $tMessage = $PALANG['pSendmail_to_text_error'];
	}

   if ($error != 1)
   {
      if (!smtp_mail ($fTo, $fFrom, $fHeaders))
      {
         $tMessage .= $PALANG['pSendmail_result_error'];
      }
      else
      {
         $tMessage .= $PALANG['pSendmail_result_succes'];
      }
   }

   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/sendmail.tpl");
   include ("./templates/footer.tpl");
}
?>
