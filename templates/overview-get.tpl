<div id="overview">
<form name="frmOverview" method="get" action="">
	<select class="flat" name="domain" onchange="this.form.submit();">
	{$select_options}
	</select>
	<input class="button" type="submit" name="go" value="{$PALANG.pOverview_button}" />
</form>
{#form_search#}
</div>
<table id="overview_table">
	<tr>
		<td colspan="5"><h3>{$PALANG.pOverview_title}</h3></td>
	</tr>
	{#tr_header#}
		<td>{$PALANG.pOverview_get_domain}</td>
		<td>{$PALANG.pOverview_get_aliases}</td>
		<td>{$PALANG.pOverview_get_mailboxes}</td>
		{if $CONF.quota===YES}<td>{$PALANG.pOverview_get_quota}</td>{/if}
	</tr>
{foreach from=$domain_properties item=domain }
		{#tr_hilightoff#}
			<td><a href="{#url_list_virtual#}?domain={$domain.domain|escape:"url"}">{$domain.domain}</a></td>
			<td>{$domain.alias_count} / {$domain.aliases}</td>
			<td>{$domain.mailbox_count} / {$domain.mailboxes}</td>
			{if $CONF.quota===YES}<td>{$domain.maxquota}</td>{/if}
		</tr>
{/foreach}
</table>
