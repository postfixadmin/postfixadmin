{literal}
	<script language="JavaScript" type="text/javascript">
	function newLocation() {
		window.location="{/literal}{$fCanceltarget}{literal}"
	}
	</script>
	<script language="JavaScript" src="calendar.js" type="text/javascript"></script>
{/literal}
<div id="edit_form">
<form name="edit-vacation" method="post" action=''>
<table>
	<tr>
		<th colspan="3">{$PALANG.pUsersVacation_welcome}</th>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pUsersLogin_username}:</label></td>
		<td><em>{$tUseremail}</em></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pUsersVacation_activefrom}:</label></td>
		<td><input class="flat" name="activefrom" value="{$tActiveFrom}" readonly="readonly" style="background:#eee;"/>
{literal}
<script language="JavaScript" type="text/javascript">
	new tcal ({
		'formname': 'edit-vacation',
		'controlname': 'activefrom'
	});
</script>
{/literal}
		</td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pUsersVacation_activeuntil}:</label></td>
		<td><input class="flat" name="activeuntil" value="{$tActiveUntil}" readonly="readonly" style="background:#eee;"/>
{literal}
<script language="JavaScript" type="text/javascript">
	new tcal ({
		'formname': 'edit-vacation',
		'controlname': 'activeuntil'
	});
</script>
{/literal}
		</td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pUsersVacation_subject}:</label></td>
		<td><textarea class="flat" rows="3" cols="60" name="fSubject" >{$tSubject}</textarea></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pUsersVacation_body}:</label></td>
		<td><textarea class="flat" rows="10" cols="60" name="fBody" >{$tBody}</textarea></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td>&nbsp;</td>
		<td colspan="2">
			<input class="button" type="submit" name="fChange" value="{$PALANG.pEdit_vacation_set}" />
			<input class="button" type="submit" name="fBack" value="{$PALANG.pEdit_vacation_remove}" />
			<input class="button" type="button" name="fCancel" value="{$PALANG.exit}" onclick="newLocation()" />
		</td>
	</tr>
</table>
</form>
</div>
