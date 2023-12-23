<form name="password" method="post" action="" class="form-horizontal">
    <div id="edit_form" class="panel panel-default">
        <div class="panel-heading"><h4>{$PALANG.pTOTP_confirm}</h4></div>
        <div class="panel-body enable-asterisk">
            <input class="flat" type="hidden" name="token" value="{$smarty.session.PFA_token|escape:"url"}"/>
            <div class="form-group {if $pTOPT_code_text}has-error{/if}">
                <label class="col-md-4 col-sm-4 control-label" for="fTOTP_code">{$PALANG.pTOTP_code}:</label>
                <div class="col-md-6 col-sm-8"><input id="TOTP_code" class="form-control" type="text" name="fTOTP_code" size="6" autofocus /></div>
                <span class="help-block">{$pTOPT_code_text}</span>
            </div>
        </div>
        <div class="panel-footer">
            <div class="btn-toolbar" role="toolbar">
                <div class="pull-right">
                    <a href="login-mfa.php?abort=1" class="btn mr btn-secondary">{$PALANG.exit}</a>
                    <button class="btn ml btn-lg btn-primary" type="submit" name="submit" value="{$PALANG.MFA_submit}">{$PALANG.MFA_submit}</button>
                </div>
            </div>
        </div>
    </div>
</form>
