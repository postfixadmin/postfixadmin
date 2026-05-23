<h3 class="h3">{$PALANG.pPassword_welcome}</h3>

<form name="mailbox" method="post" class="form">
    <div class="mb-3">
        {CSRF_Token}
        <div class="form-group">
            <label for="fUsername">{$PALANG.pLogin_username}</label>
            <input class="form-control" type="email" name="fUsername" value="{$tUsername}"/>
        </div>

        <div class="mb-3">
            <label for="fCode">{$PALANG.pPassword_password_code}</label>
            <input class="form-control" type="text" name="fCode" value="{$tCode}"/>
        </div>

        <div class="mb-3">
            <label for="fPassword">
                {$PALANG.pPassword_password}
            </label>
            <input class="form-control" type="password" name="fPassword" autocomplete="new-password"/>
        </div>

        <div class="mb-3">
            <label for="fPassword2">
                {$PALANG.pPassword_password2}
            </label>
            <input class="form-control" type="password" name="fPassword2" autocomplete="new-password"/>
        </div>


    {if $tTotpRequired}
    <div class="form-group">
        <label for="fTOTP_code">
            {$PALANG.pTOTP_confirm}:
        </label>
        <input class="flat" type="text" name="fTOTP_code" autocomplete="one-time-code" />
    </div>
    {/if}

    <button class="btn btn-primary" type="submit" name="submit"
            value="submit">{$PALANG.change_password}</button>
    </div>

</form>

