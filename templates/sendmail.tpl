<div id="edit_form">
<form name="mailbox" method="post" action="">
<table>
	<tr>
		<td colspan="3"><h3>{$PALANG.pSendmail_welcome}</h3></td>
	</tr>
	<tr>
		<td>{$PALANG.pSendmail_admin}:</td>
		<td>{$SESSID_USERNAME}</td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td>{$PALANG.pSendmail_to}:</td>
		<td><input class="flat" type="text" name="fTo" /></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td>{$PALANG.pSendmail_subject}:</td>
		<td><input class="flat" type="text" name="fSubject" value="{$PALANG.pSendmail_subject_text}" /></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td>{$PALANG.pSendmail_body}:</td>
		<td>
		<textarea class="flat" rows="10" cols="60" name="fBody">{$CONF.welcome_text}</textarea>
		</td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td colspan="3" class="hlp_center"><input class="button" type="submit" name="submit" value="{$PALANG.pSendmail_button}" /></td>
	</tr>
	<tr>
		<td colspan="3" class="standout">{$tMessage}</td>
	</tr>
</table>
</form>
</div>
