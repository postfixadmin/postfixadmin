<div class="panel panel-default">

{if ($admin_list|count > 1)}
<div class="panel-heading">
<form name="frmOverview" method="post" action="">
        {html_options name='username' output=$admin_list values=$admin_list selected=$admin_selected onchange="this.form.submit();"}
        <noscript><input class="button" type="submit" name="go" value="{$PALANG.go}" /></noscript>
</form>
</div>
{/if}

{if $msg.show_simple_search}
    {#form_search#}
{/if}

{if $msg.show_simple_search}
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
{/if}

<table class="table table-hover" border=0 id='admin_table'><!-- TODO: 'admin_table' needed because of CSS for table header -->

{if $msg.list_header}
	{assign var="colcount" value=2}
	{foreach key=key item=field from=$struct}
		{if $field.display_in_list == 1 && $field.label}{* don't show fields without a label *}
			{assign var="colcount" value=$colcount+1}
		{/if}
	{/foreach}
	<thead>
	<tr>
		<th style="text-align:center;" colspan="{$colcount}">{$PALANG.{$msg.list_header}}</th>
	</tr>
	</thead>
{/if}

<thead>
<tr class="header">
    {foreach key=key item=field from=$struct}
        {if $field.display_in_list == 1 && $field.label}{* don't show fields without a label *}
            <th>{$field.label}</th>
        {/if}
    {/foreach}
    <th>&nbsp;</th>
    <th>&nbsp;</th>
</tr>
</thead>

{foreach key=itemkey from=$RAW_items item=RAW_item}
    {assign "item" $items.{htmlentities($itemkey, $smarty.const.ENT_QUOTES, 'UTF-8', false)}} {* array keys in $items are escaped using htmlentities(), see smarty.inc.php *}
    <tr>

    {foreach key=key item=field from=$struct}
        {if $field.display_in_list == 1 && $field.label}

            {if $field.linkto != '' && ($item.$id_field != '' || $item.$id_field > 0) }
                {assign "linkto" "{$field.linkto|replace:'%s':{$item.$id_field|escape:url}}"} {* TODO: use label field instead *}
                {assign "linktext" "<a href='{$linkto}'>{$item.{$key}}</a>"}
            {else}
                {assign "linktext" $item.$key}
            {/if}

            {if $table == 'foo' && $key == 'bar'}
                <td>Special handling (complete table row) for {$table} / {$key}</td>
            {else}
                <td>
                    {if $table == 'foo' && $key == 'bar'}
                        Special handling (td content) for {$table} / {$key}
                    {elseif $table == 'aliasdomain' && $key == 'target_domain' && $struct.target_domain.linkto == 'target'}
                        <a href="list-virtual.php?domain={$item.target_domain|escape:"url"}">{$item.target_domain}</a>
{*                    {elseif $table == 'domain' && $key == 'domain'}
                        <a href="list.php?table=domain&domain={$item.domain|escape:"url"}">{$item.domain}</a>
*}
                    {elseif $key == 'active'}
                        {if $item._can_edit}
                            <a class="btn btn-warning" href="{#url_editactive#}{$table}&amp;id={$RAW_item.$id_field|escape:"url"}&amp;active={if ($item.active==0)}1{else}0{/if}&amp;token={$smarty.session.PFA_token|escape:"url"}">{$item._active}</a>
                        {else}
                            {$item._active}
                        {/if}
                    {elseif $field.type == 'bool'}
                        {assign "tmpkey" "_{$key}"}{$item.{$tmpkey}}
                    {elseif $field.type == 'list'}
                        {foreach key=key2 item=field2 from=$item.$key}{$field2}<br> {/foreach}
                    {elseif $field.type == 'pass'}
                        (hidden)
                    {elseif $field.type == 'quot'}
                        {assign "tmpkey" "_{$key}_percent"}

                        {if $item[$tmpkey] > $CONF.quota_level_high_pct}
                            {assign var="quota_level" value="high"}
                        {elseif $item[$tmpkey] > $CONF.quota_level_med_pct}
                            {assign var="quota_level" value="mid"}
                        {else}
                            {assign var="quota_level" value="low"}
                        {/if}
                        {if $item[$tmpkey] > -1}
                            <div class="quota quota_{$quota_level}" style="width:{$item[$tmpkey] *1.2}px;"></div>
                            <div class="quota_bg"></div></div>
                            <div class="quota_text quota_text_{$quota_level}">{$linktext}</div>
                        {else}
                            <div class="quota_bg quota_no_border"></div></div>
                            <div class="quota_text">{$linktext}</div>
                        {/if}

                    {elseif $field.type == 'txtl'}
                        {foreach key=key2 item=field2 from=$item.$key}{$field2}<br> {/foreach}
                    {elseif $field.type == 'html'}
                        {$RAW_item.$key}
                    {else}
                        {$linktext}
                    {/if}
                </td>
            {/if}
        {/if}
    {/foreach}

    <td>{if $item._can_edit}
            <a class="btn btn-primary" href="edit.php?table={$table|escape:"url"}&amp;edit={$RAW_item.$id_field|escape:"url"}">{$PALANG.edit}</a>
        {else}&nbsp;
        {/if}
    </td>
    <td>{if $item._can_delete}
        <form method="post" action="{#url_delete#}">
            <input type="hidden" name="table" value="{$table}">
            <input type="hidden" name="delete" value="{$RAW_item.$id_field|escape:"quotes"}">
            <input type="hidden" name="token" value="{$smarty.session.PFA_token|escape:"quotes"}">

            <button class="btn btn-danger" onclick="return confirm('{$PALANG.{$msg.confirm_delete}|replace:'%s':$item.$id_field}')">
                {$PALANG.del}
            </button>
        </form>
    {else}&nbsp;{/if}
</td>
    </tr>
{/foreach}

</table>

<div class="panel-footer">
	<div class="btn-toolbar" role="toolbar">
		<div class="btn-group pull-right">
		{if $msg.can_create}
		<a href="edit.php?table={$table|escape:"url"}" role="button" class="btn btn-default"><span class="glyphicon glyphicon-plus-sign" aria-hidden="true"></span> {$PALANG.{$formconf.create_button}}</a>
		{/if}
		<a href="list.php?table={$table|escape:"url"}&amp;output=csv&amp;domain={$domain_selected}" role="button" class="btn btn-default"><span class="glyphicon glyphicon-export" aria-hidden="true"></span> {$PALANG.download_csv}</a>
		</div>
	</div>
</div>

</div>
