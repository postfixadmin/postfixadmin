<div id="overview">
<form name="frmOverview" method="post" action="">
	<select name="fUsername" onchange="this.form.submit();">
	{$select_options}
	</select>
	<input class="button" type="submit" name="go" value="{$PALANG.pOverview_button}" />
</form>
{#form_search#}
</div>
{if $domain_properties}
	<table id="admin_table">
		{#tr_header#}
			<td>{$PALANG.pAdminList_domain_domain}</td>
			<td>{$PALANG.pAdminList_domain_description}</td>
			<td>{$PALANG.pAdminList_domain_aliases}</td>
			<td>{$PALANG.pAdminList_domain_mailboxes}</td>
			{if $CONF.quota==YES}<td>{$PALANG.pAdminList_domain_maxquota}</td>{/if}
			{if $CONF.transport==YES}<td>{$PALANG.pAdminList_domain_transport}</td>{/if}
			<td>{$PALANG.pAdminList_domain_backupmx}</td>
			<td>{$PALANG.pAdminList_domain_modified}</td>
			<td>{$PALANG.pAdminList_domain_active}</td>
			<td colspan="2">&nbsp;</td>
		</tr>
{foreach from=$domain_properties item=domain}
		{#tr_hilightoff#}
			<td><a href="{#url_list_virtual#}?domain={$domain.domain|escape:"url"}">{$domain.domain}</a></td>
			<td>{$domain.description}</td>
			<td>{$domain.alias_count} / {$domain.aliases}</td>
			<td>{$domain.mailbox_count} / {$domain.mailboxes}</td>
			{if $CONF.quota==YES}<td>{$domain.maxquota}</td>{/if}
			{if $CONF.transport==YES}<td>{$domain.transport}</td>{/if}
			<td>{$domain.backupmx}</td>
			<td>{$domain.modified}</td>
			<td><a href="{#url_edit_active_domain#}?domain={$domain.domain|escape:"url"}">{$domain.active}</a></td>
			<td><a href="{#url_edit_domain#}?domain={$domain.domain|escape:"url"}">{$PALANG.edit}</a></td>
			<td><a href="{#url_delete#}?table=domain&amp;delete={$domain.domain|escape:"url"}" onclick="return confirm ('{$PALANG.confirm_domain}{$PALANG.pAdminList_admin_domain}: {$domain.domain}')">{$PALANG.del}</a></td>
		</tr>
{/foreach}
	</table>
{/if}
<p><a href="{#url_create_domain#}">{$PALANG[$smarty.config.txt_create_domain]}</a></p>
