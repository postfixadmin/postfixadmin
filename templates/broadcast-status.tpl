<div class="card">
    <div class="card-header"><h4>{$PALANG.broadcast_status_title} #{$broadcast_job.id}</h4></div>
    <div class="card-body">
        <p class="text-muted">
            {$PALANG.broadcast_worker_note_1}<br/>
            {$PALANG.broadcast_worker_note_2}<br/>
            {$PALANG.broadcast_worker_note_3}
        </p>
        <dl class="row">
            <dt class="col-sm-3">{$PALANG.subject}</dt>
            <dd class="col-sm-9">{$broadcast_job.subject|escape}</dd>

            <dt class="col-sm-3">{$PALANG.broadcast_job_status}</dt>
            <dd class="col-sm-9">{$broadcast_job.status_label|escape}</dd>

            <dt class="col-sm-3">{$PALANG.broadcast_domains}</dt>
            <dd class="col-sm-9">{$broadcast_domains_text|escape}</dd>

            <dt class="col-sm-3">{$PALANG.broadcast_progress}</dt>
            <dd class="col-sm-9">
                {$PALANG.broadcast_sent}: {$broadcast_job.sent_count} /
                {$PALANG.broadcast_failed}: {$broadcast_job.failed_count} /
                {$PALANG.broadcast_cancelled}: {$broadcast_job.cancelled_count} /
                {$PALANG.broadcast_total}: {$broadcast_job.total_count}
            </dd>
        </dl>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-secondary" href="broadcast-message.php">{$PALANG.broadcast_back}</a>
        {if in_array($broadcast_job.status, $broadcast_active_statuses)}
            <form method="post" action="broadcast-status.php">
                {CSRF_Token}
                <input type="hidden" name="action" value="cancel"/>
                <input type="hidden" name="job_id" value="{$broadcast_job.id}"/>
                <button type="submit" class="btn btn-warning">{$PALANG.cancel}</button>
            </form>
        {else}
            <form method="post" action="broadcast-status.php">
                {CSRF_Token}
                <input type="hidden" name="action" value="reset"/>
                <input type="hidden" name="job_id" value="{$broadcast_job.id}"/>
                <button type="submit" class="btn btn-outline-danger">{$PALANG.broadcast_reset}</button>
            </form>
        {/if}
    </div>
</div>

<div class="card mt-4">
    <div class="card-header"><h4>{$PALANG.broadcast_report}</h4></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead>
                <tr>
                    <th>{$PALANG.pSendmail_to}</th>
                    <th>{$PALANG.broadcast_job_status}</th>
                    <th>{$PALANG.broadcast_response}</th>
                </tr>
                </thead>
                <tbody>
                {foreach from=$broadcast_recipients item=recipient}
                    <tr>
                        <td>{$recipient.recipient|escape}</td>
                        <td>{$recipient.status_label|escape}</td>
                        <td>{if $recipient.error}{$recipient.error|escape}{else}{$recipient.smtp_response|escape}{/if}</td>
                    </tr>
                {/foreach}
                </tbody>
            </table>
        </div>
    </div>
</div>
