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
			{if $CONF.quota==YES}<td>{$PALANG.pOverview_get_quota}</td>{/if}
			{if $CONF.domain_quota==YES}<td>{$PALANG.pAdminList_domain_quota}</td>{/if}
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
			{if $CONF.domain_quota===YES}<td>{$domain.total_quota} / {$domain.quota}</td>{/if}
			{if $CONF.transport==YES}<td>{$domain.transport}</td>{/if}
			<td>{$domain._backupmx}</td>
			<td>{$domain.modified}</td>
			<td><a href="{#url_edit_domain#}&amp;edit={$domain.domain|escape:"url"}&amp;active={if ($domain.active==0)}1{else}0{/if}">{$domain._active}</a></td>
			<td><a href="{#url_edit_domain#}&amp;edit={$domain.domain|escape:"url"}">{$PALANG.edit}</a></td>
			<td><a href="{#url_delete#}?table=domain&amp;delete={$domain.domain|escape:"url"}" onclick="return confirm ('{$PALANG.confirm_domain}{$PALANG.pAdminList_admin_domain}: {$domain.domain}')">{$PALANG.del}</a></td>
		</tr>
{/foreach}
	</table>
{/if}
<br /><a href="{#url_edit_domain#}" class="button">{$PALANG.pAdminMenu_create_domain}</a><br />
