<div class="panel panel-default">
    <div class="panel-heading">
        <form name="frmOverview" method="post" action="">
            {html_options name='fDomain' output=$domain_list values=$domain_list selected=$domain_selected onchange="this.form.submit();"}
            <noscript><input class="button" type="submit" name="go" value="{$PALANG.go}"/></noscript>
        </form>
    </div>
    <table id="maillog_table" class="table">
        {if !$log_list}
            <p class="text-center">No logs found for {$domain_selected}!</p>
        {else}
            <tr class="header">
                <th>#</th>
                <th>File Name</th>
                <th>File Size (KB)</th>
                <th>&nbsp;</th>
            </tr>
            {assign var=i value=0}

            {foreach from=$logs item=log}
                <tr>
                    <td>
                        {$log.number}
                    </td>
                    <td>
                        {$log.name}
                    </td>
                    <td>
                        {$log.size}
                    </td>
                    <td>
                        <a class="btn btn-primary" href="?get_log={$log.name}&fDomain={$domain_selected}">Download</a>
                    </td>
                </tr>
            {/foreach}
        {/if}
    </table>

</div>