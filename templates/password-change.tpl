<nav class="navbar navbar-default navbar-fixed-top">
    <div class="container">
        <div class="navbar-header">
            <a class="navbar-brand" href='main.php'><img id="login_header_logo" src="{$CONF.theme_logo}"
                                                         alt="Logo"/></a>
            {if $CONF.show_header_text==='YES' && $CONF.header_text}
                <h2>{$CONF.header_text}</h2>
            {/if}
        </div>
    </div>
</nav>

<h3 class="h3">{$PALANG.pPassword_welcome}</h3>


<form name="mailbox" method="post" class="form">

    <div class="form-group">
        <label for="fUsername">{$PALANG.pLogin_username}</label>
        <input class="form-control" type="email" name="fUsername" value="{$tUsername}"/>
    </div>

    <div class="form-group">
        <label for="fCode">{$PALANG.pPassword_password_code}</label>
        <input class="form-control" type="text" name="fCode" value="{$tCode}"/>
    </div>

    <div class="form-group">
        <label for="fPassword">
            {$PALANG.pPassword_password}
        </label>
        <input class="form-control" type="password" name="fPassword" autocomplete="new-password"/>
    </div>

    <div class="form-group">
        <label for="fPassword2">
            {$PALANG.pPassword_password2}
        </label>
        <input class="form-control" type="password" name="fPassword2" autocomplete="new-password"/>
    </div>

    <button class="btn btn-primary" type="submit" name="submit"
            value="submit">{$PALANG.change_password}</button>
</form>

