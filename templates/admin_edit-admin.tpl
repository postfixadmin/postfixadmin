<div id="edit_form">
<form name="alias" method="post" action="">
<table>
	<tr>
		<th colspan="4">{$PALANG.pAdminEdit_admin_welcome}</th>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pAdminEdit_admin_username}:</label></td>
		<td>{$username}</td>
		<td colspan="2">&nbsp;</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pAdminEdit_admin_password}:</label></td>
		<td><input class="flat" type="password" name="fPassword" value=""/></td>
		<td>&nbsp;</td>
		<td><span class="error_msg">{$pAdminEdit_admin_password_text_error}</span></td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pAdminEdit_admin_password2}:</label></td>
		<td><input class="flat" type="password" name="fPassword2" value="" /></td>
		<td colspan="2">&nbsp;</td>
	</tr>
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
	<tr>
	    <td class="label"><label>{$PALANG.pAdminCreate_admin_address}:</label></td>
		<td><select name="fDomains[]" size="10" multiple="multiple">{$select_options}</select></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td>&nbsp;</td>
		<td><input class="button" type="submit" name="submit" value="{$PALANG.save}" /></td>
		<td colspan="2">&nbsp;</td>
	</tr>
</table>
</form>
</div>
