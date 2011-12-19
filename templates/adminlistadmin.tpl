{if $admin_properties}
	<table id="admin_table">
		{#tr_header#}
			<td>{$PALANG.pAdminList_admin_username}</td>
			<td>{$PALANG.pAdminList_admin_count}</td>
			<td>{$PALANG.pAdminList_admin_modified}</td>
			<td>{$PALANG.pAdminList_admin_active}</td>
			<td colspan="2">&nbsp;</td>
		</tr>
{foreach from=$admin_properties item=admin}
		{#tr_hilightoff#}
			<td><a href="list-domain.php?username={$admin.username|escape:"url"}">{$admin.username}</a></td>
			<td>
				{if $admin.superadmin == 1}
					{$PALANG.pAdminEdit_admin_super_admin}
				{else}
					{$admin.domain_count}
				{/if}
			</td>
			<td>{$admin.modified}</td>
			<td><a href="{#url_edit_active_admin#}&edit={$admin.username|escape:"url"}">{$admin._active}</a></td>
			<td><a href="{#url_edit_admin#}&edit={$admin.username|escape:"url"}">{$PALANG.edit}</a></td>
			<td><a href="{#url_delete#}?table=admin&amp;delete={$admin.username|escape:"url"}" onclick="return confirm ('{$PALANG.confirm}{$PALANG.pAdminList_admin_username}: {$admin.username}');">{$PALANG.del}</a></td>
		</tr>
{/foreach}
	</table>
	<br /><a href="{#url_create_admin#}" class="button">{$PALANG.pAdminMenu_create_admin}</a><br />
{/if}
