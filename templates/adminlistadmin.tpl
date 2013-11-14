{if $admin_properties}
	<table id="admin_table">
		{#tr_header#}
			<td>{$PALANG.admin}</td>
			<td>{$PALANG.pAdminList_admin_count}</td>
			<td>{$PALANG.last_modified}</td>
			<td>{$PALANG.active}</td>
			<td colspan="2">&nbsp;</td>
		</tr>
{foreach from=$admin_properties item=admin}
		{#tr_hilightoff#}
			<td><a href="list-domain.php?username={$admin.username|escape:"url"}">{$admin.username}</a></td>
			<td>
				{if $admin.superadmin == 1}
					{$PALANG.super_admin}
				{else}
					{$admin.domain_count}
				{/if}
			</td>
			<td>{$admin.modified}</td>
			<td><a href="{#url_editactive#}admin&amp;id={$admin.username|escape:"url"}&amp;active={if ($admin.active==0)}1{else}0{/if}&amp;token={$smarty.session.PFA_token|escape:"url"}">{$admin._active}</a></td>
			<td><a href="{#url_edit_admin#}&amp;edit={$admin.username|escape:"url"}">{$PALANG.edit}</a></td>
			<td><a href="{#url_delete#}?table=admin&amp;delete={$admin.username|escape:"url"}&amp;token={$smarty.session.PFA_token|escape:"url"}" 
				onclick="return confirm ('{$PALANG.confirm}{$PALANG.admin}: {$admin.username}');">{$PALANG.del}</a></td>
		</tr>
{/foreach}
	</table>
	<br /><a href="{#url_create_admin#}" class="button">{$PALANG.pAdminMenu_create_admin}</a><br />
{/if}
