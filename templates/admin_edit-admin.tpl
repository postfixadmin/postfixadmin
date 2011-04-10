<div id="edit_form">
<form name="alias" method="post" action="">
<table>
	<tr>
		<td colspan="3"><h3>{$PALANG.pAdminEdit_admin_welcome}</h3></td></tr>
	<tr>
		<td>{$PALANG.pAdminEdit_admin_username}:</td>
		<td>{$username}</td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td>{$PALANG.pAdminEdit_admin_password}:</td>
		<td><input class="flat" type="password" name="fPassword" value=""/></td>
		<td>{$pAdminEdit_admin_password_text}</td>
	</tr>
	<tr>
		<td>{$PALANG.pAdminEdit_admin_password2}:</td>
		<td><input class="flat" type="password" name="fPassword2" value="" /></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td>{$PALANG.pAdminEdit_admin_active}:</td>
		<td><input class="flat" type="checkbox" name="fActive"{$tActive_checked}/></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td>{$PALANG.pAdminEdit_admin_super_admin}:</td>
		<td><input class="flat" type="checkbox" name="fSadmin"{$tSadmin_checked}/></td>
                <td>&nbsp;</td>
	</tr>
	<tr>
	       <td>{$PALANG.pAdminCreate_admin_address}:</td>
		<td>
		<select name="fDomains[]" size="10" multiple="multiple">{$select_options}</select>
		<td>&nbsp;</td>
		</td>
	</tr>
	<tr>
		<td colspan="3" class="hlp_center"><input class="button" type="submit" name="submit" value="{$PALANG.save}" /></td>
	</tr>
	<tr>
		<td colspan="3" class="standout">{$tMessage}</td>
	</tr>
</table>
</form>
</div>
