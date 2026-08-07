<form name="broadcast-message" method="post" action="" class="form-horizontal">

    <div id="edit_form" class="card">
        <div class="card-header"><h4>{$PALANG.pBroadcast_title}</h4></div>
        <div class="card-body">
            {CSRF_Token}

            <div class="mb-3">
                <label class="col-md-4">{$PALANG.from}:</label>
                <div class="col-md-6 col-sm-8"><p class="form-control-plaintext"><em>{$smtp_from_email}</em></p></div>
            </div>
            <div class="mb-3">
                <label class="col-md-4" for="name">{$PALANG.pBroadcast_name}:</label>
                <div class="col-md-6 col-sm-8"><input class="form-control" type="text" name="name" id="name"/></div>
            </div>
            <div class="mb-3">
                <label class="col-md-4" for="subject">{$PALANG.subject}:</label>
                <div class="col-md-6 col-sm-8"><input class="form-control" type="text" name="subject" id="subject"/>
                </div>
            </div>
            <div class="mb-3">
                <label class="col-md-4" for="message">{$PALANG.message}:</label>
                <div class="col-md-6 col-sm-8"><textarea class="form-control" rows="6" cols="40" name="message"
                                                         id="message"></textarea></div>
            </div>
            <div class="mb-3">
                <label class="col-md-4"></label>
                <div class="col-md-6 col-sm-8">
                    <div class="checkbox"><label><input type="checkbox" value="1"
                                                        name="mailboxes_only" id="mailboxes_only"/>{$PALANG.broadcast_mailboxes_only}
                        </label></div>
                </div>
            </div>
            <div class="mb-3">
                <label class="col-md-4" for="domains">{$PALANG.broadcast_to_domains}</label>
                <div class="col-md-6 col-sm-8">
                    <select multiple="multiple" name="domains[]" id="domains" class="form-control">
                        {foreach from=$allowed_domains item=domain}
                            <option value="{$domain|escape}"{if in_array($domain, $busy_domains)} disabled="disabled"{else} selected="selected"{/if}>
                                {$domain|escape}{if in_array($domain, $busy_domains)} - {$PALANG.broadcast_domain_busy}{/if}
                            </option>
                        {/foreach}
                    </select>
                    {if $busy_domains|@count > 0}
                        <div class="form-text">{$PALANG.broadcast_busy_domains}: {$busy_domains_text|escape}</div>
                    {/if}
                    <div class="form-text">
                        {$PALANG.broadcast_worker_note_1}<br/>
                        {$PALANG.broadcast_worker_note_2}<br/>
                        {$PALANG.broadcast_worker_note_3}
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="btn-toolbar" role="toolbar">
                <div class="btn-group float-end">
                    <input class="btn btn-primary" type="submit" name="submit" value="{$PALANG.broadcast_queue_button}"{if $available_domains|@count == 0} disabled="disabled"{/if}/>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var domains = document.getElementById('domains');
    var mailboxesOnly = document.getElementById('mailboxes_only');

    if (!domains || !mailboxesOnly) {
        return;
    }

    function syncMailboxesOnly() {
        var selectableCount = 0;
        var allSelected = true;
        for (var i = 0; i < domains.options.length; i++) {
            if (domains.options[i].disabled) {
                continue;
            }
            selectableCount++;
            if (!domains.options[i].selected) {
                allSelected = false;
                break;
            }
        }

        if (selectableCount > 0 && allSelected) {
            mailboxesOnly.checked = true;
        }
    }

    domains.addEventListener('change', syncMailboxesOnly);
    syncMailboxesOnly();
});
</script>

{if $broadcast_jobs|@count > 0}
    <div class="card mt-4">
        <div class="card-header"><h4>{$PALANG.broadcast_jobs}</h4></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead>
                    <tr>
                        <th>{$PALANG.broadcast_job_id}</th>
                        <th>{$PALANG.subject}</th>
                        <th>{$PALANG.broadcast_job_status}</th>
                        <th>{$PALANG.broadcast_progress}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    {foreach from=$broadcast_jobs item=job}
                        <tr>
                            <td>{$job.id}</td>
                            <td>{$job.subject|escape}</td>
                            <td>{$job.status_label|escape}</td>
                            <td>
                                {$PALANG.broadcast_sent}: {$job.sent_count} |
                                {$PALANG.broadcast_failed}: {$job.failed_count} |
                                {$PALANG.broadcast_cancelled}: {$job.cancelled_count} |
                                {$PALANG.broadcast_total}: {$job.total_count}
                            </td>
                            <td class="text-end">
                                <a class="btn btn-secondary btn-sm" href="broadcast-status.php?id={$job.id}">{$PALANG.broadcast_view}</a>
                                {if $job.status == 'pending' || $job.status == 'running' || $job.status == 'cancelling'}
                                    <form method="post" action="broadcast-message.php" class="d-inline">
                                        {CSRF_Token}
                                        <input type="hidden" name="action" value="cancel"/>
                                        <input type="hidden" name="job_id" value="{$job.id}"/>
                                        <button type="submit" class="btn btn-warning btn-sm">{$PALANG.cancel}</button>
                                    </form>
                                {/if}
                            </td>
                        </tr>
                    {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer text-end">
            <form method="post" action="broadcast-message.php">
                {CSRF_Token}
                <input type="hidden" name="action" value="reset"/>
                <button type="submit" class="btn btn-outline-danger btn-sm">{$PALANG.broadcast_reset}</button>
            </form>
        </div>
    </div>
{/if}
