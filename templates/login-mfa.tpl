<form name="password" method="post" action="" class="form-horizontal">

    <div id="edit_form" class="card">
        <div class="card-header"><h4>{$PALANG.pTOTP_confirm}</h4></div>
        <div class="card-body enable-asterisk">
            {CSRF_Token}

            <div class="mb-3 {if $pTOPT_code_text}is-invalid{/if}">
                <label class="col-md-4 col-sm-4" for="fTOTP_code">{$PALANG.pTOTP_code}:</label>
                <div class="col-md-6 col-sm-8"><input id="fTOTP_code" class="form-control" autocomplete="off"
                                                      type="text" name="fTOTP_code" size="6" autofocus/></div>
                <span class="form-text">{$pTOPT_code_text}</span>

            </div>
        </div>
        <div class="card-footer">
            <div class="btn-toolbar" role="toolbar">
                <div class="float-end">
                    <a href="login-mfa.php?abort=1" class="btn mr btn-secondary">{$PALANG.exit}</a>
                    <button class="btn ml btn-lg btn-primary" type="submit" name="submit"
                            value="{$PALANG.MFA_submit}">{$PALANG.MFA_submit}</button>
                </div>
            </div>
        </div>
    </div>
</form>
