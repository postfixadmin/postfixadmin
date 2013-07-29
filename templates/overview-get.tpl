<div id="overview">
<form name="frmOverview" method="get" action="">
	<select name="domain" onchange="this.form.submit();">
	{$select_options}
	</select>
	<input class="button" type="submit" name="go" value="{$PALANG.go}" />
</form>
{#form_search#}
</div>
<table id="overview_table">
	<tr>
		<th colspan="5">{$PALANG.pOverview_title}</th>
	</tr>
	{#tr_header#}
		<td>{$PALANG.domain}</td>
		<td>{$PALANG.aliases}</td>
		<td>{$PALANG.mailboxes}</td>
		{if $CONF.quota===YES}<td>{$PALANG.pOverview_get_quota}</td>{/if}
		{if $CONF.domain_quota===YES}<td>{$PALANG.pAdminList_domain_quota}</td>{/if}
	</tr>
{foreach from=$domain_properties item=domain}
		{#tr_hilightoff#}
			<td><a href="{#url_list_virtual#}?domain={$domain.domain|escape:"url"}">{$domain.domain}</a></td>
			<td>{$domain.alias_count} / {$domain.aliases}</td>
			<td>{$domain.mailbox_count} / {$domain.mailboxes}</td>
			{if $CONF.quota===YES}<td>{$domain.maxquota}</td>{/if}
			{if $CONF.domain_quota===YES}<td>{$domain.total_quota} / {$domain.quota}</td>{/if}
		</tr>
{/foreach}
</table>
