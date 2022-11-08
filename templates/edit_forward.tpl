<form name="alias" method="post" action="" class="form-horizontal">
    <div id="edit_form" class="panel panel-default">
        <div class="panel-heading"><h4>{$PALANG.pEdit_alias_welcome}</h4></div>
        <div class="panel-body enable-asterisk">
            <input class="flat" type="hidden" name="token" value="{$smarty.session.PFA_token|escape:"url"}"/>
            <p class="text-center"><em>{$PALANG.pEdit_alias_help}</em></p>
            <div class="form-group">
                <label class="col-md-4 col-sm-4 control-label">{$PALANG.alias}:</label>
                <div class="col-md-6 col-sm-8"><p class="form-control-static"><em>{$USERID_USERNAME}</em></p></div>
            </div>
            <div class="form-group">
                <label class="col-md-4 col-sm-4 control-label" for="fGoto">{$PALANG.to}:</label>
                <div class="col-md-6 col-sm-8">
                    <textarea class="form-control" rows="8" cols="50" name="fGoto"
                              id="fGoto">{foreach key=key2 item=field2 from=$tGotoArray}{$field2}&#10;{/foreach}</textarea>
                </div>
            </div>
            <div class="form-group">
                <label class="col-md-4 col-sm-4 control-label"></label>
                <div class="col-md-6 col-sm-8">
                    <div class="radio">
                        <label>
                            <input type="radio" name="fForward_and_store" id="fForward_and_store1"
                                   value="1"{$forward_and_store}/>
                            {$PALANG.pEdit_alias_forward_and_store}
                        </label>
                    </div>
                    <div class="radio">
                        <label>
                            <input type="radio" name="fForward_and_store" id="fForward_and_store0"
                                   value="0" {$forward_only}/>
                            {$PALANG.pEdit_alias_forward_only}
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="panel-footer">

            <div class="btn-toolbar">
                <div class="pull-right">
                    <a href="main.php" class="mr btn btn-secondary">{$PALANG.exit}</a>

                    <button class="ml btn btn-lg btn-primary" type="submit" name="submit" value="{$PALANG.save}">{$PALANG.save}</button>
                </div>
            </div>

        </div>
    </div>
</form>
