<div id="login" class="container">
    <div class="card card-body">
        <h2 class="h2">{if $logintype=='admin'}{$PALANG.pLogin_welcome}{else}{$PALANG.pUsersLogin_welcome}{/if}</h2>

        <form name="frmLogin" method="post" action="" role="form" class="form-signin">

            {CSRF_Token}

            <div class="form-group">
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
            <div class="text-center p-3">
                <a href="users/">{$PALANG.pLogin_login_users}</a>
            </div>
        {/if}
        {if $oidc_enabled}
            <br/>
            <div class="text-center">
                <a class="btn btn-secondary" href="{$oidc_login_url}">
                    <span class="bi bi-box-arrow-in-right" aria-hidden="true"></span>
                    {$oidc_login_text}
                </a>
            </div>
        {/if}

        {if $domain_oidc_configs}
            <br/>
            <div class="text-center">
                <p class="text-muted">Or login with a domain-specific provider:</p>
                {foreach from=$domain_oidc_configs item=config}
                    <a class="btn btn-outline-secondary mb-1" href="oidc_login.php?domain={$config.domain|urlencode}">
                        <span class="bi bi-box-arrow-in-right" aria-hidden="true"></span>
                        {$config.login_button_text|default:'Login with SSO'} ({$config.domain})
                    </a>
                {/foreach}
            </div>
        {/if}
    </div>
    <script type="text/javascript">
        document.frmLogin.fUsername.focus();
    </script>
</div>
