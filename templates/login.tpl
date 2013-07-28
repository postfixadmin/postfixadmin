<div id="login">
<form name="frmLogin" method="post" action="">
<table id="login_table" cellspacing="10">
	<tr>
		<th colspan="2">
{if $logintype=='admin'}{$PALANG.pLogin_welcome}
{else}{$PALANG.pUsersLogin_welcome}
{/if}
	</th>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pLogin_username}:</label></td>
		<td><input class="flat" type="text" name="fUsername" /></td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.password}:</label></td>
		<td><input class="flat" type="password" name="fPassword" /></td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pLogin_language}:</label></td>
		<td>{$language_selector}</td>
	</tr>
	<tr>
		<td class="label">&nbsp;</td>
		<td><input class="button" type="submit" name="submit" value="{$PALANG.pLogin_button}" /></td>
	</tr>
{if $logintype == 'admin'}
	<tr>
		<td colspan="2"><a href="users/">{$PALANG.pLogin_login_users}</a></td>
	</tr>
{/if}
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

