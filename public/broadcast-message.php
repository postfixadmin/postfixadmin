<?php
/**
 * Postfix Admin
 *
 * LICENSE
 * This source file is subject to the GPL license that is bundled with
 * this package in the file LICENSE.TXT.
 *
 * Further details on the project are available at http://postfixadmin.sf.net
 *
 * @version $Id$
 * @license GNU GPL v2 or later.
 *
 * File: broadcast-message.php
 * Used to send a message to _ALL_ users with mailboxes on this server.
 *
 * Template File: broadcast-message.tpl
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

if (Config::bool('sendmail_all_admins')) {
    authentication_require_role('admin');
} else {
    authentication_require_role('global-admin');
}

$CONF = Config::getInstance()->getAll();
$smarty = PFASmarty::getInstance();

if ($CONF['sendmail'] != 'YES') {
    header("Location: main.php");
    exit;
}

$error = 0;

$smtp_from_email = smtp_get_admin_email();
$allowed_domains = list_domains_for_admin(authentication_get_username());

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    if (safepost('token') != $_SESSION['PFA_token']) {
        die('Invalid token!');
    }

    if (empty($_POST['subject']) || empty($_POST['message']) || empty($_POST['name']) || empty($_POST['domains']) || !is_array($_POST['domains'])) {
        $error = 1;
        flash_error($PALANG['pBroadcast_error_empty']);
    } else {
        $wanted_domains = array_intersect($allowed_domains, $_POST['domains']);

        $table_mailbox = table_by_key('mailbox');
        $table_alias = table_by_key('alias');

        $recipients = array();

        $q = "SELECT username from $table_mailbox WHERE active='" . db_get_boolean(true) . "' AND ".db_in_clause("domain", $wanted_domains);
        if (intval(safepost('mailboxes_only')) == 0) {
            $q .= " UNION SELECT goto FROM $table_alias WHERE active='" . db_get_boolean(true) . "' AND ".db_in_clause("domain", $wanted_domains)." AND goto NOT IN ($q)";
        }
        $result = db_query_all($q);
        $recipients = array_column($result, 'username');

        $recipients = array_unique($recipients);

        if (count($recipients)>0) {
            mb_internal_encoding("UTF-8");
            $b_name = mb_encode_mimeheader($_POST['name'], 'UTF-8', 'Q');
            $b_subject = mb_encode_mimeheader($_POST['subject'], 'UTF-8', 'Q');
            $b_message = chunk_split(base64_encode($_POST['message']));

            $serverName = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : php_uname('n'); // ??

            $i = 0;
            foreach ($recipients as $rcpt) {
                $fTo = $rcpt;
                $fHeaders  = 'To: ' . $fTo . "\n";
                $fHeaders .= 'From: ' . $b_name . ' <' . $smtp_from_email . ">\n";
                $fHeaders .= 'Subject: ' . $b_subject . "\n";
                $fHeaders .= 'MIME-Version: 1.0' . "\n";
                $fHeaders .= 'Content-Type: text/plain; charset=UTF-8' . "\n";
                $fHeaders .= 'Content-Transfer-Encoding: base64' . "\n";
                $fHeaders .= 'Date: ' . date('r', time()) . "\n";
                $fHeaders .= 'Message-ID: <' . microtime(true) . '-' . md5($smtp_from_email . $fTo) . "@{$serverName}>\n\n";

                $fHeaders .= $b_message;

                if (!smtp_mail($fTo, $smtp_from_email, $fHeaders, smtp_get_admin_password())) {
                    flash_error(Config::lang_f('pSendmail_result_error', $fTo));
                } else {
                    flash_info(Config::lang_f('pSendmail_result_success', $fTo));
                }
            }
        }
        flash_info($PALANG['pBroadcast_success']);
        $smarty->assign('smarty_template', 'broadcast-message');
        $smarty->display('index.tpl');
        //		echo '<p>'.$PALANG['pBroadcast_success'].'</p>';
    }
}

if ($_SERVER['REQUEST_METHOD'] == "GET" || $error == 1) {
    $smarty->assign('allowed_domains', $allowed_domains);
    $smarty->assign('smtp_from_email', $smtp_from_email);
    $smarty->assign('error', $error);
    $smarty->assign('smarty_template', 'broadcast-message');
    $smarty->display('index.tpl');

    //   include ("templates/broadcast-message.tpl");
}

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
