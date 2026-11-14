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
                {$PALANG.broadcast_sent}: <span id="broadcast-sent">{$broadcast_job.sent_count}</span> /
                {$PALANG.broadcast_failed}: <span id="broadcast-failed">{$broadcast_job.failed_count}</span> /
                {$PALANG.broadcast_cancelled}: <span id="broadcast-cancelled">{$broadcast_job.cancelled_count}</span> /
                {$PALANG.broadcast_unknown}: <span id="broadcast-unknown">{$broadcast_unknown_count}</span> /
                {$PALANG.broadcast_total}: {$broadcast_job.total_count}
            </dd>
        </dl>
        {if $broadcast_unknown_count > 0}
            <p class="alert alert-warning">{$PALANG.broadcast_unknown_note}</p>
        {/if}
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-secondary" href="broadcast-message.php">{$PALANG.broadcast_back}</a>
        {if $broadcast_job.status == 'cancelling'}
            {* waiting for the active batch to stop *}
        {elseif in_array($broadcast_job.status, $broadcast_active_statuses)}
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

{if in_array($broadcast_job.status, $broadcast_active_statuses)}
    <p id="broadcast-process-error" class="alert alert-danger d-none">{$PALANG.broadcast_process_error}</p>
    <form id="broadcast-process-form" method="post" action="broadcast-status.php">
        {CSRF_Token}
        <input type="hidden" name="action" value="process"/>
        <input type="hidden" name="job_id" value="{$broadcast_job.id}"/>
        <button type="submit" class="btn btn-primary">{$PALANG.broadcast_continue}</button>
    </form>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('broadcast-process-form');
        let stopped = false;
        let processed = 0;
        async function processBatch() {
            if (stopped) {
                return;
            }
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: {Accept: 'application/json'}
                });
                if (!response.ok) {
                    throw new Error('Broadcast request failed');
                }
                const result = await response.json();
                if (!result.busy) {
                    document.getElementById('broadcast-sent').textContent = result.sent;
                    document.getElementById('broadcast-failed').textContent = result.failed;
                    document.getElementById('broadcast-cancelled').textContent = result.cancelled;
                    document.getElementById('broadcast-unknown').textContent = result.unknown;
                }
                if (result.status === 'finished' || result.status === 'cancelled') {
                    window.location.reload();
                    return;
                }
                processed += result.processed || 0;
                if (processed >= 10) {
                    window.location.reload();
                    return;
                }
                window.setTimeout(processBatch, result.busy ? 2000 : 100);
            } catch (error) {
                stopped = true;
                document.getElementById('broadcast-process-error').classList.remove('d-none');
            }
        }
        window.setTimeout(processBatch, 100);
    });
    </script>
{/if}

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
