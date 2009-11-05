{assign var="file" value=$smarty.config.url_list_virtual}
<div id="overview">
<form name="frmOverview" method="get" action="{$smarty.config.url_list_virtual}">
	<select name="domain" onchange="this.form.submit();">
		{$select_options}
	</select>
	<input type="hidden" name="limit" value="0" />
	<input class="button" type="submit" name="go" value="{$PALANG.pOverview_button}" />
</form>
<h4>{$PALANG.pOverview_welcome}{$fDomain}</h4>
<p>{$PALANG.pOverview_alias_alias_count}: {$limit.alias_count} / {$limit.aliases}</p>
<p>{$PALANG.pOverview_alias_mailbox_count}: {$limit.mailbox_count} / {$limit.mailboxes}</p>
{#form_search#}
</div>
<div id="tabbar">
<ul>
<li><a href="?domain={$smarty.get.domain}&tab=mailbox">{$PALANG.pOverview_mailbox_title}</a></li>
<li><a href="?domain={$smarty.get.domain}&tab=alias">{$PALANG.pOverview_alias_title}</a></li>
{if $boolconf_alias_domain}
	<li><a href="?domain={$smarty.get.domain}&tab=alias_domain">{$PALANG.pOverview_alias_domain_title}</a></li>
{/if}
</ul>
</div>
<br clear="all"/><br/>
{*** Domain Aliases ***}
{if $boolconf_alias_domain}
	{if $tab=='alias_domain'}
		{include file="list-virtual_alias_domain.tpl"}
	{/if}
{/if}
{*** Aliases ***}
{if $tab=='alias'}
	{$nav_bar_alias.top}
	<table id="alias_table">
		<tr>
			<td colspan="7"><h3>{$PALANG.pOverview_alias_title}</h3></td>
		</tr>
	{if $tAlias}
		{include file="list-virtual_alias.tpl"}
	{/if}
	</table>
	{$nav_bar_alias.bottom}
	{if $tCanAddAlias}
		<p><a href="create-alias.php?domain={$fDomain|escape:"url"}">{$PALANG.pMenu_create_alias}</a></p>
	{/if}
{/if}
{if $tab=='mailbox'}
	{$nav_bar_mailbox.top}
	{assign var="colspan" value=8}
	{if $CONF.vacation_control_admin===YES}{assign var="colspan" value="`$colspan+1`"}{/if}
	{if $CONF.alias_control_admin===YES}{assign var="colspan" value="`$colspan+1`"}{/if}
	<table id="mailbox_table">
		<tr>
			<td colspan="{$colspan}"><h3>{$PALANG.pOverview_mailbox_title}</h3></td>
		</tr>
	{if $tMailbox}
		{include file="list-virtual_mailbox.tpl"}
	{else}</table>
	{/if}
	{$nav_bar_mailbox.bottom}
	{if $tCanAddMailbox}
		<p><a href="create-mailbox.php?domain={$fDomain|escape:"url"}">{$PALANG.pMenu_create_mailbox}</a></p>
	{/if}
{/if}
{if $CONF.show_status===YES && $CONF.show_status_key===YES}
	<br/><br/>
	{if $CONF.show_undeliverable===YES}
		&nbsp;<span style='background-color:{$CONF.show_undeliverable_color};'>{$CONF.show_status_text}</span>={$PALANG.pStatus_undeliverable}
	{/if}
	{if $CONF.show_popimap===YES}
		&nbsp;<span style='background-color:{$CONF.show_popimap_color};'>{$CONF.show_status_text}</span>={$PALANG.pStatus_popimap}
	{/if}
	{if $CONF.show_custom_domains|@count>0}
		{foreach from=$CONF.show_custom_domains item=item key=i}
			&nbsp;<span style='background-color:{$CONF.show_custom_colors[$i]};'>{$CONF.show_status_text}</span>={$PALANG.pStatus_custom}{$item}
		{/foreach}
	{/if}
{/if}
