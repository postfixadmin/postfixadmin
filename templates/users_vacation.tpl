<div id="edit_form">
<form name="vacation" method="post" action="">
<table>
	<tr>
		<td colspan="3"><h3>{$PALANG.pUsersVacation_welcome}</h3></td>
	</tr>
	<tr>
		<td>{$PALANG.pUsersVacation_subject}:</td>
		<td><input type="text" name="fSubject" value="{$tSubject}" /></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td>{$PALANG.pUsersVacation_body}:</td>
		<td>
<textarea rows="10" cols="80" name="fBody">
{$tBody}
</textarea>
		</td>
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
