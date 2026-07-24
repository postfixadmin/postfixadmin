<div class="card">
    <div class="card-header">
        <form name="frmOverview" method="post" action="" class="d-flex flex-wrap gap-2 align-items-center">
            <select name="fDomain" class="form-select w-auto" onchange="this.form.submit();">
                <option value="{$all_domains_value}"{if $show_all} selected{/if}>{$PALANG.pViewlog_all_domains}</option>
                {foreach from=$domain_list item=d}
                    <option value="{$d|escape}"{if !$show_all && $d==$domain_selected} selected{/if}>{$d|escape}</option>
                {/foreach}
            </select>
            <label for="page_size_select" class="col-form-label">{$PALANG.pViewlog_per_page}</label>
            <select id="page_size_select" name="page_size" class="form-select w-auto" onchange="this.form.submit();">
                {foreach from=$page_size_options item=ps}
                    <option value="{$ps}"{if $ps==$page_size} selected{/if}>{$ps}</option>
                {/foreach}
            </select>
            <noscript><input class="button" type="submit" name="go" value="{$PALANG.go}"/></noscript>
        </form>
    </div>
    {if $tLog}
        <div class="card-body">
            {if $domain_selected}
                <h4>{$PALANG.pViewlog_welcome|replace:"%s":$page_size} {$fDomain} </h4>
            {else}
                <h4>{$PALANG.pViewlog_welcome_all|replace:"%s":$page_size}</h4>
            {/if}
        </div>
        <table id="log_table" class="table">
            {#tr_header#}
            <th>{$PALANG.pViewlog_timestamp}</th>
            <th>{$PALANG.admin}</th>
            <th>{$PALANG.domain}</th>
            <th>{$PALANG.pViewlog_action}</th>
            <th>{$PALANG.pViewlog_data}</th>
            </tr>
            {assign var="PALANG_pViewlog_data" value=$PALANG.pViewlog_data}

            {foreach from=$tLog item=item}
                {assign var=log_data value=$item.data|truncate:35:"...":true}
                {assign var=item_data value=$item.data}
                {$smarty.config.tr_hilightoff|replace:'>':" style=\"cursor:pointer;\" onclick=\"alert('$PALANG_pViewlog_data = $item_data')\">"}
                <td nowrap="nowrap">{$item.timestamp}</td>
                <td nowrap="nowrap">{$item.username}</td>
                <td nowrap="nowrap">{$item.domain}</td>
                <td nowrap="nowrap">{$item.action}</td>
                <td nowrap="nowrap">{$log_data}</td>
                </tr>
            {/foreach}
        </table>

        {if $pagination}
            <div class="card-footer">
                {include file="_pagination.tpl"}
            </div>
        {/if}
    {/if}
</div>
