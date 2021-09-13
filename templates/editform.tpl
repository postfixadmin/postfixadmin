<form name="edit_{$table}" method="post" action="" class="form-horizontal">
    <div id="edit_form" class="panel panel-default">
        <div class="panel-heading"><h4>{$formtitle}</h4></div>
        <div class="panel-body enable-asterisk">
            <input class="flat" type="hidden" name="table" value="{$table}"/>
            <input class="flat" type="hidden" name="token" value="{$smarty.session.PFA_token|escape:"url"}"/>

            {foreach key=key item=field from=$struct}
                {if $field.display_in_form == 1}

                    {if $table == 'foo' && $key == 'bar'}
                        <div class="form-group">Special handling (complete table row) for {$table} / {$key}</div>
                    {else}
                        <div class="form-group {if $fielderror.{$key}}has-error{/if}">
                            <label class="col-md-4 col-sm-4 control-label" for="{$key}">{$field.label}</label>
                            <div class="col-md-6 col-sm-8">
                                {if $field.editable == 0}
                                    {if $field.type == 'enma'}
                                        {$struct.{$key}.options.{$value_{$key}}}
                                    {else}
                                        {$value_{$key}}
                                    {/if}
                                {else}
                                    {if $table == 'foo' && $key == 'bar'}
                                        Special handling (td content) for {$table} / {$key}
                                    {elseif $field.type == 'bool'}
                                        <div class="checkbox"><label>
                                                <input type="checkbox" value='1'
                                                       name="value[{$key}]"{if {$value_{$key}} == 1} checked="checked"{/if}/>
                                            </label></div>
                                    {elseif $field.type == 'enum'}
                                        <select class="form-control" name="value[{$key}]" id="{$key}">
                                            {html_options output=$struct.{$key}.options values=$struct.{$key}.options selected=$value_{$key}}
                                        </select>
                                    {elseif $field.type == 'enma'}
                                        <select class="form-control" name="value[{$key}]" id="{$key}">
                                            {html_options options=$struct.{$key}.options selected=$value_{$key}}
                                        </select>
                                    {elseif $field.type == 'list'}
                                        <select class="form-control" name="value[{$key}][]" size="10"
                                                multiple="multiple">
                                            {html_options output=$struct.{$key}.options values=$struct.{$key}.options selected=$value_{$key}}
                                        </select>
                                    {elseif $field.type == 'pass' || $field.type == 'b64p'}
                                        <input class="form-control" type="password" name="value[{$key}]" {if $key == 'password' || $key == 'password2'}autocomplete="new-password"{/if}/>
                                    {elseif $field.type == 'txtl'}
                                        <textarea class="form-control" rows="10" cols="35" name="value[{$key}]">{foreach key=key2 item=field2 from=$value_{$key}}{$field2}&#10;{/foreach}</textarea>
                                    {else}
                                        <input class="form-control" type="text" name="value[{$key}]"
                                               value="{$value_{$key}}"/>
                                    {/if}
                                {/if}

                                {if $table == 'foo' && $key == 'bar'}
                                    <span class="help-block">Special handling (td content) for {$table} / {$key}</span>
                                {else}
                                    {if $fielderror.{$key}}
                                        <span class="help-block">{$fielderror.{$key}}</span>
                                    {else}
                                        <span class="help-block">{$field.desc}</span>
                                    {/if}
                                {/if}
                            </div>
                        </div>
                    {/if}

                {/if}
            {/foreach}

        </div>
        <div class="panel-footer">
            <div class="btn-toolbar" role="toolbar">
                <div class="btn-group pull-right">
                    <input class="btn btn-primary" type="submit" name="submit" value="{$submitbutton}"/>
                </div>
            </div>
        </div>

    </div>
</form>
