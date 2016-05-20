{literal}
	<script language="JavaScript" src="calendar.js" type="text/javascript"></script>
{/literal}
<div id="edit_form">
<form name="edit-vacation" method="post" action=''>
<input class="flat" type="hidden" name="token" value="{$smarty.session.PFA_token|escape:"url"}" />
<table>
	<tr>
		<th colspan="3">{$PALANG.pUsersVacation_welcome}</th>
	</tr>
	{if !$authentication_has_role.user}
	<tr>
		<td class="label"><label>{$PALANG.pLogin_username}:</label></td>
		<td><em>{$tUseremail}</em></td>
		<td>&nbsp;</td>
	</tr>
	{/if}
	<tr>
		<td class="label"><label>{$PALANG.pUsersVacation_activefrom}:</label></td>
		<td><input class="flat readonly" name="fActiveFrom" value="{$tActiveFrom}" readonly="readonly" />
{literal}
<script language="JavaScript" type="text/javascript">
	new tcal ({
		'formname': 'edit-vacation',
		'controlname': 'fActiveFrom'
	});
</script>
{/literal}
		</td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pUsersVacation_activeuntil}:</label></td>
		<td><input class="flat readonly" name="fActiveUntil" value="{$tActiveUntil}" readonly="readonly" />
{literal}
<script language="JavaScript" type="text/javascript">
	new tcal ({
		'formname': 'edit-vacation',
		'controlname': 'fActiveUntil'
	});
</script>
{/literal}
		</td>
		<td>&nbsp;</td>
	</tr>

    <tr>
        <td class="label"><label>{$PALANG.pVacation_reply_type}:</label></td>
        <td>
			<select class="flat" name="fInterval_Time">
				{html_options options=$select_options selected=$tInterval_Time}
			</select>
		</td>
        <td>&nbsp;</td>
    </tr>
	<tr>
		<td class="label"><label>{$PALANG.subject}:</label></td>
		<td><textarea class="flat" rows="3" cols="60" name="fSubject" >{$tSubject}</textarea></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.message}:</label></td>
		<td><textarea class="flat" rows="10" cols="60" name="fBody" >{$tBody}</textarea></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td>&nbsp;</td>
		<td colspan="2">
			<input class="button" type="submit" name="fChange" value="{$PALANG.pEdit_vacation_set}" />
			<input class="button" type="submit" name="fBack" value="{$PALANG.pEdit_vacation_remove}" />
			<input class="button" type="submit" name="fCancel" value="{$PALANG.exit}" />
		</td>
	</tr>
</table>
</form>
</div>
