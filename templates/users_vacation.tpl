{literal}
	<script type="text/javascript">
	function newLocation() {
		window.location="{/literal}{$fCanceltarget}{literal}"
	}
	</script>
	<script language="JavaScript" src="calendar.js"></script>
	<link rel="stylesheet" href="css/calendar.css">
{/literal}
<div id="edit_form">
<form name="vacation" method="post" action=''>
<table>
	<tr>
		<td colspan="3"><h3>{$PALANG.pUsersVacation_welcome}</h3></td>
	</tr>
	<tr>
		<td>{$PALANG.pUsersVacation_activefrom}:</td>
		<td><input name="fActiveFrom" value="{$tActiveFrom}" readonly="readonly" style="background:#eee;"/>
{literal}
<script language="JavaScript">
	new tcal ({
		'formname': 'vacation',
		'controlname': 'fActiveFrom'
	});
</script>
{/literal}
		</td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td>{$PALANG.pUsersVacation_activeuntil}:</td>
		<td><input name="fActiveUntil" value="{$tActiveUntil}" readonly="readonly" style="background:#eee;"/>
{literal}
<script language="JavaScript">
	new tcal ({
		'formname': 'vacation',
		'controlname': 'fActiveUntil'
	});
</script>
{/literal}
		</td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td>{$PALANG.pUsersVacation_subject}:</td>
		<td><textarea class="flat" rows="3" cols="60" name="fSubject" >{$tSubject}</textarea></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td>{$PALANG.pUsersVacation_body}:</td>
		<td><textarea class="flat" rows="10" cols="60" name="fBody" >{$tBody}</textarea></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td colspan="3" class="hlp_center">
			<input class="button" type="submit" name="fAway" value="{$PALANG.pUsersVacation_button_away}" />
			<input class="button" type="submit" name="fBack" value="{$PALANG.pUsersVacation_button_back}" />
			<input class="button" type="submit" name="fCancel" value="{$PALANG.exit}" />
		</td>
	</tr>
	<tr>
		<td colspan="3" class="standout">{$tMessage}</td>
	</tr>
</table>
</form>
</div>
