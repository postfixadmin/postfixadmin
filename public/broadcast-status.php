<?php

require_once('common.php');
require_once(dirname(__DIR__) . '/model/BroadcastQueue.php');

if (Config::bool('sendmail_all_admins')) {
    authentication_require_role('admin');
} else {
    authentication_require_role('global-admin');
}

$CONF = Config::getInstance()->getAll();

if ($CONF['sendmail'] != 'YES') {
    header("Location: main.php");
    exit;
}

$username = authentication_get_username();
$is_global_admin = authentication_has_role('global-admin');
$allowed_domains = list_domains_for_admin($username);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    CsrfToken::assertValid(safepost('CSRF_Token'));

    if (in_array(safepost('action'), ['retry', 'discard'], true)) {
        $jobId = (int)safepost('job_id');
        if (!BroadcastQueue::canAccessJob($jobId, $allowed_domains, $is_global_admin, $username)) {
            http_response_code(403);
            exit;
        }
        $lock = BroadcastQueue::acquireProcessLock();
        if ($lock === null) {
            flash_error($PALANG['broadcast_unknown_action_failed']);
        } else {
            try {
                if (!BroadcastQueue::resolveUnknown($jobId, (int)safepost('recipient_id'), safepost('action'), $allowed_domains, $is_global_admin, $username)) {
                    flash_error($PALANG['broadcast_unknown_action_failed']);
                }
            } finally {
                BroadcastQueue::releaseProcessLock($lock);
            }
        }
        header("Location: broadcast-status.php?id=" . $jobId);
        exit;
    }

    if (safepost('action') === 'process') {
        $jobId = (int)safepost('job_id');
        if (!BroadcastQueue::canAccessJob($jobId, $allowed_domains, $is_global_admin, $username)) {
            http_response_code(403);
            exit;
        }
        $lock = BroadcastQueue::acquireProcessLock();
        if ($lock === null) {
            if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') === false) {
                header("Location: broadcast-status.php?id=" . $jobId);
                exit;
            }
            header('Content-Type: application/json');
            echo json_encode(['busy' => true, 'csrf_token' => CsrfToken::generate()]);
            exit;
        }
        try {
            BroadcastQueue::recoverInterrupted($jobId);
            $result = BroadcastQueue::processNext(1, null, $jobId);
            $job = BroadcastQueue::getJob($jobId);
        } finally {
            BroadcastQueue::releaseProcessLock($lock);
        }
        if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') === false) {
            header("Location: broadcast-status.php?id=" . $jobId);
            exit;
        }
        header('Content-Type: application/json');
        echo json_encode([
            'status' => $job['status'] ?? 'unknown',
            'sent' => (int)($job['sent_count'] ?? 0),
            'failed' => (int)($job['failed_count'] ?? 0),
            'unknown' => BroadcastQueue::getUnknownCount($jobId),
            'cancelled' => (int)($job['cancelled_count'] ?? 0),
            'processed' => $result['processed'],
            'csrf_token' => CsrfToken::generate(),
        ]);
        exit;
    }

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

$reportPageSize = 200;
$reportPages = max(1, (int)ceil((int)$job['total_count'] / $reportPageSize));
$reportPage = max(1, min((int)safeget('page'), $reportPages));
$reportPagination = [];
if ($reportPages > 1) {
    $base = 'broadcast-status.php?id=' . $jobId . '&amp;page=';
    $reportPagination[] = ['label' => '&lsaquo;', 'url' => $base . max(1, $reportPage - 1),
        'disabled' => $reportPage === 1, 'aria' => $PALANG['pOverview_left_arrow']];
    foreach (pagination_window($reportPage, $reportPages, 2) as $page) {
        $reportPagination[] = $page === null ? ['ellipsis' => true] :
            ['label' => $page, 'url' => $base . $page, 'active' => $page === $reportPage];
    }
    $reportPagination[] = ['label' => '&rsaquo;', 'url' => $base . min($reportPages, $reportPage + 1),
        'disabled' => $reportPage === $reportPages, 'aria' => $PALANG['pOverview_right_arrow']];
}

$smarty = PFASmarty::getInstance();
$smarty->assign('broadcast_job', $job);
$smarty->assign('broadcast_pagination', $reportPagination);
$broadcastDomains = BroadcastQueue::getJobDomains($jobId);
$smarty->assign('broadcast_domains', $broadcastDomains);
$smarty->assign('broadcast_domains_text', implode(', ', $broadcastDomains));
$smarty->assign('broadcast_recipients', BroadcastQueue::getRecipients($jobId, $reportPageSize, ($reportPage - 1) * $reportPageSize));
$smarty->assign('broadcast_unknown_count', BroadcastQueue::getUnknownCount($jobId));
$smarty->assign('broadcast_unknown_recipients', BroadcastQueue::getUnknownRecipients($jobId));
$smarty->assign('broadcast_active_statuses', BroadcastQueue::activeStatuses());
$smarty->assign('smarty_template', 'broadcast-status');
$smarty->display('index.tpl');
