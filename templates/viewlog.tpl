<div class="panel panel-default">
    <div class="panel-heading">
        <form name="frmOverview" method="post" action="">
            {html_options name='fDomain' output=$domain_list values=$domain_list selected=$domain_selected onchange="this.form.submit();"}
            <noscript><input class="button" type="submit" name="go" value="{$PALANG.go}"/></noscript>
        </form>
    </div>
    {if $tLog}
        <div class="panel-body">
            <h4>{$PALANG.pViewlog_welcome|replace:"%s":$CONF.page_size} {$fDomain} </h4>
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
    {/if}
</div>
