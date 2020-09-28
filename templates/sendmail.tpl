<form name="mailbox" method="post" action="" class="form-horizontal">
    <div id="edit_form" class="panel panel-default">
        <div class="panel-heading"><h4>{$PALANG.pSendmail_welcome}</h4></div>
        <div class="panel-body enable-asterisk">
            <input class="flat" type="hidden" name="token" value="{$smarty.session.PFA_token|escape:"url"}"/>
            <div class="form-group">
                <label class="col-md-4 col-sm-4 control-label">{$PALANG.from}:</label>
                <div class="col-md-6 col-sm-8"><p class="form-control-static"><em>{$smtp_from_email}</em></p></div>
            </div>
            <div class="form-group">
                <label class="col-md-4 col-sm-4 control-label" for="fTo">{$PALANG.pSendmail_to}:</label>
                <div class="col-md-6 col-sm-8"><input class="form-control" type="text" name="fTo" id="fTo"/></div>
            </div>
            <div class="form-group">
                <label class="col-md-4 col-sm-4 control-label" for="fSubject">{$PALANG.subject}:</label>
                <div class="col-md-6 col-sm-8"><input class="form-control" type="text" name="fSubject" id="fSubject"
                                                      value="{$PALANG.pSendmail_subject_text}"/></div>
            </div>
            <div class="form-group">
                <label class="col-md-4 col-sm-4 control-label" for="fBody">{$PALANG.pSendmail_body}:</label>
                <div class="col-md-6 col-sm-8"><textarea class="form-control" rows="10" cols="60" name="fBody"
                                                         id="fBody">{$CONF.welcome_text}</textarea></div>
            </div>
        </div>
        <div class="panel-footer">
            <div class="btn-toolbar" role="toolbar">
                <div class="btn-group pull-right">
                    <input class="btn btn-primary" type="submit" name="submit" value="{$PALANG.pSendmail_button}"/>
                </div>
            </div>
        </div>
    </div>
</form>
