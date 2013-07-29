{assign var="file" value=$smarty.config.url_list_virtual}
<div id="overview">
<form name="frmOverview" method="get" action="{$smarty.config.url_list_virtual}">
	<select name="domain" onchange="this.form.submit();">
		{$select_options}
	</select>
	<input type="hidden" name="limit" value="0" />
	<input class="button" type="submit" name="go" value="{$PALANG.go}" />
</form>
<h4>{$PALANG.pOverview_welcome}{$fDomain}</h4>
<p>{$PALANG.aliases}: {$limit.alias_count} / {$limit.aliases}</p>
<p>{$PALANG.mailboxes}: {$limit.mailbox_count} / {$limit.mailboxes}</p>
{#form_search#}
</div>
<div class='subnav'><p>{$PALANG.show}
	{if $tab=='all'}<span class='active'>{$PALANG.all}</span>
	{else}<a href="?domain={$smarty.get.domain}&amp;tab=all{if $search != ""}&search={$search}{/if}">{$PALANG.all}</a>{/if}
	{if $tab=='mailbox'}<span class='active'>{$PALANG.pOverview_mailbox_title}</span>
	{else}<a href="?domain={$smarty.get.domain}&amp;tab=mailbox{if $search != ""}&search={$search}{/if}">{$PALANG.pOverview_mailbox_title}</a>{/if}
	{if $tab=='alias'}<span class='active'>{$PALANG.pOverview_alias_title}</span>
	{else}<a href="?domain={$smarty.get.domain}&amp;tab=alias{if $search != ""}&search={$search}{/if}">{$PALANG.pOverview_alias_title}</a>{/if}
	{if $boolconf_alias_domain}
		{if $tab=='alias_domain'}<span class='active'>{$PALANG.pOverview_alias_domain_title}</span>
		{else}<a href="?domain={$smarty.get.domain}&amp;tab=alias_domain{if $search != ""}&search={$search}{/if}">{$PALANG.pOverview_alias_domain_title}</a>{/if}
	{/if}
</p></div>
<br clear="all"/><br/>
{*** Domain Aliases ***}
{if $boolconf_alias_domain}
	{if $tab=='alias_domain' || $tab=='all'}
		{include file="list-virtual_alias_domain.tpl"}
	{/if}
{/if}
{if $tab=='all'}<br />{/if}
{*** Aliases ***}
{if $tab=='alias' || $tab=='all'}
	{$nav_bar_alias.top}
	<table id="alias_table">
		<tr>
			<th colspan="7">{$PALANG.pOverview_alias_title}</th>
		</tr>
	{if $tAlias}
		{include file="list-virtual_alias.tpl"}
	{/if}
	</table>
	{$nav_bar_alias.bottom}
	{if $tCanAddAlias}
		<br /><a href="{#url_create_alias#}&amp;domain={$fDomain|escape:"url"}" class="button">{$PALANG.add_alias}</a><br />
	{/if}
{/if}
{if $tab=='all'}<br />{/if}
{if $tab=='mailbox' || $tab=='all'}
	{$nav_bar_mailbox.top}
	{assign var="colspan" value=9}
	{if $CONF.vacation_control_admin===YES}{assign var="colspan" value="`$colspan+1`"}{/if}
	{if $CONF.alias_control_admin===YES}{assign var="colspan" value="`$colspan+1`"}{/if}
	<table id="mailbox_table">
		<tr>
			<th colspan="{$colspan}">{$PALANG.pOverview_mailbox_title}</th>
		</tr>
	{if $tMailbox}
		{include file="list-virtual_mailbox.tpl"}
	{else}</table>
	{/if}
	{$nav_bar_mailbox.bottom}
	{if $tCanAddMailbox}
		<br /><a href="{#url_create_mailbox#}&amp;domain={$fDomain|escape:"url"}" class="button">{$PALANG.add_mailbox}</a><br />
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
