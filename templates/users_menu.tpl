<button type="button" class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
        aria-expanded="false" aria-controls="navbarSupportedContent" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
</button>
<div id="navbarSupportedContent" class="collapse navbar-collapse">
    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li><a class="nav-item nav-link" target="_top" href="{#url_user_main#}">{$PALANG.pMenu_main}</a></li>
        {if $CONF.vacation===YES}
            <li><a class="nav-item nav-link" target="_top" href="{#url_user_vacation#}">{$PALANG.pUsersMenu_vacation}</a></li>
        {/if}
        {if $CONF.edit_alias===YES}
            <li><a class="nav-item nav-link" target="_top" href="{#url_user_edit_alias#}&amp;edit={$smarty.session.sessid.username|escape:url}">{$PALANG.pUsersMenu_edit_alias}</a></li>
        {/if}
        {if $user_can_edit_mailbox_name}
            <li><a class="nav-item nav-link" href="{#url_user_edit_name#}&amp;edit={$smarty.session.sessid.username|escape:url}">{$PALANG.pUsersMenu_edit_name}</a></li>
        {/if}
        {* TOTP *}
        {if $CONF.totp==='YES'}
            {strip}
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button" aria-haspopup="true"
                       aria-expanded="false" >{$PALANG.pMenu_security} <span
                                class="caret"></span></a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-item dropdown-item" href="password.php"><span class="nav-item bi bi-lock" aria-hidden="true"></span> {$PALANG.change_password}</a></li>
                        <li><a class="nav-item dropdown-item" href="{#url_totp#}"><span class="nav-item bi bi-lock" aria-hidden="true"></span> {$PALANG.pUsersMenu_totp}</a></li>
                        <li><a class="nav-item dropdown-item" href="{#url_user_totp_exceptions#}"><span class="nav-item bi bi-lock" aria-hidden="true"></span> {$PALANG.pMenu_totp_exceptions}</a></li>
                        {if $CONF.app_passwords==='YES'}
                        <li><a class="nav-item dropdown-item" href="{#url_app_passwords#}"><span class="nav-item bi bi-lock" aria-hidden="true"></span> {$PALANG.pMenu_app_passwords}</a></li>
                        {/if}
                    </ul>
                </li>
            {/strip}
        {else}
            {* password *}
            <li><a class="nav-link" target="_top" href="{#url_user_password#}">{$PALANG.change_password}</a></li>
        {/if}

        <li class="logout"><a class="nav-link" target="_top" href="{#url_user_logout#}">{$PALANG.pMenu_logout}</a></li>
    </ul>
</div>
