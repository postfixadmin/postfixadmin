<div class="panel panel-default" id="main_menu">
    <table class="table">
        {if $CONF.vacation===YES}
            <tr>
                <td nowrap="nowrap"><a class="btn btn-primary" href="vacation.php">{$PALANG.pUsersMenu_vacation}</a>
                </td>
                <td>{$tummVacationtext}</td>
            </tr>
        {/if}
        {if $CONF.edit_alias===YES}
            <tr>
                <td nowrap="nowrap"><a class="btn btn-primary" href="edit-alias.php">{$PALANG.pUsersMenu_edit_alias}</a>
                </td>
                <td>{$PALANG.pUsersMain_edit_alias}</td>
            </tr>
        {/if}
        <tr>
            <td nowrap="nowrap"><a class="btn btn-primary" href="password.php">{$PALANG.change_password}</a></td>
            <td>{$PALANG.pUsersMain_password}</td>
        </tr>
        {* TOTP *}
        {if $CONF.totp==='YES'}
        {strip}
        <tr>
            <td nowrap="nowrap"><a class="btn btn-primary" href="totp.php">{$PALANG.pUsersMenu_totp}</a></td>
            <td>{$PALANG.pUsersMain_totp}</td>
        </tr>
        <tr>
            <td nowrap="nowrap"><a class="btn btn-primary" href="{#url_totp_exceptions#}">{$PALANG.pMenu_totp_exceptions}</a></td>
            <td>{$PALANG.pUsersMain_totp_exceptions}</td>
        </tr>
        {/strip}
        {/if}
        {if $CONF.app_passwords==='YES'}
        {strip}
        <tr>
            <td nowrap="nowrap"><a class="btn btn-primary" href="{#url_app_passwords#}">{$PALANG.pMenu_app_passwords}</a></td>
            <td>{$PALANG.pUsersMain_app_passwords}</td>
        </tr>
        {/strip}
        {/if}
        <tr>
            <td nowrap="nowrap"><a class="btn btn-primary" href="{#url_user_logout#}">{$PALANG.pMenu_logout}</a></td>
            <td>{$PALANG.pMain_logout}</td>
        </tr>
    </table>
</div>
