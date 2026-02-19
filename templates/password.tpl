<form name="password" method="post" action="" class="form-horizontal">
    <div id="edit_form" class="card">
        <div class="card-header"><h4>{$PALANG.pPassword_welcome}</h4></div>
        <div class="card-body enable-asterisk">
            <input class="flat" type="hidden" name="token" value="{$smarty.session.PFA_token|escape:"url"}"/>
            <div class="mb-3">
                <label class="col-md-4 col-sm-4 control-label">{$PALANG.pLogin_username}:</label>
                <div class="col-md-6 col-sm-8"><p class="form-control-plaintext"><em>{$SESSID_USERNAME}</em></p></div>
            </div>
            <div class="mb-3 {if $pPassword_password_current_text}is-invalid{/if}">
                <label class="col-md-4 col-sm-4 control-label"
                       for="fPassword_current">{$PALANG.pPassword_password_current}:</label>
                <div class="col-md-6 col-sm-8"><input class="form-control" type="password" name="fPassword_current"
                                                      id="fPassword_current"/></div>
                <span class="form-text">{$pPassword_password_current_text}</span>
            </div>
            <div class="mb-3 {if $pPassword_password_text}is-invalid{/if}">
                <label class="col-md-4 col-sm-4 control-label" for="fPassword">{$PALANG.pPassword_password}:</label>
                <div class="col-md-6 col-sm-8"><input class="form-control" type="password" name="fPassword"
                                                      id="fPassword" autocomplete="new-password"/></div>
                <span class="form-text">{$pPassword_password_text}</span>
            </div>
            <div class="mb-3">
                <label class="col-md-4 col-sm-4 control-label" for="fPassword2">{$PALANG.pPassword_password2}:</label>
                <div class="col-md-6 col-sm-8"><input class="form-control" type="password" name="fPassword2"
                                                      id="fPassword2" autocomplete="new-password"/></div>
            </div>
        </div>
        <div class="card-footer">
            <div class="btn-toolbar" role="toolbar">

                <div class="float-end">
                    {if $authentication_has_role.user}
                        <a href="main.php" class="btn mr btn-secondary">{$PALANG.exit}</a>
                    {/if}

                    <button class="btn ml btn-lg btn-primary" type="submit" name="submit" value="{$PALANG.change_password}">{$PALANG.change_password}</button>

                </div>
            </div>
        </div>
    </div>
</form>
