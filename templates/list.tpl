<div id="{$id_div}" class="panel panel-default">

    {if ($admin_list|count > 1)}
        <div class="panel-heading">
            <form name="frmOverview" method="post" action="">
                {html_options name='username' output=$admin_list values=$admin_list selected=$admin_selected onchange="this.form.submit();"}
                <noscript><input class="button" type="submit" name="go" value="{$PALANG.go}"/></noscript>
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

    {if $table == 'domain'}<div class="domain-list-scroll">{/if}
    <table class="table table-hover" id='admin_table'>
        <!-- TODO: 'admin_table' needed because of CSS for table header -->

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
                    <th class="list-col-{$key}">{$field.label}</th>
                {/if}
            {/foreach}
            <th class="list-col-action">&nbsp;</th>
            <th class="list-col-action">&nbsp;</th>
        </tr>
        </thead>
        <tbody>
        {foreach key=raw_itemkey from=$RAW_items item=RAW_item}
        {assign var="itemkey" value=$raw_itemkey|htmlentities_no_double_encode} {* array keys in $items are escaped by PFASmarty::sanitise()  *}
        {assign var="item" value=$items[$itemkey]}

        <tr>

            {foreach key=key item=field from=$struct}
            {if $field.display_in_list == 1 && $field.label}

            {if $field.linkto != '' && ($item.$id_field != '' || $item.$id_field > 0) }
                {assign "linkto" "{$field.linkto|replace:'%s':{$item.$id_field|escape:url}}"} {* TODO: use label field instead *}
                {assign "linktitle" ""}
                {if $table == 'domain' && $key == 'domain'}
                    {if isset($struct.maxquota) && $struct.maxquota.label == '' && isset($item.maxquota)}
                        {assign "linktitle" "{$PALANG.pOverview_get_quota}: {$item.maxquota} MB"}
                    {/if}
                    {if isset($struct.password_expiry) && $struct.password_expiry.label == '' && isset($item.password_expiry)}
                        {if $linktitle != ''}{assign "linktitle" "{$linktitle}&#10;"}{/if}
                        {assign "linktitle" "{$linktitle}{$PALANG.password_expiration}: {$item.password_expiry}"}
                    {/if}
                {/if}
                {if $linktitle != ''}
                    {assign "linktext" "<a href='{$linkto}' title='{$linktitle}'>{$item.{$key}}</a>"}
                {else}
                    {assign "linktext" "<a href='{$linkto}'>{$item.{$key}}</a>"}
                {/if}
            {else}
                {assign "linktext" $item.$key}
            {/if}

            {if $table == 'foo' && $key == 'bar'}
            <td>Special handling (complete table row) for {$table} / {$key}</td>
            {else}
            <td class="list-col-{$key}">
                {if $table == 'foo' && $key == 'bar'}
                Special handling (td content) for {$table} / {$key}
                {elseif $table == 'aliasdomain' && $key == 'target_domain' && $struct.target_domain.linkto == 'target'}
                <a href="list-virtual.php?domain={$item.target_domain|escape:"quotes"}">{$item.target_domain}</a> {* do we need escape:url or escpae:quotes here? see #705 *}
                {*                    {elseif $table == 'domain' && $key == 'domain'}
                                        <a href="list.php?table=domain&domain={$item.domain|escape:"url"}">{$item.domain}</a>
                *}
                {elseif $key == 'active'}
                {if $item._can_edit}
                    <a class="btn btn-{if ($item.active==0)}info{else}warning{/if} list-action-icon" title="{$field.label}: {$item._active}" aria-label="{$field.label}: {$item._active}"
                       href="{#url_editactive#}{$table}&amp;id={$RAW_item.$id_field|escape:"url"}&amp;active={if ($item.active==0)}1{else}0{/if}&amp;token={$smarty.session.PFA_token|escape:"url"}">
                        {if $item._active == $PALANG['YES']}
                            <span class="glyphicon glyphicon-check" aria-hidden="true"></span>
                        {else}
                            <span class="glyphicon glyphicon-unchecked" aria-hidden="true"></span>
                        {/if}
                    </a>
                {else}
                    {$item._active}
                {/if}
                {elseif $field.type == 'bool'}
                {assign "tmpkey" "_{$key}"}{$item.{$tmpkey}}
                {elseif $field.type == 'list'}
                {foreach key=key2 item=field2 from=$item.$key}{$field2}<br>{/foreach}
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
                <div class="quota_bar{if $item[$tmpkey] <= -1} quota_no_border{/if}">
                {if $item[$tmpkey] > -1}
                    <span class="quota_fill quota_{$quota_level}" style="width:{$item[$tmpkey]}%;"></span>
                    <span class="quota_label quota_text_{$quota_level}">{$linktext}</span>
                {else}
                    <span class="quota_label">{$linktext}</span>
                {/if}
                </div>

{elseif $field.type == 'txtl'}
{foreach key=key2 item=field2 from=$item.$key}{$field2}<br>{/foreach}
{elseif $field.type == 'html'}
{$RAW_item.$key}
{else}
{$linktext}
{/if}
</td>
{/if}
{/if}
{/foreach}

<td class="list-col-action">{if $item._can_edit}
        <a class="btn btn-primary list-action-icon" title="{$PALANG.edit}" aria-label="{$PALANG.edit}"
           href="edit.php?table={$table|escape:"url"}&amp;edit={$RAW_item.$id_field|escape:"url"}"><span
                    class="glyphicon glyphicon-edit" aria-hidden="true"></span></a>
    {else}&nbsp;
    {/if}
</td>
<td class="list-col-action">{if $item._can_delete}
        <form method="post" action="{#url_delete#}">
            <input type="hidden" name="table" value="{$table}">
            <input type="hidden" name="delete" value="{$RAW_item.$id_field|escape:"quotes"}">
            <input type="hidden" name="token" value="{$smarty.session.PFA_token|escape:"quotes"}">

            <button class="btn btn-danger list-action-icon" title="{$PALANG.del}" aria-label="{$PALANG.del}"
                    onclick="return confirm('{$PALANG.{$msg.confirm_delete}|replace:'%s':$item.$id_field}')">
                <span class="glyphicon glyphicon-trash" aria-hidden="true"></span>
            </button>
        </form>
    {else}&nbsp;{/if}
</td>
</tr>
{/foreach}
</tbody>
</table>
    {if $table == 'domain'}</div>{/if}

<div class="panel-footer">
    <div class="btn-toolbar" role="toolbar">
        <div class="btn-group pull-right">
            {if $msg.can_create}
                {assign var=tmpdomain value=""}
                {if isset($fDomain)}
                    {assign var=tmpdomain value="&amp;domain={$fDomain|escape:url}"}
                {/if}
                <a href="edit.php?table={$table|escape:"url"}{$tmpdomain}" role="button" class="btn btn-default"><span
                            class="glyphicon glyphicon-plus-sign"
                            aria-hidden="true"></span> {$PALANG.{$formconf.create_button}}</a>
            {/if}
            <a href="list.php?table={$table|escape:"url"}&amp;output=csv&amp;domain={$domain_selected}" role="button"
               class="btn btn-default"><span class="glyphicon glyphicon-export"
                                             aria-hidden="true"></span> {$PALANG.download_csv}</a>
        </div>
    </div>
</div>

</div>
