<div id="edit_form">
<form name="admin" method="post" action="">
<table>
	<tr>
		<th colspan="4">
			{if $mode == 'edit'}
				{$PALANG.pAdminEdit_admin_welcome}
			{else}
				{$PALANG.pAdminCreate_admin_welcome}
			{/if}
		</th>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pAdminEdit_admin_username}:</label></td>
{if $mode == 'edit'}
		<td>{$username}</td>
		<td colspan="2">&nbsp;</td>
{else}
		<td><input class="flat" type="text" name="fUsername" value="{$tUsername}" /></td>
		<td>{$pAdminCreate_admin_username_text}</td>
		<td><span class="error_msg">{$pAdminCreate_admin_username_text_error}</span></td>
{/if}
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pAdminEdit_admin_password}:</label></td>
		<td><input class="flat" type="password" name="fPassword" /></td>
		<td>&nbsp;</td>
		<td><span class="error_msg">{$admin_password_text_error}</span></td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pAdminEdit_admin_password2}:</label></td>
		<td><input class="flat" type="password" name="fPassword2" /></td>
		<td colspan="2">&nbsp;</td>
	</tr>
{if $mode == 'edit'}
<!-- TODO: these options should also be available in create-admin -->
	<tr>
		<td class="label"><label>{$PALANG.pAdminEdit_admin_active}:</label></td>
		<td><input class="flat" type="checkbox" name="fActive"{$tActive_checked}/></td>
		<td colspan="2">&nbsp;</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pAdminEdit_admin_super_admin}:</label></td>
		<td><input class="flat" type="checkbox" name="fSadmin"{$tSadmin_checked}/></td>
		<td colspan="2">&nbsp;</td>
	</tr>
{/if}
	<tr>
	    <td class="label"><label>{$PALANG.pAdminCreate_admin_address}:</label></td>
		<td><select name="fDomains[]" size="10" multiple="multiple">{$select_options}</select></td>
		<td colspan="2">&nbsp;</td>
	</tr>
	<tr>
		<td>&nbsp;</td>
		<td><input class="button" type="submit" name="submit" 
			value="{if $mode == 'edit'}{$PALANG.save}{else}{$PALANG.pAdminCreate_admin_button}{/if}" /></td>
		<td colspan="2">&nbsp;</td>
	</tr>
</table>
</form>
</div>
