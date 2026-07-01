<form name="settings" method="post" action="" class="form-horizontal">
    <div id="edit_form" class="panel panel-default">
        <div class="panel-heading"><h4>{$PALANG.pMenu_settings}</h4></div>
        <div class="panel-body">
            <input class="flat" type="hidden" name="token" value="{$smarty.session.PFA_token|escape:"url"}"/>

            <h5>{$PALANG.settings_alias_prefill_title}</h5>
            <p class="help-block">{$PALANG.settings_alias_prefill_help}</p>

            <div class="form-group">
                <div class="col-md-offset-1 col-md-11">
                    <div class="radio">
                        <label>
                            <input type="radio" name="alias_goto_prefill_mode" value="none"
                                   {if $alias_goto_prefill_mode != 'login' && $alias_goto_prefill_mode != 'custom'}checked="checked"{/if}/>
                            {$PALANG.settings_alias_prefill_mode_none}
                        </label>
                    </div>
                    <div class="radio">
                        <label>
                            <input type="radio" name="alias_goto_prefill_mode" value="login"
                                   {if $alias_goto_prefill_mode == 'login'}checked="checked"{/if}/>
                            {$PALANG.settings_alias_prefill_mode_login} (<em>{$login_username|escape:"html"}</em>)
                        </label>
                    </div>
                    <div class="radio">
                        <label>
                            <input type="radio" name="alias_goto_prefill_mode" value="custom"
                                   {if $alias_goto_prefill_mode == 'custom'}checked="checked"{/if}/>
                            {$PALANG.settings_alias_prefill_mode_custom}
                        </label>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="col-md-4 col-sm-4 control-label" for="alias_goto_prefill_value">
                    {$PALANG.settings_alias_prefill_custom_value}:
                </label>
                <div class="col-md-6 col-sm-8">
                    <input class="form-control" type="text" name="alias_goto_prefill_value"
                           id="alias_goto_prefill_value"
                           value="{$alias_goto_prefill_value|escape:"html"}"/>
                </div>
            </div>
        </div>

        <script>
            (function () {
                var radios = document.querySelectorAll('input[name="alias_goto_prefill_mode"]');
                var input = document.getElementById('alias_goto_prefill_value');

                function sync() {
                    var checked = document.querySelector('input[name="alias_goto_prefill_mode"]:checked');
                    var isCustom = checked && checked.value === 'custom';
                    input.disabled = !isCustom;
                    input.required = isCustom;
                }

                for (var i = 0; i < radios.length; i++) {
                    radios[i].addEventListener('change', sync);
                }
                sync();
            })();
        </script>
        <div class="panel-footer">
            <div class="btn-toolbar" role="toolbar">
                <div class="btn-group pull-right">
                    <button class="btn btn-primary" type="submit" name="submit">
                        <span class="glyphicon glyphicon-ok" aria-hidden="true"></span> {$PALANG.save}
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
