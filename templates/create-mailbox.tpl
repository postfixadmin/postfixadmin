{* checkboxes *}
<div id="edit_form">
<form name="mailbox" method="post" action="">
<table>
	<tr>
		<th colspan="4">{$PALANG.pCreate_mailbox_welcome}</th>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pCreate_mailbox_username}:</label></td>
		<td><input class="flat" type="text" name="fUsername" value="{$tUsername}"/></td>
		<td>@
		<select name="fDomain">{$select_options}</select>
		{$pCreate_mailbox_username_text}
		</td>
		<td><span class="error_msg">{$pCreate_mailbox_username_text_error}</span></td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pCreate_mailbox_password}:</label></td>
		<td><input class="flat" type="password" name="fPassword" /></td>
		<td>{$pCreate_mailbox_password_text}</td>
		<td><span class="error_msg">{$pCreate_mailbox_password_text_error}</span></td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pCreate_mailbox_password2}:</label></td>
		<td><input class="flat" type="password" name="fPassword2" /></td>
		<td colspan="2">&nbsp;</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pCreate_mailbox_name}:</label></td>
		<td><input class="flat" type="text" name="fName" value="{$tName}" /></td>
		<td>{$PALANG.pCreate_mailbox_name_text}</td>
		<td>&nbsp;</td>
	</tr>
{if $CONF.quota===YES}
	<tr>
		<td class="label"><label>{$PALANG.pCreate_mailbox_quota}:</label></td>
		<td><input class="flat" type="text" name="fQuota" value="{$tQuota}" /></td>
		<td>{$pCreate_mailbox_quota_text}</td>
		<td><span class="error_msg">{$pCreate_mailbox_quota_text_error}</span></td>
	</tr>
{/if}
	<tr>
		<td class="label"><label>{$PALANG.pCreate_mailbox_active}:</label></td>
		<td><input class="flat" type="checkbox" name="fActive" checked="checked" /></td>
		<td colspan="2">&nbsp;</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pCreate_mailbox_mail}:</label></td>
		<td><input class="flat" type="checkbox" name="fMail" checked="checked" /></td>
		<td colspan="2">&nbsp;</td>
	</tr>
	<tr>
		<td>&nbsp;</td>
		<td><input class="button" type="submit" name="submit" value="{$PALANG.pCreate_mailbox_button}" /></td>
		<td colspan="2">&nbsp;</td>
	</tr>
</table>
</form>
</div>
