<!-- {$smarty.template} -->
{strip}
{include file="header.tpl"}
<br clear="all" />

{include file='flash_error.tpl'}

<div id="2fa">
<form name="frmLogin" method="post" action="">
<input class="flat" type="hidden" name="token" value="{$smarty.session.PFA_token|escape:"url"}" />
<table id="login_table" cellspacing="10">
	<tr>
		<th colspan="2">{$PALANG.2FA_form_welcome}
	</th>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.2FA_form_code}:</label></td>
		<td><input class="flat" type="text" name="fCode" /></td>
	</tr>
	<!-- 
	<tr>
		<td class="label"><label>{$PALANG.2FA_form_30days_authentication}:</label></td>
		<td><input type="checkbox" value="1" name="f30DaysRemember"/</td>
	</tr>
	-->
	<tr>
		<td class="label">&nbsp;</td>
		<td><input class="button" type="submit" name="submit" value="{$PALANG.2FA_form_submit}" /></td>
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
	document.frmLogin.fCode.focus();
// -->
</script>
{/literal}
</div>



{include file='footer.tpl'}
{/strip}
