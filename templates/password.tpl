<div id="edit_form">
<form name="password" method="post" action="">
<input class="flat" type="hidden" name="token" value="{$smarty.session.PFA_token|escape:"url"}" />
<table>
	<tr>
		<th colspan="3">{$PALANG.pPassword_welcome}</th>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pPassword_admin}:</label></td>
		<td><em>{$SESSID_USERNAME}</em></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pPassword_password_current}:</label></td>
		<td><input class="flat" type="password" name="fPassword_current" /></td>
		<td class="error_msg">{$pPassword_password_current_text}</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pPassword_password}:</label></td>
		<td><input class="flat" type="password" name="fPassword" /></td>
		<td class="error_msg">{$pPassword_password_text}</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pPassword_password2}:</label></td>
		<td><input class="flat" type="password" name="fPassword2" /></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td class="label">&nbsp;</td>
		<td>
			<input class="button" type="submit" name="submit" value="{$PALANG.change_password}" />
			{if $authentication_has_role.user}
				<input class="button" type="submit" name="fCancel" value="{$PALANG.exit}" />
			{/if}
		<td>&nbsp;</td>
	</tr>
</table>
</form>
</div>
