<div class="card mb-4">
    <div class="card-header">
        <h4><span class="bi bi-arrow-repeat" aria-hidden="true"></span> {$PALANG.pUpdate_check_title|default:'Update Check'}</h4>
    </div>
    <div class="card-body">
        <p><strong>{$PALANG.pUpdate_current_version|default:'Current version'}:</strong> {$current_version}</p>

        {if $error}
            <div class="alert alert-warning">{$error}</div>
        {elseif !$update_available}
            <div class="alert alert-success">
                <span class="bi bi-check-circle" aria-hidden="true"></span>
                {$PALANG.pUpdate_up_to_date|default:'You are running the latest version.'}
            </div>
        {else}
            <div class="alert alert-info">
                <span class="bi bi-info-circle" aria-hidden="true"></span>
                {$PALANG.pUpdate_available|default:'A newer version is available'}:
                <strong>{$latest_release.normalized_version}</strong>
            </div>

            {if !$has_phar}
                <div class="alert alert-danger">
                    <span class="bi bi-exclamation-triangle" aria-hidden="true"></span>
                    {$PALANG.pUpdate_no_phar|default:'The PHP PharData extension is not available. Cannot extract update archives.'}
                </div>
            {/if}

            {if !$is_writable}
                <div class="alert alert-danger">
                    <span class="bi bi-exclamation-triangle" aria-hidden="true"></span>
                    {$PALANG.pUpdate_not_writable|default:'The installation directory is not writable by the web server. Cannot apply updates automatically.'}
                    <br><code>{$install_dir}</code>
                </div>
            {/if}

            {if !$can_download}
                <div class="alert alert-danger">
                    <span class="bi bi-exclamation-triangle" aria-hidden="true"></span>
                    {$PALANG.pUpdate_no_download|default:'Neither cURL nor allow_url_fopen is available. Cannot download updates. Install php-curl or enable allow_url_fopen in php.ini.'}
                </div>
            {/if}

            {if $tarball_url && $is_writable && $has_phar && $can_download}
                <form method="post" action="">
                    {CSRF_Token}
                    <input type="hidden" name="action" value="apply_update">
                    <input type="hidden" name="download_url" value="{$tarball_url}">
                    <input type="hidden" name="update_version" value="{$latest_release.normalized_version}">

                    <div class="alert alert-warning">
                        <span class="bi bi-exclamation-triangle" aria-hidden="true"></span>
                        {$PALANG.pUpdate_backup_warning|default:'Make sure you have a backup of your database before proceeding. Your config.local.php will not be overwritten.'}
                    </div>

                    <button type="submit" class="btn btn-primary" onclick="return confirm('{$PALANG.pUpdate_confirm|default:'Are you sure you want to apply this update?'}');">
                        <span class="bi bi-download" aria-hidden="true"></span>
                        {$PALANG.pUpdate_apply|default:'Download & Apply Update'}
                    </button>
                </form>
            {elseif !$tarball_url}
                <div class="alert alert-warning">
                    <span class="bi bi-exclamation-triangle" aria-hidden="true"></span>
                    {$PALANG.pUpdate_no_tarball|default:'No self-contained release archive is available for this version. You may need to update manually.'}
                    <br>
                    <a href="{$latest_release.html_url}" target="_blank" rel="noopener" class="btn btn-secondary mt-2">
                        <span class="bi bi-box-arrow-up-right" aria-hidden="true"></span>
                        {$PALANG.pUpdate_view_release|default:'View release on GitHub'}
                    </a>
                </div>
            {/if}
        {/if}
    </div>
</div>

{if $update_available && $newer_releases|@count > 0}
    <div class="card">
        <div class="card-header">
            <h4><span class="bi bi-journal-text" aria-hidden="true"></span> {$PALANG.pUpdate_changelog|default:'Changelog'}</h4>
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
