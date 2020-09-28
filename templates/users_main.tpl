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
        <tr>
            <td nowrap="nowrap"><a class="btn btn-primary" href="{#url_user_logout#}">{$PALANG.pMenu_logout}</a></td>
            <td>{$PALANG.pMain_logout}</td>
        </tr>
    </table>
</div>
