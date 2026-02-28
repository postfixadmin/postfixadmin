<nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top">
    <div class="container">
        <a class="navbar-brand" href='main.php'><img id="login_header_logo" src="{$CONF.theme_logo}"
                                                     alt="Logo"/></a>
        {if $CONF.show_header_text==='YES' && $CONF.header_text}
            <h2>{$CONF.header_text}</h2>
        {/if}
    </div>
</nav>

<div id="login" class="container">

    <h2 class="h2">{if $logintype=='admin'}{$PALANG.pLogin_welcome}{else}{$PALANG.pUsersLogin_welcome}{/if}</h2>

    <div class="card card-body">

        <form name="frmLogin" method="post" action="" role="form" class="form-signin">
            <input type="hidden" name="token" value="{$smarty.session.PFA_token|escape:"url"}"/>
            <div class="mb-3">
                <label for="fUsername">{$PALANG.pLogin_username}:</label>
                <input class="form-control" type="text" name="fUsername" id="fUsername"/>
            </div>
            <div class="mb-3">
                <label for="fPassword">{$PALANG.password}:</label>
                <input class="form-control" type="password" name="fPassword" id="fPassword"/>
            </div>
            {if $forgotten_password_reset}
                <div class="mb-3 row">
                    <div class="col-sm-6 offset-sm-3 reset-button">
                        <a class="btn btn-secondary w-100" role="button" href="password-recover.php">
                            <span class="bi bi-arrow-clockwise" aria-hidden="true"></span>
                            {$PALANG.pUsersLogin_password_recover}</a>
                    </div>
                </div>
            {/if}
            <div class="mb-3">
                <label for=lang>{$PALANG.pLogin_language}:</label>
                {$language_selector}
            </div>
            <div class="text-center">
                <button class="btn btn-primary btn-lg" type="submit" name="submit" value="{$PALANG.pLogin_button}"><span class="bi bi-box-arrow-in-right"
                                                    		aria-hidden="true"></span> {$PALANG.pLogin_button}</button>
            </div>
        </form>
        {if $logintype == 'admin'}
            <br/>
            <div class="text-center">
                <a href="users/">{$PALANG.pLogin_login_users}</a>
            </div>
        {/if}
    </div>
    <script type="text/javascript">
        document.frmLogin.fUsername.focus();
    </script>
</div>
