{* checkboxes *}
<div id="edit_form">
<form name="mailbox" method="post" action="">
<table>
	<tr>
		<td colspan="3"><h3>{$PALANG.pCreate_mailbox_welcome}</h3></td>
	</tr>
	<tr>
		<td>{$PALANG.pCreate_mailbox_username}:</td>
		<td><input class="flat" type="text" name="fUsername" value="{$tUsername}"/></td>
		<td>@
		<select name="fDomain">{$select_options}</select>
		{$pCreate_mailbox_username_text}
		</td>
	</tr>
	<tr>
		<td>{$PALANG.pCreate_mailbox_password}:</td>
		<td><input class="flat" type="password" name="fPassword" /></td>
		<td>{$pCreate_mailbox_password_text}</td>
	</tr>
	<tr>
		<td>{$PALANG.pCreate_mailbox_password2}:</td>
		<td><input class="flat" type="password" name="fPassword2" /></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td>{$PALANG.pCreate_mailbox_name}:</td>
		<td><input class="flat" type="text" name="fName" value="{$tName}" /></td>
		<td>{$PALANG.pCreate_mailbox_name_text}</td>
	</tr>
{if $CONF.quota===YES}
	<tr>
		<td>{$PALANG.pCreate_mailbox_quota}:</td>
		<td><input class="flat" type="text" name="fQuota" value="{$tQuota}" /></td>
		<td>{$pCreate_mailbox_quota_text}</td>
	</tr>
{/if}
	<tr>
		<td>{$PALANG.pCreate_mailbox_active}:</td>
		<td><input class="flat" type="checkbox" name="fActive" checked="checked" /></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td>{$PALANG.pCreate_mailbox_mail}:</td>
		<td><input class="flat" type="checkbox" name="fMail" checked="checked" /></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td colspan="3" class="hlp_center"><input class="button" type="submit" name="submit" value="{$PALANG.pCreate_mailbox_button}" /></td>
	</tr>
	<tr>
		<td colspan="3" class="standout">{$tMessage}</td>
	</tr>
</table>
</form>
</div>
