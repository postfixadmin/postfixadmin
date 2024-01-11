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
				    	<input type="text" class="form-control" style="margin-bottom : 25px;" id="id_searchDomains" onkeyup="searchDomains()" placeholder="Search for domains..." title="search domains">
                                        <ul id="domainsList" name="value[{$key}][]" style="max-height : 250px; overflow: auto;">
                                        {foreach from=$struct.{$key}.options item=domain}
                                                <li>
                                                        {assign var=flag value=0}
                                                        {foreach from=$value_{$key} item=selectedDomain}
                                                                {if $domain == $selectedDomain }
                                                                        <input type="checkbox" checked name="value[{$key}][]" value="{$domain}" id="{$domain}_id" />
                                                                        <label for="{$domain}_id">{$domain}</label>
                                                                        {assign var=flag value=$flag+1}
                                                                {/if}
                                                        {/foreach}

                                                        {if $flag == 0 }
                                                                   <input type="checkbox" name="value[{$key}][]" value="{$domain}" id="{$domain}_id" />
                                                                   <label for="{$domain}_id">{$domain}</label>
                                                         {/if}
                                                </li>
                                        {/foreach}

                                        </ul>
                                    {elseif $field.type == 'pass' || $field.type == 'b64p'}
                                        <input class="form-control" type="password" name="value[{$key}]" {if $key == 'password' || $key == 'password2'}autocomplete="new-password"{/if}/>
                                    {elseif $field.type == 'txtl'}
                                        <textarea class="form-control" rows="10" cols="35" name="value[{$key}]">{foreach key=key2 item=field2 from=$value_{$key}}{$field2}&#10;{/foreach}</textarea>
                                    {elseif $field.type == 'txta'}
                                        <textarea class="form-control" rows="10" cols="35" name="value[{$key}]">{$value_{$key}}</textarea>
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
		    <button class="btn btn-primary" type="submit" name="submit">
				<span class="glyphicon glyphicon-edit" aria-hidden="true"></span> {$submitbutton}
		    </button>
		</div>
            </div>
        </div>

    </div>
</form>

<script type="text/javascript">
        function searchDomains(){
                input = document.getElementById("id_searchDomains").value.toLowerCase();
                ul = document.getElementById("domainsList");
                li = ul.getElementsByTagName("li");
                for (i=0; i< li. length; i++){
                        //get domain
                        domain = li[i].innerHTML.split('<label')[1].split('>')[1].split('</label')[0];

                        //if domain = input
                        if (domain.indexOf(input) > -1) {
                                li[i].style.display = "";
                        }else{
                                li[i].style.display = "none";
                        }

                }
        }
        {if $struct.local_part.options.legal_chars }
                // If set: Check for illegal characters in local part of username

                // decode htmlentities
                var div = document.createElement('div');
                div.innerHTML = "{$struct.local_part.options.legal_char_warning}";
                var decoded = div.firstChild.nodeValue;

                const local_part = document.getElementsByName("value[local_part]");
                local_part[0].tabIndex = -1
                local_part[0].addEventListener("keydown", function(event){
                        var regex = new RegExp("{$struct.local_part.options.legal_chars}");
                        if (!regex.test(event.key)) {
                                event.preventDefault();
                                alert(decoded + ": " + event.key);
                                return false;
                        }
                });
        {/if}
</script>
