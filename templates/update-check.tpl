<div class="card mb-4">
    <div class="card-header">
        <h4><span class="bi bi-arrow-repeat" aria-hidden="true"></span> {$PALANG.pUpdate_title}</h4>
    </div>
    <div class="card-body">
        <p><strong>{$PALANG.pUpdate_current_version}:</strong> {$current_version}</p>

        {if $error}
            <div class="alert alert-warning">{$error}</div>
            <a href="https://github.com/postfixadmin/postfixadmin/releases" target="_blank" rel="noopener" class="btn btn-secondary">
                <span class="bi bi-box-arrow-up-right" aria-hidden="true"></span>
                {$PALANG.pUpdate_check_releases}
            </a>
        {elseif !$update_available}
            <div class="alert alert-success">
                <span class="bi bi-check-circle" aria-hidden="true"></span>
                {$PALANG.pUpdate_up_to_date}
            </div>
        {else}
            <div class="alert alert-info">
                <span class="bi bi-info-circle" aria-hidden="true"></span>
                {$PALANG.pUpdate_available}:
                <strong>{$latest_release.normalized_version}</strong>
            </div>

            <a href="{$latest_release.html_url}" target="_blank" rel="noopener" class="btn btn-primary">
                <span class="bi bi-box-arrow-up-right" aria-hidden="true"></span>
                {$PALANG.pUpdate_view_release}
            </a>
        {/if}
    </div>
</div>

{if $update_available && $newer_releases|@count > 0}
    <div class="card">
        <div class="card-header">
            <h4><span class="bi bi-journal-text" aria-hidden="true"></span> {$PALANG.pUpdate_changelog}</h4>
        </div>
        <div class="card-body">
            {foreach from=$newer_releases item=release}
                <div class="mb-4">
                    <h5>
                        <a href="{$release.html_url}" target="_blank" rel="noopener">
                            {$release.normalized_version}
                        </a>
                        <small class="text-muted">
                            &mdash; {$release.published_at|truncate:10:''}
                        </small>
                    </h5>
                    {if $release.body}
                        <div class="ps-3 border-start">
                            <pre class="mb-0" style="white-space: pre-wrap;">{$release.body}</pre>
                        </div>
                    {/if}
                </div>
                {if !$release@last}<hr>{/if}
            {/foreach}
        </div>
    </div>
{/if}
