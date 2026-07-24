<form name="edit-vacation" method="post" action="">
    <div id="edit_form" class="card">
        <div class="card-header"><h4>{$PALANG.pUsersVacation_welcome}</h4></div>
        <div class="card-body">
            {CSRF_Token}
            {if !$authentication_has_role.user}
                <div class="p-3">
                    <label class="col-md-6 ">{$PALANG.pLogin_username}:</label>
                    <div class="col-md-6 "><p class="form-control-plaintext"><em>{$tUseremail}</em></p></div>
                </div>
            {/if}

            <div class="p-3">
                <label class="col-md-6 " for="fActiveFrom">{$PALANG.pUsersVacation_activefrom}:</label>
                <div class="col-md-6">
                    <div class="input-group ">
                        <input type='datetime-local' name="fActiveFrom" id="fActiveFrom" min="1 year ago" max="+1 year"
                               value="{$tActiveFrom}" class="form-control"/>
                    </div>
                </div>
            </div>
            <div class="p-3">
                <label class="col-md-6" for="fActiveUntil">{$PALANG.pUsersVacation_activeuntil}:</label>
                <div class="col-md-6">
                    <div class="input-group ">
                        <input type='datetime-local' name="fActiveUntil" id="fActiveUntil"
                               value="{$tActiveUntil}" class="form-control"/>
                    </div>
                </div>
            </div>

            <div class="p-3">
                <label class="col-md-6" for="fInterval_Time">{$PALANG.pVacation_reply_type}:</label>
                <div class="col-md-6">
                    <select class="form-control" name="fInterval_Time" id="fInterval_Time">
                        {html_options options=$select_options selected=$tInterval_Time}
                    </select>
                </div>
            </div>
            <div class="p-3">
                <label class="col-md-6 " for="fSubject">{$PALANG.subject}:</label>
                <div class="col-md-6">
                    <input type="text" class="form-control" value="{$tSubject}" name="fSubject" id="fSubject"/>
                </div>
            </div>
            <div class="p-3">
                <label class="col-md-6 " for="fBody">{$PALANG.message}:</label>
                <div class="col-md-6 ">
                    <textarea class="form-control" rows="10" cols="60" name="fBody" id="fBody">{$tBody}</textarea>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer">
        <div class="btn-toolbar" role="toolbar">

            <a href="{$return_url}" class="btn  btn-secondary m-3 " title="Go back">{$PALANG.exit}</a>


            <button class="m-3 btn  btn-danger " type="submit" name="action"
                    value="fBack">{$PALANG.pEdit_vacation_remove}</button>

            <button class="m-3 btn  btn-primary" type="submit" name="action"
                    value="fChange">{$PALANG.pEdit_vacation_set}</button>
        </div>
    </div>

</form>