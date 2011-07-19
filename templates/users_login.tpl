<div id="login">
<form name="frmLogin" method="post" action="">
<table id="login_table" cellspacing="10">
	<tr>
		<th colspan="2">{$PALANG.pUsersLogin_welcome}</th>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pUsersLogin_username}:</label></td>
		<td><input class="flat" type="text" name="fUsername" value="{$tUsername}" /></td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pUsersLogin_password}:</label></td>
		<td><input class="flat" type="password" name="fPassword" /></td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pLogin_language}:</label></td>
		<td>{$language_selector}</td>
	</tr>
	<tr>
		<td>&nbsp;</td>
		<td><input class="button" type="submit" name="submit" value="{$PALANG.pUsersLogin_button}" /></td>
	</tr>
</table>
</form>
</div>
