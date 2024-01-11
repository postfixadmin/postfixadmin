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

<h2 class="h2">{$PALANG.pPassword_recovery_title}</h2>

<form name="frmPassword" method="post" action="" class="form">

    <div class="form-group">
        <label for="fUsername">{$PALANG.pLogin_username}:</label>

        <input class="form-control" type="email" name="fUsername"/>

    </div>

    <button type=submit class="btn btn-primary" name="submit"
            value="submit">{$PALANG.pPassword_recovery_button}</button>

</form>

<script type="text/javascript">
    document.frmPassword.fUsername.focus();
</script>
