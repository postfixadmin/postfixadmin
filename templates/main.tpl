<!-- {$smarty.template} -->
<div class="panel panel-default" id="main_menu">

    <table class="table">
        <tr>
            <td nowrap="nowrap"><a class="btn btn-primary btn-block" href="{#url_list_domain#}">{$PALANG.pAdminMenu_list_domain}</a></td>
            <td>{$PALANG.pMain_overview}</td>
        </tr>
        <tr>
            <td nowrap="nowrap"><a class="btn btn-primary btn-block" href="{#url_create_alias#}">{$PALANG.add_alias}</a></td>
            <td>{$PALANG.pMain_create_alias}</td>
        </tr>
        <tr>
            <td nowrap="nowrap"><a class="btn btn-primary btn-block" href="{#url_create_mailbox#}">{$PALANG.add_mailbox}</a></td>
            <td>{$PALANG.pMain_create_mailbox}</td>
        </tr>
        {if $CONF.sendmail==='YES'}
            <tr>
                <td nowrap="nowrap"><a class="btn btn-primary btn-block" href="{#url_sendmail#}">{$PALANG.pMenu_sendmail}</a></td>
                <td>{$PALANG.pMain_sendmail}</td>
            </tr>
        {/if}
        <tr>
            <td nowrap="nowrap"><a class="btn btn-primary btn-block" href="{#url_password#}">{$PALANG.pMenu_password}</a></td>
            <td>{$PALANG.pMain_password}</td>
        </tr>
        {* viewlog *}
        {if $CONF.logging==='YES'}
            <tr>
                <td nowrap="nowrap"><a class="btn btn-primary btn-block" href="{#url_viewlog#}">{$PALANG.pMenu_viewlog}</a></td>
                <td>{$PALANG.pMain_viewlog}</td>
            </tr>
        {/if}
            
        <tr>
            <td nowrap="nowrap"><a class="btn btn-primary btn-block" href="{#url_logout#}">{$PALANG.pMenu_logout}</a></td>
            <td>{$PALANG.pMain_logout}</td>
        </tr>
    </table>
</div>
