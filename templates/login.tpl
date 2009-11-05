<div id="login">
<form name="frmLogin" method="post" action="">
<table id="login_table" cellspacing="10">
	<tr>
		<td colspan="2"><h4>{$PALANG.pLogin_welcome}</h4></td>
	</tr>
	<tr>
		<td>{$PALANG.pLogin_username}:</td>
		<td><input class="flat" type="text" name="fUsername" value="{$tUsername}" /></td>
	</tr>
	<tr>
		<td>{$PALANG.pLogin_password}:</td>
		<td><input class="flat" type="password" name="fPassword" /></td>
	</tr>
	<tr>
		<td colspan="2">{$language_selector}</td>
	</tr>
	<tr>
		<td colspan="2" class="hlp_center"><input class="button" type="submit" name="submit" value="{$PALANG.pLogin_button}" /></td>
	</tr>
	<tr>
		<td colspan="2" class="standout">{$tMessage}</td>
	</tr>
	<tr>
		<td colspan="2"><a href="users/">{$PALANG.pLogin_login_users}</a></td>
	</tr>
</table>
</form>
{literal}
<script type="text/javascript">
<!--
	document.frmLogin.fUsername.focus();
// -->
</script>
{/literal}
</div>

