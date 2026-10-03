<form name="password" method="post" action="" class="form-horizontal">
    {if $show_form == 'hidden'}
        <div id="showform" class="card">
            <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                <p class="mb-0">{$PALANG.pTOTP_enabled}</p>
                <a href="#" class="btn btn-primary" id="showbutton">{$PALANG.pTOTP_restart}</a>
            </div>
        </div>
        <script>
            document.getElementById("showbutton").addEventListener("click", function (e) {
                e.preventDefault();
                showform();
            });

            function showform() {
                document.getElementById("showform").style.display = "none";
                document.getElementById("edit_form").style.display = "block";
            }
        </script>
    {/if}
    <div id="edit_form" class="card" style="display:{if $show_form == 'hidden'}none{else}block{/if}">
        <div class="card-header"><h4>{$PALANG.pTOTP_welcome}</h4></div>
        <div class="card-body enable-asterisk">
            {CSRF_Token}

            <div class="mb-3">
                <label class="col-md-2">{$PALANG.pLogin_username}:</label>
                <div class="col-md-6 col-sm-8"><p class="form-control-plaintext"><em>{$SESSID_USERNAME}</em></p></div>
            </div>
            <div class="mb-3 {if $pPassword_password_current_text}is-invalid{/if}">
                <label class="col-md-2"
                       for="fPassword_current">{$PALANG.pPassword_password_current}:</label>
                <div class="col-md-6 col-sm-8"><input class="form-control" type="password" name="fPassword_current"
                                                      id="fPassword_current"/></div>
                <span class="form-text">{$pPassword_password_current_text}</span>
            </div>
            <div class="mb-3 {if $pTOTP_secret_text}is-invalid{/if}">
                <label class="col-md-2" for="fTOTP_secret">{$PALANG.pTOTP_secret}:</label>
                <div class="col-md-6 col-sm-8">
                    <img src="data:image/png;base64, {$pQR_raw}"/>{$pTOTP_secret}
                    <input type="hidden" name="fTOTP_secret" value="{$pTOTP_secret}"/>
                </div>
            </div>
            <div class="mb-3 {if $pTOTP_code_text}is-invalid{/if}">
                <label class="col-md-2" for="fTOTP_code">{$PALANG.pTOTP_code}:</label>
                <div class="col-md-6 col-sm-8"><input id="fTOTP_code" class="form-control" type="text"
                                                      name="fTOTP_code" size="6" inputmode="numeric"
                                                      autocomplete="one-time-code"/>
                    <span class="text-warning">{$pTOTP_code_text}</span> <!-- error text -->
                    <span class="form-text">{$PALANG.pTOTP_code_text}</span>
                </div>

            </div>
        </div>
        <div class="card-footer">
            <div class="btn-toolbar" role="toolbar">

                <div class="float-end">
                    {if $authentication_has_role.user}
                        <a href="main.php" class="btn mr btn-secondary">{$PALANG.exit}</a>
                    {/if}

                    <button class="btn ml btn-lg btn-primary" type="submit" name="submit"
                            value="{$PALANG.change_TOTP}">{$PALANG.change_TOTP}</button>

                </div>
            </div>
        </div>
    </div>

</form>
