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
// name
// subject
// message
//
//

require_once('../common.php');

authentication_require_role('global-admin');

$SESSID_USERNAME = authentication_get_username();

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   if (empty($_POST['subject']) || empty($_POST['message']) || empty($_POST['name']))
   {
      $error = 1;
   }
   else
   {
      $q = 'select username from mailbox union '.
         'select goto from alias '.
         'where goto not in (select username from mailbox)';

      $result = db_query ($q);
      if ($result['rows'] > 0)
      {
         $b_name = mb_encode_mimeheader( $_POST['name'], 'UTF-8', 'Q');
         $b_subject = mb_encode_mimeheader( $_POST['subject'], 'UTF-8', 'Q');
         $b_message = encode_base64($_POST['message']);

         $i = 0;
         while ($row = db_array ($result['result'])) {
            $fTo = $row[0];
            $fHeaders  = 'To: ' . $fTo . "\n";
            $fHeaders .= 'From: ' . $b_name . ' <' . $CONF['admin_email'] . ">\n";
            $fHeaders .= 'Subject: ' . $b_subject . "\n";
            $fHeaders .= 'MIME-Version: 1.0' . "\n";
            $fHeaders .= 'Content-Type: text/plain; charset=UTF-8' . "\n";
            $fHeaders .= 'Content-Transfer-Encoding: base64' . "\n";

            $fHeaders .= $b_message;

            if (!smtp_mail ($fTo, $CONF['admin_email'], $fHeaders))
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
