<form name="edit-vacation" method="post" action="" class="form-horizontal">
    <div id="edit_form" class="panel panel-default">
        <div class="panel-heading"><h4>{$PALANG.pUsersVacation_welcome}</h4></div>
        <div class="panel-body enable-asterisk">
            <input type="hidden" name="token" value="{$smarty.session.PFA_token|escape:"url"}"/>
            {if !$authentication_has_role.user}
                <div class="form-group">
                    <label class="col-md-4 col-sm-4 control-label">{$PALANG.pLogin_username}:</label>
                    <div class="col-md-6 col-sm-8"><p class="form-control-static"><em>{$tUseremail}</em></p></div>
                </div>
            {/if}
            <div class="form-group">
                <label class="col-md-4 col-sm-4 control-label" for="fActiveFromForm">{$PALANG.pUsersVacation_activefrom}
                    :</label>
                <div class="col-md-6 col-sm-8">
                    <input type='hidden' name="fActiveFrom" id="fActiveFrom" value="{$tActiveFrom}"
                           class="form-control hidden"/>
                    <div class="input-group date" id="datetimepicker-fActiveFrom">
                        <input type='text' name="fActiveFromForm" id="fActiveFromForm" value="{$tActiveFrom}"
                               class="form-control" />
                        <span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span></span>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="col-md-4 col-sm-4 control-label"
                       for="fActiveUntilForm">{$PALANG.pUsersVacation_activeuntil}:</label>
                <div class="col-md-6 col-sm-8">
                    <input type='hidden' name="fActiveUntil" id="fActiveUntil" value="{$tActiveUntil}"
                           class="form-control hidden"/>
                    <div class="input-group date" id="datetimepicker-fActiveUntil">
                        <input type='text'
                               name="fActiveUntilForm" id="fActiveUntilForm" value="{$tActiveUntil}"
                               class="form-control" />
                        <span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span></span>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="col-md-4 col-sm-4 control-label" for="fInterval_Time">{$PALANG.pVacation_reply_type}
                    :</label>
                <div class="col-md-6 col-sm-8">
                    <select class="form-control" name="fInterval_Time" id="fInterval_Time">
                        {html_options options=$select_options selected=$tInterval_Time}
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="col-md-4 col-sm-4 control-label" for="fSubject">{$PALANG.subject}:</label>
                <div class="col-md-6 col-sm-8">
                    <textarea class="form-control" rows="3" cols="60" name="fSubject"
                              id="fSubject">{$tSubject}</textarea>
                </div>
            </div>
            <div class="form-group">
                <label class="col-md-4 col-sm-4 control-label" for="fBody">{$PALANG.message}:</label>
                <div class="col-md-6 col-sm-8">
                    <textarea class="form-control" rows="10" cols="60" name="fBody" id="fBody">{$tBody}</textarea>
                </div>
            </div>
        </div>
        <div class="panel-footer">
            <div class="btn-toolbar" role="toolbar">

                <div class="pull-right">
                    <a href="{$return_url}" class="btn mr btn-secondary bg-info" title="Go back">{$PALANG.exit}</a>

                    <button class="mr btn mr-5 btn-danger " type="submit" name="action"
                            value="fBack">{$PALANG.pEdit_vacation_remove}</button>

                    <button class="ml btn btn-lg btn-primary" type="submit" name="action"
                            value="fChange">{$PALANG.pEdit_vacation_set}</button>
                </div>
            </div>
        </div>
    </div>
    </div>
</form>
<script type="text/javascript">

    {literal}
    $(function () {
        // See: https://momentjs.com/docs/#/displaying/format/ for format spec.
        // See: https://getdatepicker.com/4/Options/ for docs
        $('#datetimepicker-fActiveFrom').datetimepicker({
            ignoreReadonly: true,
            //     locale: locale,
            showTodayButton: true,
            showClear: true,
            showClose: true,
            allowInputToggle: true,
            format: 'YYYY/MM/DD HH:mm',  // should use 'L' but it's crappy mm/dd/YYYY format for me in the U.K.
            date: $('#fActiveFrom').val(),

        });
        $('#datetimepicker-fActiveUntil').datetimepicker({
            ignoreReadonly: true,
            //   locale: locale,
            showTodayButton: true,
            showClear: true,
            showClose: true,
            allowInputToggle: true,
            format: 'YYYY/MM/DD HH:mm', // should use 'L' but it's crappy mm/dd/YYYY format for me in the U.K.
            date: $('#fActiveUntil').val(),
            useCurrent: false //Important! See issue #1075
        });

        $("#datetimepicker-fActiveFrom").on("dp.change", function (e) {
            $('#datetimepicker-fActiveUntil').data("DateTimePicker").minDate(e.date);
            $('#fActiveFrom').val((e.date) ? e.date.format() : '').trigger("change");
        });
        $("#datetimepicker-fActiveUntil").on("dp.change", function (e) {
            $('#datetimepicker-fActiveFrom').data("DateTimePicker").maxDate(e.date);
            $('#fActiveUntil').val((e.date) ? e.date.format() : '').trigger("change");
        });
    });
    {/literal}

</script>
