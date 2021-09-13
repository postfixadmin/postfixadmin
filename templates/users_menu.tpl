<nav class="navbar navbar-default navbar-fixed-top">
    <div class="container-fluid">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar"
                    aria-expanded="false" aria-controls="navbar">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            {*** <a class="navbar-brand" href='main.php'><img id="login_header_logo" src="{$CONF.theme_logo}" alt="Logo" /></a> ***}
            <a class="navbar-brand" href="{#url_user_main#}"><img id="login_header_logo"
                                                                                   src="../images/postbox.png"
                                                                                   alt="Logo"/></a>
        </div>
        <div id="navbar" class="collapse navbar-collapse">
            <ul class="nav navbar-nav">
                <li><a target="_top" href="{#url_user_main#}">{$PALANG.pMenu_main}</a></li>
                {if $CONF.vacation===YES}
                    <li><a target="_top" href="{#url_user_vacation#}">{$PALANG.pUsersMenu_vacation}</a></li>
                {/if}
                {if $CONF.edit_alias===YES}
                    <li><a target="_top" href="{#url_user_edit_alias#}">{$PALANG.pUsersMenu_edit_alias}</a></li>
                {/if}
                <li><a target="_top" href="{#url_user_password#}">{$PALANG.change_password}</a></li>
                <li class="logout"><a target="_top" href="{#url_user_logout#}">{$PALANG.pMenu_logout}</a></li>
            </ul>
        </div>
    </div>
</nav>
