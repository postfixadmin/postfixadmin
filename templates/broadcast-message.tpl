<form name="broadcast-message" method="post" action="" class="form-horizontal">
<div id="edit_form" class="panel panel-default">
	<div class="panel-heading"><h4>{$PALANG.pBroadcast_title}</h4></div>
	<div class="panel-body">
		<input class="flat" type="hidden" name="token" value="{$smarty.session.PFA_token|escape:"url"}" />
		<div class="form-group">
                        <label class="col-md-4 col-sm-4 control-label">{$PALANG.from}:</label>
                        <div class="col-md-6 col-sm-8"><p class="form-control-static"><em>{$smtp_from_email}</em></p></div>
                </div>
		<div class="form-group">
                        <label class="col-md-4 col-sm-4 control-label" for="name">{$PALANG.pBroadcast_name}:</label>
                        <div class="col-md-6 col-sm-8"><input class="form-control" type="text" name="name" id="name" /></div>
                </div>
		<div class="form-group">
                        <label class="col-md-4 col-sm-4 control-label" for="subject">{$PALANG.subject}:</label>
                        <div class="col-md-6 col-sm-8"><input class="form-control" type="text" name="subject" id="subject" /></div>
                </div>
                <div class="form-group">
                        <label class="col-md-4 col-sm-4 control-label" for="message">{$PALANG.message}:</label>
                        <div class="col-md-6 col-sm-8"><textarea class="form-control" rows="6" cols="40" name="message" id="message"></textarea></div>
                </div>
		<div class="form-group">
                        <label class="col-md-4 col-sm-4 control-label"></label>
                        <div class="col-md-6 col-sm-8"><div class="checkbox"><label><input type="checkbox" value="1" name="mailboxes_only"/>{$PALANG.broadcast_mailboxes_only}</label></div></div>
                </div>
		<div class="form-group">
                        <label class="col-md-4 col-sm-4 control-label" for="domains">{$PALANG.broadcast_to_domains}</label>
                        <div class="col-md-6 col-sm-8">
				<select multiple="multiple" name="domains[]" id="domains" class="form-control">
					{html_options output=$allowed_domains values=$allowed_domains selected=$allowed_domains}
				</select>
			</div>
                </div>
	</div>
        <div class="panel-footer">
                <div class="btn-toolbar" role="toolbar">
                        <div class="btn-group pull-right">
                        <input class="btn btn-primary" type="submit" name="submit" value="{$PALANG.pSendmail_button}" />
                        </div>
                </div>
        </div>
</div>
</form>
