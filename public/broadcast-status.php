<?php

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

$username = authentication_get_username();
$is_global_admin = authentication_has_role('global-admin');
$allowed_domains = list_domains_for_admin($username);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    CsrfToken::assertValid(safepost('CSRF_Token'));

    if (safepost('action') === 'cancel') {
        if (!BroadcastQueue::requestCancel((int)safepost('job_id'), $allowed_domains, $is_global_admin, $username)) {
            flash_error($PALANG['pViewlog_result_error'] ?? 'Permission denied');
            header("Location: broadcast-message.php");
            exit;
        }
    } elseif (safepost('action') === 'reset') {
        BroadcastQueue::resetInactive($allowed_domains, $is_global_admin, $username);
        header("Location: broadcast-message.php");
        exit;
    }

    header("Location: broadcast-status.php?id=" . (int)safepost('job_id'));
    exit;
}

$jobId = (int)safeget('id');
$job = BroadcastQueue::getJob($jobId);

if (empty($job) || !BroadcastQueue::canAccessJob($jobId, $allowed_domains, $is_global_admin, $username)) {
    flash_error($PALANG['broadcast_job_not_found']);
    header("Location: broadcast-message.php");
    exit;
}

$smarty->assign('broadcast_job', $job);
$broadcastDomains = BroadcastQueue::getJobDomains($jobId);
$smarty->assign('broadcast_domains', $broadcastDomains);
$smarty->assign('broadcast_domains_text', implode(', ', $broadcastDomains));
$smarty->assign('broadcast_recipients', BroadcastQueue::getRecipients($jobId));
$smarty->assign('broadcast_active_statuses', BroadcastQueue::activeStatuses());
$smarty->assign('smarty_template', 'broadcast-status');
$smarty->display('index.tpl');
