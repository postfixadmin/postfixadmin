{* Print a list cell value, optionally wrapped in a link. All parameters are plain values and are escaped here. *}
{function name=list_cell_link href='' title='' text=''}{if $href != ''}<a href="{$href}"{if $title != ''} title="{$title}"{/if}>{$text}</a>{else}{$text}{/if}{/function}
<div id="{$id_div}" class="card">

    {if ($admin_list|count > 1) || ($table == 'domain' && $dns_check_mode > 0)}
        <div class="card-header">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                {if ($admin_list|count > 1)}
                    <form name="frmOverview" method="post" action="" class="d-inline">
                        {html_options name='username' output=$admin_list values=$admin_list selected=$admin_selected onchange="this.form.submit();"}
                        <noscript><input class="button" type="submit" name="go" value="{$PALANG.go}"/></noscript>
                    </form>
                {/if}
                {if $table == 'domain' && $dns_check_mode > 0}
                    <form method="post" action="refresh-domain-dns.php" class="d-inline">
                        <input type="hidden" name="username" value="{$admin_selected}">
                        {if isset($search.dns_active) && $search.dns_active == 0}<input type="hidden" name="search_dns_active" value="0">{/if}
                        {CSRF_Token}
                        <button type="submit" class="btn btn-sm btn-secondary" title="{$PALANG.dns_refresh}" aria-label="{$PALANG.dns_refresh}">
                            <span class="bi bi-arrow-clockwise" aria-hidden="true"></span> DNS
                        </button>
                    </form>
                    {if $dns_inactive_count > 0}
                        <a class="btn btn-sm btn-danger" href="list.php?table=domain{if $admin_list|count > 1}&amp;username={$admin_selected|escape:'url'}{/if}&amp;search%5Bdns_active%5D=0">
                            <span class="bi bi-exclamation-triangle" aria-hidden="true"></span> DNS ({$dns_inactive_count})
                        </a>
                    {/if}
                    {if isset($search.dns_active) && $search.dns_active == 0}
                        <a class="btn btn-sm btn-outline-secondary" href="list.php?table=domain&amp;reset_search=1{if $admin_list|count > 1}&amp;username={$admin_selected|escape:'url'}{/if}">
                            <span class="bi bi-x-lg" aria-hidden="true"></span>
                        </a>
                    {/if}
                    <small class="text-muted">{$PALANG.dns_oldest_check}: {$dns_oldest_checked|default:$PALANG.dns_not_checked}</small>
                {/if}
            </div>
        </div>
    {/if}

    {if $msg.show_simple_search}
        {#form_search#|raw} {* trusted HTML from configs/menu.conf *}
    {/if}

    {if $msg.show_simple_search}
        {if ($search|count > 0)}
            <div class='searchparams'>
                <p>{$PALANG.searchparams}
                    {foreach key=key item=field from=$search}
                        <span>{if $struct.$key.label}{$struct.$key.label}{else}{$key}{/if}
                            {if isset($searchmode.$key)}{$searchmode.$key}{else}={/if} {$field}
                </span>
                    {/foreach}
                    <span><a href="list.php?table={$table}&reset_search=1">[x]</a></span>
            </div>
        {/if}
    {/if}

    {if $msg.list_header}
        <div class="card-header text-center fw-bold">
            {$PALANG.{$msg.list_header}}
        </div>
    {/if}

    <div class="card-body">
        {if isset($pagination) && $pagination}
            <div class="mb-3">
                {include file="_pagination.tpl" pagination=$pagination pagination_label=$pagination_label}
            </div>
        {/if}
        {if $table == 'domain'}
            <div class="domain-list-scroll">
        {elseif $table == 'alias' || $table == 'aliasdomain'}
            <div class="virtual-list-scroll">
        {/if}
        <table class="table table-hover table-sm table-striped align-middle" id='admin_table'>
            <!-- TODO: 'admin_table' needed because of CSS for table header -->
            <thead>
            <tr>
                {foreach key=key item=field from=$struct}
                    {if $field.display_in_list == 1 && $field.label}{* don't show fields without a label *}
                        <th class="list-col-{$key}">{$field.label}</th>
                    {/if}
                {/foreach}
                <th class="list-col-action">&nbsp;</th>
                <th class="list-col-action">&nbsp;</th>
            </tr>
            </thead>
            <tbody>
            {foreach key=itemkey from=$items item=item}
                <tr>

                    {foreach key=key item=field from=$struct}
                        {if $field.display_in_list == 1 && $field.label}

                            {* linktext/linkto/linktitle are plain (unescaped) values; list_cell_link escapes them on output *}
                            {assign "linktext" $item.$key}
                            {assign "linkto" ""}
                            {assign "linktitle" ""}
                            {if $field.linkto != '' && ($item.$id_field != '' || $item.$id_field > 0) }
                                {* unquoted on purpose: a modifier inside "{...}" would be auto-escaped here and again on output *}
                                {assign "linkto" $field.linkto|replace:'%s':($item.$id_field|escape:url)} {* TODO: use label field instead *}
                                {if $table == 'domain' && $key == 'domain'}
                                    {if isset($struct.maxquota) && $struct.maxquota.display_in_list == 0 && isset($item.maxquota)}
                                        {if $item.maxquota < 0}
                                            {assign "maxquota_text" "{$PALANG.quota_not_managed}"}
                                        {elseif $item.maxquota == 0}
                                            {assign "maxquota_text" "{$PALANG.pOverview_unlimited}"}
                                        {else}
                                            {assign "maxquota_text" "{$item.maxquota} MB"}
                                        {/if}
                                        {assign "linktitle" "{$PALANG.pAdminList_domain_maxquota}: {$maxquota_text}"}
                                    {/if}
                                    {if isset($struct.password_expiry) && $struct.password_expiry.display_in_list == 0 && isset($item.password_expiry)}
                                        {if $linktitle != ''}{assign "linktitle" "{$linktitle}\n"}{/if}
                                        {assign "linktitle" "{$linktitle}{$PALANG.password_expiration}: {$item.password_expiry}"}
                                    {/if}
                                {/if}
                            {/if}

                            {if $table == 'foo' && $key == 'bar'}
                                <td>Special handling (complete table row) for {$table} / {$key}</td>
                            {else}
                                <td class="list-col-{$key}">
                                    {if $table == 'foo' && $key == 'bar'}
                                        Special handling (td content) for {$table} / {$key}
                                    {elseif $table == 'aliasdomain' && $key == 'target_domain' && $struct.target_domain.linkto == 'target'}
                                        <a href="list-virtual.php?domain={$item.target_domain|escape:"url"}">{$item.target_domain}</a>
                                    {elseif $table == 'domain' && $key == 'domain'}
                                        {call list_cell_link href=$linkto title=$linktitle text=$linktext} {if isset($item.mfa) && $item.mfa}<span class="badge text-bg-success">{$PALANG.mfa}</span>{/if}
                                        {if $item.full_mailbox_count > 0}<span class="text-danger">({$item.full_mailbox_count})</span>{/if}
                                        {if $dns_check_mode > 0 && isset($item.dns_active) && $item.dns_active == 0}<span class="bi bi-exclamation-triangle text-danger" role="img" aria-label="Inactive DNS" title="Inactive DNS"></span>{/if}
                                        {*                    {elseif $table == 'domain' && $key == 'domain'}
                                                                <a href="list.php?table=domain&domain={$item.domain|escape:"url"}">{$item.domain}</a>
                                        *}
                                    {elseif $key == 'mfa'}
                                        {if $item.mfa}{$PALANG.YES}{else}{$PALANG.NO}{/if}
                                    {elseif $key == 'active'}
                                        {if $item._can_edit}
                                            <a class="btn btn-sm btn-{if ($item.active==0)}info{else}warning{/if} list-action-icon" title="{$field.label}: {$item._active}" aria-label="{$field.label}: {$item._active}"
                                            href="{#url_editactive#}{$table}&amp;id={$item.$id_field|escape:"url"}&amp;active={if ($item.active==0)}1{else}0{/if}&amp;token={CSRF_Token type="url"}">
                                                {if $item._active == $PALANG['YES']}
                                                    <span class="bi bi-check-lg" aria-hidden="true"></span>
                                                {else}
                                                    <span class="bi bi-square" aria-hidden="true"></span>
                                                {/if}
                                            </a>
                                        {else}
                                            {$item._active}
                                        {/if}
                                    {elseif $field.type == 'bool'}
                                        {assign "tmpkey" "_{$key}"}{$item.{$tmpkey}}
                                    {elseif $field.type == 'list'}
                                        {foreach key=key2 item=field2 from=$item.$key}{$field2}<br>{/foreach}
                                    {elseif $field.type == 'pass'}
                                        (hidden)
                                    {elseif $field.type == 'quot'}
                                        {assign "tmpkey" "_{$key}_percent"}

                                        {if $item[$tmpkey] > $CONF.quota_level_high_pct}
                                            {assign var="quota_level" value="high"}
                                        {elseif $item[$tmpkey] > $CONF.quota_level_med_pct}
                                            {assign var="quota_level" value="mid"}
                                        {else}
                                            {assign var="quota_level" value="low"}
                                        {/if}
                                        {assign "quota_tooltip" ""}
                                        {if $table == 'domain' && $key == 'total_quot' && isset($item.total_quota_used)}
                                            {assign "quota_tooltip" "{$PALANG.quota_assigned}: {$linktext}\n{$PALANG.quota_used}: {$item.total_quota_used} MB"}
                                        {/if}
                                        <div class="quota_bar{if $item[$tmpkey] <= -1 && !($table == 'domain' && (($key == 'aliases_quot' && $item.aliases == 0) || ($key == 'mailboxes_quot' && $item.mailboxes == 0) || ($key == 'total_quot' && $item.quota == 0)))} quota_no_border{/if}"{if $quota_tooltip != ''} title="{$quota_tooltip}"{/if}>
                                            {if $item[$tmpkey] > -1}
                                                <span class="quota_fill quota_{$quota_level}" style="width:{$item[$tmpkey]}%;"></span>
                                                <span class="quota_label quota_text_{$quota_level}">{call list_cell_link href=$linkto title=$linktitle text=$linktext}</span>
                                            {else}
                                                <span class="quota_label">{if $table == 'domain' && $key == 'total_quot' && $item.quota == 0 && $CONF.used_quotas === YES && isset($item.total_quota_used)}{$item.total_quota_used} / &infin;{else}{call list_cell_link href=$linkto title=$linktitle text=$linktext}{/if}</span>
                                            {/if}
                                        </div>

                                    {elseif $field.type == 'txtl'}
                                        {foreach key=key2 item=field2 from=$item.$key}{$field2}<br>{/foreach}
                                    {elseif $field.type == 'html'}
                                        {* trusted HTML generated by the handler (e.g. alias status) - not escaped *}
                                        {$item.$key|raw}
                                    {else}
                                        {call list_cell_link href=$linkto title=$linktitle text=$linktext}
                                    {/if}
                                </td>
                            {/if}
                        {/if}
                    {/foreach}

                    <td class="list-col-action">{if $item._can_edit}
                            <a class="btn btn-sm btn-primary list-action-icon" title="{$PALANG.edit}" aria-label="{$PALANG.edit}"
                            href="edit.php?table={$table|escape:"url"}&amp;edit={$item.$id_field|escape:"url"}"><span
                                        class="bi bi-pencil" aria-hidden="true"></span></a>
                        {else}&nbsp;
                        {/if}
                    </td>
                    <td class="list-col-action">{if $item._can_delete}
                            <form method="post" action="{#url_delete#}">
                                <input type="hidden" name="table" value="{$table}">
                                <input type="hidden" name="delete" value="{$item.$id_field}">
                                {CSRF_Token}

                                <button class="btn btn-sm btn-danger list-action-icon" title="{$PALANG.del}" aria-label="{$PALANG.del}"
                                        onclick="return confirm('{$PALANG.{$msg.confirm_delete}|escape:'javascript'|replace:'%s':{$item.$id_field|escape:'javascript'}|escape:'force'}')">
                                    <span class="bi bi-trash" aria-hidden="true"></span>
                                </button>
                            </form>
                        {else}&nbsp;{/if}
                    </td>
                </tr>
            {/foreach}
            </tbody>
        </table>
        {if $table == 'domain' || $table == 'alias' || $table == 'aliasdomain'}</div>{/if}
        {if isset($pagination) && $pagination}
            <div class="mt-3">
                {include file="_pagination.tpl" pagination=$pagination pagination_label=$pagination_label}
            </div>
        {/if}
    </div>

    <div class="card-footer">
        <div class="btn-group">
            {if $msg.can_create}
                {assign var=tmpdomain value=""}
                {if isset($fDomain)}
                    {assign var=tmpdomain value="&domain={$fDomain|escape:url}"} {* plain '&' - escaped on output *}
                {/if}
                <a href="edit.php?table={$table|escape:"url"}{$tmpdomain}" role="button"
                    class="btn btn-secondary"><span
                            class="bi bi-plus-circle"
                            aria-hidden="true"></span> {$PALANG.{$formconf.create_button}}</a>
            {/if}
            <a href="list.php?table={$table|escape:"url"}&amp;output=csv&amp;domain={$domain_selected|escape:url}"
                role="button"
                class="btn btn-secondary"><span class="bi bi-box-arrow-up-right"
                                                aria-hidden="true"></span> {$PALANG.download_csv}</a>
        </div>
    </div>
</div>
