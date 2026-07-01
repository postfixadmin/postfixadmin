<form name="edit_{$table}" method="post" action="" class="form">
    <!-- could really do with using https://getbootstrap.com/docs/5.3/forms/validation/#server-side here ? -->
    <div id="edit_form" class="card">
        <div class="card-header"><h4>{$formtitle}</h4></div>
        <div class="card-body enable-asterisk">
            <input class="flat" type="hidden" name="table" value="{$table}"/>

            {CSRF_Token}

            {foreach key=key item=field from=$struct}
                {if $field.display_in_form == 1}

                    {if $table == 'foo' && $key == 'bar'}
                        <div class="mb-3">Special handling (complete table row) for {$table} / {$key}</div>
                    {else}
                        <div class="mb-3 {if $fielderror.{$key}}is-invalid{/if}">
                            <label class="col-md-4" for="{$key}">{$field.label}</label>
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
                                    {elseif $table == 'alias' && $key == 'goto_default'}
                                        {* the label next to the box states what ticking it will do,
                                           which differs depending on whether a value is already stored *}
                                        <div class="checkbox"><label>
                                                <input type="checkbox" value='1' id="goto_default"
                                                       name="value[{$key}]"{if {$value_{$key}} == 1} checked="checked"{/if}/>
                                                <span id="goto_default_label"
                                                      data-label-off="{$PALANG.alias_goto_default_off}"
                                                      data-label-on="{if $struct.goto_default.options.stored != ''}{$PALANG.alias_goto_default_on_replace}{else}{$PALANG.alias_goto_default_on}{/if}">{$PALANG.alias_goto_default_off}</span>
                                            </label></div>
                                    {elseif $field.type == 'bool'}
                                        <div class="checkbox"><label>
                                                <input type="checkbox" value='1'
                                                       name="value[{$key}]"{if {$value_{$key}} == 1} checked="checked"{/if}/>
                                            </label></div>
                                    {elseif $field.type == 'enum'}
                                        <select class="form-select" name="value[{$key}]" id="{$key}">
                                            {html_options output=$struct.{$key}.options values=$struct.{$key}.options selected=$value_{$key}}
                                        </select>
                                    {elseif $field.type == 'enma'}
                                        <select class="form-select" name="value[{$key}]" id="{$key}">
                                            {html_options options=$struct.{$key}.options selected=$value_{$key}}
                                        </select>
                                    {elseif $field.type == 'list'}
                                        <input type="text" class="form-control" style="margin-bottom : 25px;"
                                               id="id_searchDomains" onkeyup="searchDomains()"
                                              placeholder="{$PALANG.search_domains}" title="{$PALANG.search_domains}">
                                        <ul id="domainsList" name="value[{$key}][]"
                                            style="max-height : 250px; overflow: auto;">
                                            {foreach from=$struct.{$key}.options item=domain}
                                                <li style="list-style-type: none">
                                                    {assign var=flag value=0}
                                                    {foreach from=$value_{$key} item=selectedDomain}
                                                        {if $domain == $selectedDomain }
                                                            <input type="checkbox" checked name="value[{$key}][]"
                                                                   value="{$domain}" id="{$domain}_id"/>
                                                            <label for="{$domain}_id">{$domain}</label>
                                                            {assign var=flag value=$flag+1}
                                                        {/if}
                                                    {/foreach}

                                                    {if $flag == 0 }
                                                        <input type="checkbox" name="value[{$key}][]" value="{$domain}"
                                                               id="{$domain}_id"/>
                                                        <label for="{$domain}_id">{$domain}</label>
                                                    {/if}
                                                </li>
                                            {/foreach}
                                        </ul>
                                    {elseif $field.type == 'pass' || $field.type == 'b64p'}
                                        <input class="form-control" type="password" name="value[{$key}]"
                                               {if $key == 'password' || $key == 'password2'}autocomplete="new-password"{/if}/>
                                    {elseif $field.type == 'txtl'}
                                        <textarea class="form-control" rows="10" cols="35"
                                                  name="value[{$key}]">{foreach key=key2 item=field2 from=$value_{$key}}{$field2}&#10;{/foreach}</textarea>
                                    {elseif $field.type == 'txta'}
                                        <textarea class="form-control" rows="10" cols="35"
                                                  name="value[{$key}]">{$value_{$key}}</textarea>
                                    {else}
                                        <input class="form-control" type="text" name="value[{$key}]"
                                               value="{$value_{$key}}"/>
                                    {/if}
                                {/if}

                                {if $table == 'foo' && $key == 'bar'}
                                    <span class="form-text">Special handling (td content) for {$table} / {$key}</span>
                                {elseif $table == 'alias' && $key == 'goto_default'}
                                    {if $struct.goto_default.options.stored != ''}
                                        <span class="form-text">
                                            {$PALANG.alias_goto_default_current}: {$struct.goto_default.options.stored}
                                            {* submits the separate form at the end of this template -
                                               it cannot be nested inside the form we are in *}
                                            <button type="submit" form="clear_goto_default"
                                                    class="btn btn-sm btn-link text-danger p-0 align-baseline"
                                                    title="{$PALANG.alias_goto_default_delete}"
                                                    aria-label="{$PALANG.alias_goto_default_delete}"
                                                    onclick="return confirm ('{$PALANG.alias_goto_default_delete_confirm}');">
                                                <span class="bi bi-x-lg" aria-hidden="true"></span>
                                            </button>
                                        </span>
                                    {/if}
                                {else}
                                    {if $fielderror.{$key}}
                                        <span class="form-text text-danger">{$fielderror.{$key}}</span>
                                    {else}
                                        <span class="form-text">{$field.desc}</span>
                                    {/if}
                                {/if}
                            </div>
                        </div>
                    {/if}

                {/if}
            {/foreach}

        </div>
        <div class="card-footer">
            <div class="btn-toolbar" role="toolbar">
                <div class="btn-group float-end">
                    <button class="btn btn-primary" type="submit" name="submit">
                        <span class="bi bi-pencil" aria-hidden="true"></span> {$submitbutton}
                    </button>
                </div>
            </div>
        </div>

    </div>
</form>

{if isset($struct.goto_default) && $struct.goto_default.options.stored != ''}
    {* deleting the stored default must work without creating an alias, so it is its own
       form - placed outside the one above and referenced via the button's form attribute *}
    <form id="clear_goto_default" method="post" action="alias-goto-default.php">
        <input type="hidden" name="domain" value="{$value_domain|escape:"quotes"}"/>
        {CSRF_Token}
    </form>
{/if}

<script type="text/javascript">
    {if isset($struct.goto_default)}
    // "save this as my default" checkbox: state what ticking it does, and what unticking it means
    (function () {
        var box = document.getElementById("goto_default");
        var label = document.getElementById("goto_default_label");
        if (!box || !label) {
            return;
        }
        var update = function () {
            label.textContent = box.checked ? label.dataset.labelOn : label.dataset.labelOff;
        };
        box.addEventListener("change", update);
        update();
    })();
    {/if}

    function searchDomains() {
        input = document.getElementById("id_searchDomains").value.toLowerCase();
        ul = document.getElementById("domainsList");
        li = ul.getElementsByTagName("li");
        for (i = 0; i < li.length; i++) {
            //get domain
            domain = li[i].innerHTML.split('<label')[1].split('>')[1].split('</label')[0];

            //if domain = input
            if (domain.indexOf(input) > -1) {
                li[i].style.display = "";
            } else {
                li[i].style.display = "none";
            }

        }
    }
    {if (isset($struct.local_part) && $struct.local_part.options.legal_chars) }
    // If set: Check for illegal characters in local part of username

    // decode htmlentities
    var div = document.createElement('div');
    div.innerHTML = "{$struct.local_part.options.legal_char_warning}";
    var decoded = div.firstChild.nodeValue;

    const local_part = document.getElementsByName("value[local_part]");
    if (local_part.length > 0) {
        local_part[0].tabIndex = -1
        local_part[0].addEventListener("keydown", function (event) {
            var regex = new RegExp("{$struct.local_part.options.legal_chars}");
            if (!regex.test(event.key)) {
                event.preventDefault();
                alert(decoded + ": " + event.key);
                return false;
            }
        });
    }
    {/if}
</script>
