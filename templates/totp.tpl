<form name="password" method="post" action="" class="form-horizontal">
    <div id="edit_form" class="panel panel-default" style="visibility:{$show_form}">
        <div class="panel-heading"><h4>{$PALANG.pTOTP_welcome}</h4></div>
        <div class="panel-body enable-asterisk">
            <input class="flat" type="hidden" name="token" value="{$smarty.session.PFA_token|escape:"url"}"/>
            <div class="form-group">
                <label class="col-md-4 col-sm-4 control-label">{$PALANG.pLogin_username}:</label>
                <div class="col-md-6 col-sm-8"><p class="form-control-static"><em>{$SESSID_USERNAME}</em></p></div>
            </div>
            <div class="form-group {if $pPassword_password_current_text}has-error{/if}">
                <label class="col-md-4 col-sm-4 control-label"
                       for="fPassword_current">{$PALANG.pPassword_password_current}:</label>
                <div class="col-md-6 col-sm-8"><input class="form-control" type="password" name="fPassword_current"
                                                      id="fPassword_current"/></div>
                <span class="help-block">{$pPassword_password_current_text}</span>
            </div>
            <div class="form-group {if $pTOTP_secret_text}has-error{/if}">
                <label class="col-md-4 col-sm-4 control-label" for="fTOTP_secret">{$PALANG.pTOTP_secret}:</label>
                <div class="col-md-6 col-sm-8">
                    <img src="data:image/png;base64, {$pQR_raw}" />{$pTOTP_secret}
                    <input type="hidden" name="fTOTP_secret" value="{$pTOTP_secret}" />
                </div>
            </div>
            <div class="form-group {if $pTOTP_code_text}has-error{/if}">
                <label class="col-md-4 col-sm-4 control-label" for="fTOTP_code">{$PALANG.pTOTP_code}:</label>
                <div class="col-md-6 col-sm-8"><input id="TOTP_code" class="form-control" type="text" name="fTOTP_code" size="6" /></div>
                <span class="help-block">{$pTOTP_code_text}</span>
            </div>
        </div>
        <div class="panel-footer">
            <div class="btn-toolbar" role="toolbar">

                <div class="pull-right">
                    {if $authentication_has_role.user}
                        <a href="main.php" class="btn mr btn-secondary">{$PALANG.exit}</a>
                    {/if}

                    <button class="btn ml btn-lg btn-primary" type="submit" name="submit" value="{$PALANG.change_TOTP}">{$PALANG.change_TOTP}</button>

                </div>
            </div>
        </div>
    </div>
    {if $show_form == 'hidden'}
    <div id="showform" class="panel panel-default"">
        <div class="panel-footer">
            <div class="btn-toolbar" role="toolbar">
                <div class="pull-left">
                    <h3>{$PALANG.TOTP_already_configured}</h3>
                </div>
                <div class="pull-right">
                    <a href="#" class="btn ml btn-lg btn-primary" id="showbutton">{$PALANG.show}</a>
                </div>
            </div>
        </div>
    </div>
    {/if}
</form>
<script>
document.getElementById("showbutton").addEventListener("click", function(e) {
  showform()
});

function showform() {
  document.getElementById("showform").style.visibility= "hidden";
  document.getElementById("edit_form").style.visibility= "visible";
}
</script>
