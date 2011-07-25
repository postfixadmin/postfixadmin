{* {$pEdit_mailbox_username_text} nicht gesetzt *}
<div id="edit_form">
<form name="mailbox" method="post" action="">
<table>
	<tr>
		<th colspan="4">{$PALANG.pEdit_mailbox_welcome}</th>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pEdit_mailbox_username}:</label></td>
		<td><em>{$fUsername}</em></td>
		<td>{$pEdit_mailbox_username_text}</td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pEdit_mailbox_password}:</label></td>
		<td><input class="flat" type="password" name="fPassword" /></td>
		<td>&nbsp;</td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pEdit_mailbox_password2}:</label></td>
		<td><input class="flat" type="password" name="fPassword2" /></td>
		<td>&nbsp;</td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pEdit_mailbox_name}:</label></td>
		<td><input class="flat" type="text" name="fName" value="{$tName}" /></td>
		<td>{$pEdit_mailbox_name_text}</td>
		<td>&nbsp;</td>
	</tr>
{if $CONF.quota===YES}
	<tr>
		<td class="label"><label>{$PALANG.pEdit_mailbox_quota} (max: {$tMaxquota}):</label></td>
		<td><input class="flat" type="text" name="fQuota" value="{$tQuota}" /></td>
		<td>{$PALANG.pEdit_mailbox_quota_text}</td>
		<td>{$pEdit_mailbox_quota_text_error}</td>
	</tr>
{/if}
	<tr>
		<td class="label"><label>{$PALANG.pCreate_mailbox_active}:</label></td>
		<td><input class="flat" type="checkbox" name="fActive"{$tActive}/></td>
		<td colspan="2">&nbsp;</td>
	</tr>
	<tr>
   	<td>&nbsp;</td>
		<td>
			<input class="button" type="submit" name="submit" value="{$PALANG.save}" />
			<input class="button" type="submit" name="cancel" value="{$PALANG.exit}"/>
		</td>
		<td colspan="2">&nbsp;</td>
	</tr>
</table>
</form>
</div>
