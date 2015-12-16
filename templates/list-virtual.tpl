{assign var="file" value=$smarty.config.url_list_virtual}
<div id="overview">
<form name="frmOverview" method="get" action="{$smarty.config.url_list_virtual}">
	{html_options name='domain' output=$domain_list values=$domain_list selected=$domain_selected onchange="this.form.submit();"}
	<input type="hidden" name="limit" value="0" />
	<noscript><input class="button" type="submit" name="go" value="{$PALANG.go}" /></noscript>
</form>
{if isset($search._)}
	<h4>{$PALANG.pSearch_welcome} {$search._}</h4>
{else}
	<h4>{$PALANG.pOverview_welcome}{$fDomain}</h4>
	<p>{$PALANG.aliases}: {$limit.alias_count} / {$limit.aliases}</p>
	<p>{$PALANG.mailboxes}: {$limit.mailbox_count} / {$limit.mailboxes}</p>
{/if}
{#form_search#}
</div>
<div class='subnav'><p>{$PALANG.show}
	{if isset($search._)}
		{assign var="searchsuffix" value="&search[_]={$search._}"}
	{else}
		{assign var="searchsuffix" value=""}
	{/if}

	{if $tab=='all'}<span class='active'>{$PALANG.all}</span>
	{else}<a href="?domain={$smarty.get.domain}&amp;tab=all{$searchsuffix}">{$PALANG.all}</a>{/if}
	{if $tab=='mailbox'}<span class='active'>{$PALANG.pOverview_mailbox_title}</span>
	{else}<a href="?domain={$smarty.get.domain}&amp;tab=mailbox{$searchsuffix}">{$PALANG.pOverview_mailbox_title}</a>{/if}
	{if $tab=='alias'}<span class='active'>{$PALANG.pOverview_alias_title}</span>
	{else}<a href="?domain={$smarty.get.domain}&amp;tab=alias{$searchsuffix}">{$PALANG.pOverview_alias_title}</a>{/if}
	{if $boolconf_alias_domain}
		{if $tab=='alias_domain'}<span class='active'>{$PALANG.pOverview_alias_domain_title}</span>
		{else}<a href="?domain={$smarty.get.domain}&amp;tab=alias_domain{$searchsuffix}">{$PALANG.pOverview_alias_domain_title}</a>{/if}
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
	{include file="list-virtual_alias.tpl"}
	{$nav_bar_alias.bottom}
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
