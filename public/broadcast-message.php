<?php

/**
 * Postfix Admin
 *
 * LICENSE
 * This source file is subject to the GPL license that is bundled with
 * this package in the file LICENSE.TXT.
 *
 * Further details on the project are available at https://github.com/postfixadmin/postfixadmin
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
require_once(dirname(__DIR__) . '/model/BroadcastQueue.php');

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

$username = authentication_get_username();
$is_global_admin = authentication_has_role('global-admin');
$smtp_from_email = smtp_get_admin_email();
$allowed_domains = list_domains_for_admin($username);
$busy_domains = BroadcastQueue::getBusyDomains($allowed_domains);
$available_domains = array_values(array_diff($allowed_domains, $busy_domains));

if ($_SERVER['REQUEST_METHOD'] == "POST") {

    CsrfToken::assertValid(safepost('CSRF_Token'));

    if (safepost('action') === 'cancel') {
        $jobId = (int)safepost('job_id');
        if (!BroadcastQueue::requestCancel($jobId, $allowed_domains, $is_global_admin, $username)) {
            flash_error($PALANG['pViewlog_result_error'] ?? 'Permission denied');
            header("Location: broadcast-message.php");
            exit;
        }
        header("Location: broadcast-status.php?id=" . $jobId);
        exit;
    }

    if (safepost('action') === 'reset') {
        BroadcastQueue::resetInactive($allowed_domains, $is_global_admin, $username);
        header("Location: broadcast-message.php");
        exit;
    }

    if (empty($_POST['subject']) || empty($_POST['message']) || empty($_POST['name']) || empty($_POST['domains']) || !is_array($_POST['domains'])) {
        $error = 1;
        flash_error($PALANG['pBroadcast_error_empty']);
    } else {
        $wanted_domains = array_values(array_intersect($available_domains, $_POST['domains']));

        if (empty($wanted_domains)) {
            $error = 1;
            flash_error($PALANG['broadcast_no_available_domains']);
        } else {
            $all_domains_selected = count($wanted_domains) === count($available_domains);
            $mailboxes_only = $all_domains_selected || intval(safepost('mailboxes_only')) == 1;
            $recipients = BroadcastQueue::buildRecipients($wanted_domains, $mailboxes_only);

            if (count($recipients) == 0) {
                $error = 1;
                flash_error($PALANG['broadcast_no_recipients']);
            } else {
                $jobId = BroadcastQueue::createJob(
                    $username,
                    $smtp_from_email,
                    safepost('name'),
                    safepost('subject'),
                    safepost('message'),
                    $wanted_domains,
                    $mailboxes_only,
                    $recipients
                );

                if (!BroadcastQueue::startWorker()) {
                    flash_error($PALANG['broadcast_worker_start_failed']);
                }

                flash_info(Config::lang_f('broadcast_queued', $jobId));
                header("Location: broadcast-status.php?id=" . $jobId);
                exit;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == "GET" || $error == 1) {
    $smarty->assign('allowed_domains', $allowed_domains);
    $smarty->assign('available_domains', $available_domains);
    $smarty->assign('busy_domains', $busy_domains);
    $smarty->assign('busy_domains_text', implode(', ', $busy_domains));
    $smarty->assign('broadcast_jobs', BroadcastQueue::getJobs(20, $allowed_domains, $is_global_admin, $username));
    $smarty->assign('smtp_from_email', $smtp_from_email);
    $smarty->assign('error', $error);
    $smarty->assign('smarty_template', 'broadcast-message');
    $smarty->display('index.tpl');

    //   include ("templates/broadcast-message.tpl");
}

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
