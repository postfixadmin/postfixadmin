<?php

class BroadcastQueue
{
    public static function activeStatuses(): array
    {
        return ['pending', 'running', 'cancelling'];
    }

    public static function statusLabel(string $status): string
    {
        $PALANG = Config::read('__LANG');
        $key = 'broadcast_status_' . $status;
        return $PALANG[$key] ?? $status;
    }

    public static function getBusyDomains(array $allowedDomains): array
    {
        if (empty($allowedDomains)) {
            return [];
        }

        $jobTable = table_by_key('broadcast_job');
        $domainTable = table_by_key('broadcast_job_domain');
        $params = [];
        $domainWhere = db_in_clause('d.domain', $allowedDomains, $params);
        $statusWhere = db_in_clause('j.status', self::activeStatuses(), $params);

        $rows = db_query_all(
            "SELECT DISTINCT d.domain FROM $domainTable d INNER JOIN $jobTable j ON d.job_id = j.id WHERE $statusWhere AND $domainWhere",
            $params
        );

        return array_column($rows, 'domain');
    }

    public static function buildRecipients(array $domains, bool $mailboxesOnly): array
    {
        if (empty($domains)) {
            return [];
        }

        $tableMailbox = table_by_key('mailbox');
        $tableAlias = table_by_key('alias');
        $params = ['active' => true];
        $domainWhere = db_in_clause('domain', $domains, $params);
        $rows = db_query_all("SELECT username FROM $tableMailbox WHERE active = :active AND $domainWhere", $params);
        $recipients = array_column($rows, 'username');

        if (!$mailboxesOnly) {
            $paramsAlias = ['active' => true];
            $domainWhereAlias = db_in_clause('domain', $domains, $paramsAlias);
            $aliasRows = db_query_all("SELECT goto FROM $tableAlias WHERE active = :active AND $domainWhereAlias", $paramsAlias);

            foreach ($aliasRows as $row) {
                foreach (explode(',', (string)$row['goto']) as $goto) {
                    $goto = trim($goto);
                    if ($goto !== '' && !self::hasLineBreak($goto)) {
                        $recipients[] = $goto;
                    }
                }
            }
        }

        $recipients = array_values(array_unique($recipients));
        sort($recipients);
        return $recipients;
    }

    public static function createJob(string $createdBy, string $sender, string $senderName, string $subject, string $body, array $domains, bool $mailboxesOnly, array $recipients): int
    {
        $pdo = db_connect();
        $jobTable = table_by_key('broadcast_job');
        $domainTable = table_by_key('broadcast_job_domain');
        $recipientTable = table_by_key('broadcast_recipient');

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO $jobTable (created_by, sender, sender_name, subject, body, mailboxes_only, status, total_count, sent_count, failed_count, cancelled_count, created, modified) " .
                "VALUES (:created_by, :sender, :sender_name, :subject, :body, :mailboxes_only, 'pending', :total_count, 0, 0, 0, " . self::nowSql() . ", " . self::nowSql() . ")"
            );
            $stmt->execute([
                'created_by' => $createdBy,
                'sender' => $sender,
                'sender_name' => $senderName,
                'subject' => $subject,
                'body' => $body,
                'mailboxes_only' => $mailboxesOnly ? 1 : 0,
                'total_count' => count($recipients),
            ]);
            $jobId = (int)$pdo->lastInsertId();

            $stmtDomain = $pdo->prepare("INSERT INTO $domainTable (job_id, domain, created) VALUES (:job_id, :domain, " . self::nowSql() . ")");
            foreach ($domains as $domain) {
                $stmtDomain->execute(['job_id' => $jobId, 'domain' => $domain]);
            }

            $stmtRecipient = $pdo->prepare("INSERT INTO $recipientTable (job_id, recipient, status, created, modified) VALUES (:job_id, :recipient, 'pending', " . self::nowSql() . ", " . self::nowSql() . ")");
            foreach ($recipients as $recipient) {
                $stmtRecipient->execute(['job_id' => $jobId, 'recipient' => $recipient]);
            }

            $pdo->commit();
            return $jobId;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function getJobs(int $limit = 20): array
    {
        $jobTable = table_by_key('broadcast_job');
        $limit = max(1, min($limit, 100));
        $jobs = db_query_all("SELECT * FROM $jobTable ORDER BY id DESC LIMIT $limit");

        foreach ($jobs as $key => $job) {
            $jobs[$key]['status_label'] = self::statusLabel($job['status']);
        }

        return $jobs;
    }

    public static function getJob(int $jobId): array
    {
        $jobTable = table_by_key('broadcast_job');
        $job = db_query_one("SELECT * FROM $jobTable WHERE id = :id", ['id' => $jobId]);

        if (!empty($job)) {
            $job['status_label'] = self::statusLabel($job['status']);
        }

        return $job;
    }

    public static function getJobDomains(int $jobId): array
    {
        $domainTable = table_by_key('broadcast_job_domain');
        $rows = db_query_all("SELECT domain FROM $domainTable WHERE job_id = :job_id ORDER BY domain", ['job_id' => $jobId]);
        return array_column($rows, 'domain');
    }

    public static function getRecipients(int $jobId, int $limit = 200): array
    {
        $recipientTable = table_by_key('broadcast_recipient');
        $limit = max(1, min($limit, 1000));
        $recipients = db_query_all("SELECT * FROM $recipientTable WHERE job_id = :job_id ORDER BY id LIMIT $limit", ['job_id' => $jobId]);

        foreach ($recipients as $key => $recipient) {
            $recipients[$key]['status_label'] = self::statusLabel($recipient['status']);
        }

        return $recipients;
    }

    public static function requestCancel(int $jobId): void
    {
        $jobTable = table_by_key('broadcast_job');
        $params = ['id' => $jobId];
        $statusWhere = db_in_clause('status', self::activeStatuses(), $params);
        db_execute("UPDATE $jobTable SET status = 'cancelling', cancel_requested = 1, modified = " . self::nowSql() . " WHERE id = :id AND $statusWhere", $params);
    }

    public static function resetInactive(): void
    {
        $jobTable = table_by_key('broadcast_job');
        $domainTable = table_by_key('broadcast_job_domain');
        $recipientTable = table_by_key('broadcast_recipient');
        $params = [];
        $activeWhere = db_in_clause('status', self::activeStatuses(), $params);
        $inactiveSql = "SELECT id FROM $jobTable WHERE NOT ($activeWhere)";

        db_execute("DELETE FROM $recipientTable WHERE job_id IN ($inactiveSql)", $params);
        db_execute("DELETE FROM $domainTable WHERE job_id IN ($inactiveSql)", $params);
        db_execute("DELETE FROM $jobTable WHERE NOT ($activeWhere)", $params);
    }

    public static function processNext(int $limit = 50, bool $dryRun = false): array
    {
        $jobTable = table_by_key('broadcast_job');
        $recipientTable = table_by_key('broadcast_recipient');
        $processed = 0;
        $sent = 0;
        $failed = 0;

        $job = db_query_one("SELECT * FROM $jobTable WHERE status IN ('pending', 'running', 'cancelling') ORDER BY id ASC LIMIT 1");
        if (empty($job)) {
            return ['job_id' => 0, 'processed' => 0, 'sent' => 0, 'failed' => 0, 'status' => 'idle'];
        }

        $jobId = (int)$job['id'];
        if ($job['status'] === 'cancelling' || (int)$job['cancel_requested'] === 1) {
            self::cancelPending($jobId);
            return ['job_id' => $jobId, 'processed' => 0, 'sent' => 0, 'failed' => 0, 'status' => 'cancelled'];
        }

        db_execute("UPDATE $jobTable SET started = CASE WHEN status = 'pending' THEN " . self::nowSql() . " ELSE started END, status = 'running', modified = " . self::nowSql() . " WHERE id = :id", ['id' => $jobId]);

        $limit = max(1, min($limit, 500));
        $items = db_query_all("SELECT * FROM $recipientTable WHERE job_id = :job_id AND status = 'pending' ORDER BY id LIMIT $limit", ['job_id' => $jobId]);

        foreach ($items as $item) {
            $freshJob = self::getJob($jobId);
            if (!empty($freshJob) && ((int)$freshJob['cancel_requested'] === 1 || $freshJob['status'] === 'cancelling')) {
                self::cancelPending($jobId);
                break;
            }

            $recipientId = (int)$item['id'];
            $recipient = $item['recipient'];
            db_execute("UPDATE $recipientTable SET status = 'sending', modified = " . self::nowSql() . " WHERE id = :id", ['id' => $recipientId]);

            $ok = $dryRun ? true : self::sendRecipient($job, $recipient);
            if ($ok) {
                db_execute("UPDATE $recipientTable SET status = 'sent', smtp_response = :response, sent_at = " . self::nowSql() . ", modified = " . self::nowSql() . " WHERE id = :id", ['id' => $recipientId, 'response' => $dryRun ? 'dry-run' : 'accepted by SMTP']);
                $sent++;
            } else {
                db_execute("UPDATE $recipientTable SET status = 'failed', error = :error, modified = " . self::nowSql() . " WHERE id = :id", ['id' => $recipientId, 'error' => 'SMTP submission failed']);
                $failed++;
            }

            $processed++;
            self::updateCounters($jobId);
        }

        self::finishIfDone($jobId);
        $finalJob = self::getJob($jobId);

        return ['job_id' => $jobId, 'processed' => $processed, 'sent' => $sent, 'failed' => $failed, 'status' => $finalJob['status'] ?? 'unknown'];
    }

    private static function sendRecipient(array $job, string $recipient): bool
    {
        if (self::hasLineBreak($recipient)) {
            return false;
        }

        mb_internal_encoding('UTF-8');
        $sender = $job['sender'];
        $name = mb_encode_mimeheader(self::headerValue($job['sender_name']), 'UTF-8', 'Q');
        $subject = mb_encode_mimeheader(self::headerValue($job['subject']), 'UTF-8', 'Q');
        $message = chunk_split(base64_encode($job['body']));
        $serverName = php_uname('n');

        $headers = 'To: ' . $recipient . "\n";
        $headers .= 'From: ' . $name . ' <' . $sender . ">\n";
        $headers .= 'Subject: ' . $subject . "\n";
        $headers .= 'MIME-Version: 1.0' . "\n";
        $headers .= 'Content-Type: text/plain; charset=UTF-8' . "\n";
        $headers .= 'Content-Transfer-Encoding: base64' . "\n";
        $headers .= 'Date: ' . date('r', time()) . "\n";
        $headers .= 'Message-ID: <' . ((string)microtime(true)) . '-' . md5($sender . $recipient) . "@{$serverName}>\n\n";
        $headers .= $message;

        return smtp_mail($recipient, $sender, $headers, smtp_get_admin_password());
    }

    private static function cancelPending(int $jobId): void
    {
        $jobTable = table_by_key('broadcast_job');
        $recipientTable = table_by_key('broadcast_recipient');

        db_execute("UPDATE $recipientTable SET status = 'cancelled', modified = " . self::nowSql() . " WHERE job_id = :job_id AND status IN ('pending', 'sending')", ['job_id' => $jobId]);
        self::updateCounters($jobId);
        db_execute("UPDATE $jobTable SET status = 'cancelled', finished = " . self::nowSql() . ", modified = " . self::nowSql() . " WHERE id = :id", ['id' => $jobId]);
    }

    private static function finishIfDone(int $jobId): void
    {
        $jobTable = table_by_key('broadcast_job');
        $recipientTable = table_by_key('broadcast_recipient');
        $pending = db_query_one("SELECT COUNT(*) AS count FROM $recipientTable WHERE job_id = :job_id AND status IN ('pending', 'sending')", ['job_id' => $jobId]);

        if ((int)$pending['count'] === 0) {
            self::updateCounters($jobId);
            db_execute("UPDATE $jobTable SET status = 'finished', finished = " . self::nowSql() . ", modified = " . self::nowSql() . " WHERE id = :id AND status != 'cancelled'", ['id' => $jobId]);
        }
    }

    private static function updateCounters(int $jobId): void
    {
        $jobTable = table_by_key('broadcast_job');
        $recipientTable = table_by_key('broadcast_recipient');
        $rows = db_query_all("SELECT status, COUNT(*) AS count FROM $recipientTable WHERE job_id = :job_id GROUP BY status", ['job_id' => $jobId]);
        $counts = ['sent' => 0, 'failed' => 0, 'cancelled' => 0];

        foreach ($rows as $row) {
            if (isset($counts[$row['status']])) {
                $counts[$row['status']] = (int)$row['count'];
            }
        }

        db_execute("UPDATE $jobTable SET sent_count = :sent, failed_count = :failed, cancelled_count = :cancelled, modified = " . self::nowSql() . " WHERE id = :id", ['id' => $jobId, 'sent' => $counts['sent'], 'failed' => $counts['failed'], 'cancelled' => $counts['cancelled']]);
    }

    private static function nowSql(): string
    {
        return db_sqlite() ? "datetime('now')" : 'now()';
    }

    private static function headerValue(string $value): string
    {
        return str_replace(["\r", "\n"], ' ', $value);
    }

    private static function hasLineBreak(string $value): bool
    {
        return str_contains($value, "\r") || str_contains($value, "\n");
    }
}
