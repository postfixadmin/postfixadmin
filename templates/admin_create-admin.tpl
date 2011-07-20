<div id="edit_form">
<form name="create_admin" method="post" action="">
<table>
	<tr>
		<th colspan="4">{$PALANG.pAdminCreate_admin_welcome}</th>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pAdminCreate_admin_username}:</label></td>
		<td><input class="flat" type="text" name="fUsername" value="{$tUsername}" /></td>
		<td>{$pAdminCreate_admin_username_text}</td>
		<td><span class="error_msg">{$pAdminCreate_admin_username_text_error}</span></td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pAdminCreate_admin_password}:</label></td>
		<td><input class="flat" type="password" name="fPassword" /></td>
		<td>&nbsp;</td>
		<td><span class="error_msg">{$pAdminCreate_admin_password_text_error}</span></td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pAdminCreate_admin_password2}:</label></td>
		<td><input class="flat" type="password" name="fPassword2" /></td>
		<td colspan="2">&nbsp;</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pAdminCreate_admin_address}:</label></td>
		<td>
			<select name="fDomains[]" size="10" multiple="multiple">{$select_options}</select>
		</td>
		<td colspan="2">&nbsp;</td>
	</tr>
	<tr>
		<td>&nbsp;</td>
		<td><input class="button" type="submit" name="submit" value="{$PALANG.pAdminCreate_admin_button}" /></td>
		<td colspan="2">&nbsp;</td>
	</tr>
</table>
</form>
</div>
