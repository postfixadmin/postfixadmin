<div id="login">
<form name="frmLogin" method="post" action="">
<table id="login_table" cellspacing="10">
	<tr>
		<td colspan="2"><h4>{$PALANG.pUsersLogin_welcome}</h4></td>
	</tr>
	<tr>
		<td>{$PALANG.pUsersLogin_username}:</td>
		<td><input class="flat" type="text" name="fUsername" value="{$tUsername}" /></td>
	</tr>
	<tr>
		<td>{$PALANG.pUsersLogin_password}:</td>
		<td><input class="flat" type="password" name="fPassword" /></td>
	</tr>
	<tr>
		<td colspan="2">{$language_selector}</td>
	</tr>
	<tr>
		<td colspan="2" class="hlp_center"><input class="button" type="submit" name="submit" value="{$PALANG.pUsersLogin_button}" /></td>
	</tr>
	<tr>
		<td colspan="2" class="standout">{$tMessage}</td>
	</tr>
</table>
</form>
</div>
