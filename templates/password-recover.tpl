<h2 class="h2">{$PALANG.pPassword_recovery_title}</h2>

<form name="frmPassword" method="post" action="" class="form">

    <div class="mb-3">
        <label for="fUsername">{$PALANG.pLogin_username}:</label>

        <input class="form-control" type="email" name="fUsername"/>

    </div>

    <button type=submit class="btn btn-primary" name="submit"
            value="submit">{$PALANG.pPassword_recovery_button}</button>

</form>

<script type="text/javascript">
    document.frmPassword.fUsername.focus();
</script>
