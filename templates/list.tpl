<div id="overview">
<form name="frmOverview" method="post" action="">
    {if ($admin_list|count > 1)}
        {html_options name='username' output=$admin_list values=$admin_list selected=$admin_selected onchange="this.form.submit();"}
        <input class="button" type="submit" name="go" value="{$PALANG.go}" />
    {/if}
</form>
{#form_search#}
</div>

    {if ($search|count > 0)}
        <div class='searchparams'>
            <p>{$PALANG.searchparams}
            {foreach key=key item=field from=$search}
                <span>{if $struct.$key.label}{$struct.$key.label}{else}{$key}{/if}
                    {if isset($searchmode.$key)}{$searchmode.$key}{else}={/if} {$field}
                </span>
            {/foreach}
            <span><a href="list.php?table={$table}&reset_search=1">[x]</a></span>
        </div>
    {/if}



<div id="list">
<table border=0 id='admin_table'><!-- TODO: 'admin_table' needed because of CSS for table header -->

<tr class="header">
    {foreach key=key item=field from=$struct}
        {if $field.display_in_list == 1 && $field.label}{* don't show fields without a label *}
            <td>{$field.label}</td>
        {/if}
    {/foreach}
    <td>&nbsp;</td>
    <td>&nbsp;</td>
</tr>

{foreach from=$items item=item}
    {#tr_hilightoff#}

    {foreach key=key item=field from=$struct}
        {if $field.display_in_list == 1 && $field.label}

            {if $field.linkto != '' && ($item.$id_field != '' || $item.$id_field > 0) }
                {assign "linkto" "{$field.linkto|replace:'%s':{$item.$id_field|escape:url}}"} {* TODO: use label field instead *}
                {assign "linktext" "<a href='{$linkto}'>{$item.{$key}}</a>"}
            {else}
                {assign "linktext" $item.$key}
            {/if}

            {if $table == 'foo' && $key == 'bar'}
                <td>Special handling (complete table row) for {$table} / {$key}</td></tr>
            {else}
                <td>
                    {if $table == 'foo' && $key == 'bar'}
                        Special handling (td content) for {$table} / {$key}
{*                    {elseif $table == 'domain' && $key == 'domain'}
                        <a href="list.php?table=domain&domain={$item.domain|escape:"url"}">{$item.domain}</a>
*}                        
                    {elseif $key == 'active'}
                        <a href="{#url_editactive#}{$table}&amp;id={$item.$id_field|escape:"url"}&amp;active={if ($item.active==0)}1{else}0{/if}&amp;token={$smarty.session.PFA_token|escape:"url"}">{$item._active}</a>
                    {elseif $field.type == 'bool'}
                        {assign "tmpkey" "_{$key}"}{$item.{$tmpkey}}
                    {elseif $field.type == 'list'}
                        {foreach key=key2 item=field2 from=$value_{$key}}<p>{$field2} {/foreach}
                    {elseif $field.type == 'pass'}
                        (hidden)
                    {elseif $field.type == 'quot'}
                        {assign "tmpkey" "_{$key}_percent"}

                        {if $item[$tmpkey]>90}
                            {assign var="quota_level" value="high"}
                        {elseif $item[$tmpkey]>55}
                            {assign var="quota_level" value="mid"}
                        {else} 
                            {assign var="quota_level" value="low"}
                        {/if}
                        {if $item[$tmpkey] > -1}
                            <div class="quota quota_{$quota_level}" style="width:{$item[$tmpkey] *1.2}px;"></div>
                            <div class="quota_bg"></div></div>
                            <div class="quota_text quota_text_{$quota_level}">{$linktext}</div>
                        {else}
                            {$item[$key]}
                        {/if}

                    {elseif $field.type == 'txtl'}
                        {foreach key=key2 item=field2 from=$value_{$key}}<p>{$field2} {/foreach}
                    {else}
                        {$linktext}
                    {/if}
                </td>
            {/if}
        {/if}
    {/foreach}

    <td>{if $item._can_edit}<a href="edit.php?table={$table|escape:"url"}&amp;edit={$item.$id_field|escape:"url"}">{$PALANG.edit}</a>{else}&nbsp;{/if}</td>
    <td>{if $item._can_delete}<a href="{#url_delete#}?table={$table}&amp;delete={$item.$id_field|escape:"url"}&amp;token={$smarty.session.PFA_token|escape:"url"}" 
        onclick="return confirm ('{$PALANG.{$msg.confirm_delete}|replace:'%s':$item.$id_field}')">{$PALANG.del}</a>{else}&nbsp;{/if}</td>
    </tr>
{/foreach}

</table>

<br /><a href="edit.php?table={$table|escape:"url"}" class="button">{$PALANG.{$formconf.create_button}}</a><br />
<br />
<br /><a href="list.php?table={$table|escape:"url"}&amp;output=csv">{$PALANG.download_csv}</a>

</div>
