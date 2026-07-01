<div class="card">
    <div class="card-header">
        <form name="frmOverview" method="post" action="" class="d-flex flex-wrap gap-2 align-items-center">
            <select name="fDomain" class="form-select w-auto" onchange="this.form.submit();">
                <option value="{$all_domains_value}"{if $show_all} selected{/if}>{$PALANG.pViewlog_all_domains}</option>
                {foreach from=$domain_list item=d}
                    <option value="{$d|escape}"{if !$show_all && $d==$domain_selected} selected{/if}>{$d|escape}</option>
                {/foreach}
            </select>
            <label class="col-form-label">{$PALANG.pViewlog_per_page}</label>
            <select name="page_size" class="form-select w-auto" onchange="this.form.submit();">
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

        {if $number_of_pages > 1}
            <div class="card-footer">
                <nav aria-label="{$PALANG.pViewlog_action}">
                    <ul class="pagination justify-content-end mb-0">
                        <li class="page-item{if $page_number <= 1} disabled{/if}">
                            <a class="page-link" href="#" onclick="go2page(1); return false;" aria-label="First">&laquo;</a>
                        </li>
                        <li class="page-item{if $page_number <= 1} disabled{/if}">
                            <a class="page-link" href="#" onclick="go2page({$page_number-1}); return false;" aria-label="Previous">&lsaquo;</a>
                        </li>
                        {foreach from=$page_window item=p}
                            {if $p}
                                <li class="page-item{if $p == $page_number} active{/if}">
                                    <a class="page-link" href="#" onclick="go2page({$p}); return false;">{$p}</a>
                                </li>
                            {else}
                                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                            {/if}
                        {/foreach}
                        <li class="page-item{if $page_number >= $number_of_pages} disabled{/if}">
                            <a class="page-link" href="#" onclick="go2page({$page_number+1}); return false;" aria-label="Next">&rsaquo;</a>
                        </li>
                        <li class="page-item{if $page_number >= $number_of_pages} disabled{/if}">
                            <a class="page-link" href="#" onclick="go2page({$number_of_pages}); return false;" aria-label="Last">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            </div>
        {/if}
    {/if}
</div>

<script>
        function go2page(page){
                window.location.href = '{$url}?page='+page+'&fDomain={$domain_param|escape:"url"}&page_size={$page_size}';
        }
</script>
