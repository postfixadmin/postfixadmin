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
 * File: broadcast-message.php
 * Used to send a message to _ALL_ users with mailboxes on this server.
 *
 * Template File: broadcast-message.php
 *
 * Template Variables: -none-
 *
 * Form POST \ GET Variables:
 *
 * name
 * subject
 * message
 */

require_once('common.php');

authentication_require_role('global-admin');

if ($CONF['sendmail'] != 'YES') {
    header("Location: " . $CONF['postfix_admin_url'] . "/main.php");
    exit;
}

$SESSID_USERNAME = authentication_get_username();

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    if (empty($_POST['subject']) || empty($_POST['message']) || empty($_POST['name']))
    {
        $error = 1;
    }
    else
    {
        $table_mailbox = table_by_key('mailbox');
        $table_alias = table_by_key('alias');

        $q = "select username from $table_mailbox union select goto from $table_alias " .
                    "where goto not in (select username from $table_mailbox)";

        $result = db_query ($q);
        if ($result['rows'] > 0)
        {
            mb_internal_encoding("UTF-8");
            $b_name = mb_encode_mimeheader( $_POST['name'], 'UTF-8', 'Q');
            $b_subject = mb_encode_mimeheader( $_POST['subject'], 'UTF-8', 'Q');
            $b_message = base64_encode($_POST['message']);

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
                    $tMessage .= "<br />" . $PALANG['pSendmail_result_success'] . "<br />";
                }
            }
        }
        include ("templates/header.php");
        include ("templates/menu.php");
        echo '<p>'.$PALANG['pBroadcast_success'].'</p>';
        include ("templates/footer.php");
    }
}

if ($_SERVER['REQUEST_METHOD'] == "GET" || $error == 1)
{
    include ("templates/header.php");
    include ("templates/menu.php");
    include ("templates/broadcast-message.php");
    include ("templates/footer.php");
}

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
