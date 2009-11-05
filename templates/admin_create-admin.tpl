<div id="edit_form">
<form name="create_admin" method="post" action="">
<table>
	<tr>
		<td colspan="3"><h3>{$PALANG.pAdminCreate_admin_welcome}</h3></td>
	</tr>
	<tr>
		<td>{$PALANG.pAdminCreate_admin_username}:</td>
		<td><input class="flat" type="text" name="fUsername" value="{$tUsername}" /></td>
		<td>{$pAdminCreate_admin_username_text}</td>
	</tr>
	<tr>
		<td>{$PALANG.pAdminCreate_admin_password}:</td>
		<td><input class="flat" type="password" name="fPassword" /></td>
		<td>{$pAdminCreate_admin_password_text}</td>
	</tr>
	<tr>
		<td>{$PALANG.pAdminCreate_admin_password2}:</td>
		<td><input class="flat" type="password" name="fPassword2" /></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td>{$PALANG.pAdminCreate_admin_address}:</td>
		<td>
			<select name="fDomains[]" size="10" multiple="multiple">{$select_options}</select>
		</td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td colspan="3" class="hlp_center"><input class="button" type="submit" name="submit" value="{$PALANG.pAdminCreate_admin_button}" /></td>
	</tr>
	<tr>
		<td colspan="3" class="standout">{$tMessage}</td>
	</tr>
</table>
</form>
</div>
