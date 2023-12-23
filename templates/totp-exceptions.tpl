<form name="password" method="post" action="" class="form-horizontal">
    <div id="edit_form" class="panel panel-default">
        <div class="panel-heading"><h4>{$PALANG.pTotp_exceptions_welcome}</h4></div>
        <div class="panel-body enable-asterisk">
            <input class="flat" type="hidden" name="token" value="{$smarty.session.PFA_token|escape:"url"}"/>
            <div class="form-group {if $pPassword_text}has-error{/if}">
                <label class="col-md-4 col-sm-4 control-label"
                       for="fPassword_current">{$PALANG.pPassword_password_current}:</label>
                <div class="col-md-6 col-sm-8"><input class="form-control" type="password" name="fPassword_current" id="fPassword_current"/></div>
                <span class="help-block">{$pPassword_text}</span>
            </div>
            <div class="form-group {if $pUser_text}has-error{/if}">
                <label class="col-md-4 col-sm-4 control-label" for="fUser">{$PALANG.pTotp_exceptions_user}:</label>
                <div class="col-md-6 col-sm-8"><input class="form-control" type="input" name="fUser" id="fUser" value="{$pUser}"/></div>
                <span class="help-block">{$pUser_text}</span>
            </div>
            <div class="form-group">
                <label class="col-md-4 col-sm-4 control-label" for="fIp">{$PALANG.pTotp_exceptions_address}:</label>
                <div class="col-md-6 col-sm-8"><input class="form-control" type="input" name="fIp" id="fIp"/></div>
            </div>
            <div class="form-group">
                <label class="col-md-4 col-sm-4 control-label" for="fDesc">{$PALANG.pTotp_exceptions_description}:</label>
                <div class="col-md-6 col-sm-8"><input class="form-control" type="input" name="fDesc" id="fDesc"/></div>
            </div>
        </div>
        <div class="panel-footer">
            <div class="btn-toolbar" role="toolbar">

                <div class="pull-right">
                    <a href="main.php" class="btn mr btn-secondary">{$PALANG.exit}</a>

                    <button class="btn ml btn-lg btn-primary" type="submit" name="submit" value="{$PALANG.pTotp_exceptions_add}">{$PALANG.pTotp_exceptions_add}</button>

                </div>
            </div>
        </div>
    </div>
</form>

<div id="edit_form" class="panel panel-default">
    <div class="panel-heading"><h4>{$PALANG.pTotp_exceptions_list}</h4></div>
    <table class="table table-hover" id="mailbox_table">
        <tr class="header">
            <th>{$PALANG.pOverview_mailbox_username}</th>
            <th>{$PALANG.pTotp_exceptions_address}</th>
            <th>{$PALANG.pTotp_exceptions_description}</th>
            <th>{$PALANG.pTotp_exceptions_revoke}</th>
        </tr>
        {foreach $pExceptions as $exception}
        <tr>
            <td>{$exception.username}</td>
            <td>{$exception.ip}</td>
            <td>{$exception.description}</td>
            <td>
                <form name="exception{$exception.id}" method="post" action="" class="form-vertical">
                    <input type="hidden" name="fId" value="{$exception.id}">
                    <input class="flat" type="hidden" name="token" value="{$smarty.session.PFA_token|escape:"url"}"/>
                    <button class="btn ml btn-primary" type="submit" {if !$exception.edit}disabled="disabled"{/if} name="submit" value="{$PALANG.pTotp_exceptions_revoke}">{$PALANG.pTotp_exceptions_revoke}</button>
                </form>
            </td>
        </tr>
        {/foreach}
    </table>
</div>
