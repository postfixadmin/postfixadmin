<?php
// 
// Postfix Admin 
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// Licensed under GPL for more info check GPL-LICENSE.TXT
//
// File: broadcast-message.php
//
// Template File: broadcast-message.tpl
//
// Template Variables:
//
// -none-
//
// Form POST \ GET Variables:
//
// b_from
// b_subject
// b_message
//
//

require_once('../common.php');

authentication_require_role('global-admin');

$SESSID_USERNAME = authentication_get_username();

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $b_from = escape_string ($_POST['from']) ;
   $b_subject = escape_string ($_POST['subject']) ;
   $b_message = escape_string ($_POST['message']) ;

   if (strlen($b_subject) == 0 || strlen($b_message) == 0 || strlen($b_from) == 0)
   {
      $error = 1;
   }
   else
   {
      $q = "select username from mailbox union ".
         "select goto from alias ".
         "where goto not in (select username from mailbox)" ;

      $result = db_query ($q);
      if ($result['rows'] > 0)
      {
         $i = 0;
         while ($row = db_array ($result['result'])){
            $fHeaders = "To: " . $fTo . "\n";
            $fHeaders .= "From: " . $b_from . "\n";
            $fTo = $row[0];
            if (!empty ($PALANG['charset']))
            {
               $fHeaders .= "Subject: " . encode_header ($b_subject, $PALANG['charset']) . "\n";
               $fHeaders .= "MIME-Version: 1.0\n";
               $fHeaders .= "Content-Type: text/plain; charset=" . $PALANG['charset'] . "\n";
               $fHeaders .= "Content-Transfer-Encoding: 8bit\n";
            }
            else
            {
               $fHeaders .= "Subject: " . $b_subject . "\n\n";
            }

            $fHeaders .= $b_message;

            if (!smtp_mail ($fTo, $fFrom, $fHeaders))
            {
               $tMessage .= "<br />" . $PALANG['pSendmail_result_error'] . "<br />";
            }
            else
            {
               $tMessage .= "<br />" . $PALANG['pSendmail_result_succes'] . "<br />";
            }
         }
      }
      include ("../templates/header.tpl");
      include ("../templates/admin_menu.tpl");
      echo '<p>'.$PALANG['pBroadcast_success'].'</p>';
      include ("../templates/footer.tpl");
   }
}

if ($_SERVER['REQUEST_METHOD'] == "GET" || $error == 1)
{
   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/broadcast-message.tpl");
   include ("../templates/footer.tpl");
}

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
