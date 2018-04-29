<div id="edit_form">
<form name="mailbox" method="post">
<table>
	<tr>
		<td colspan="3"><h3>{$PALANG.pPassword_welcome}</h3></td>
	</tr>
	<tr>
		<td>{$PALANG.pLogin_username} :</td>
		<td><input class="flat" type="text" name="fUsername" value="{$tUsername}" /></td>
	</tr>
	<tr>
		<td>{$PALANG.pPassword_password_code} :</td>
		<td><input class="flat" type="text" name="fCode" value="{$tCode}" /></td>
	</tr>
	<tr>
		<td>{$PALANG.pPassword_password} :</td>
		<td><input class="flat" type="password" name="fPassword" /></td>
	</tr>
	<tr>
		<td>{$PALANG.pPassword_password2} :</td>
		<td><input class="flat" type="password" name="fPassword2" /></td>
	</tr>
	<tr>
		<td colspan="2" class="hlp_center"><input class="button" type="submit" name="submit" value="{$PALANG.change_password}" /></td>
	</tr>
</table>
</form>
</div>
