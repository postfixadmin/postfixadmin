<div id="edit_form">
<form name="alias" method="post" action="">
<table>
	<tr>
		<td colspan="3"><h3>{$PALANG.pEdit_alias_welcome}</h3></td>
	</tr>
	<tr>
		<td>{$PALANG.pEdit_alias_address}:</td>
		<td>{$fAddress}</td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td>{$PALANG.pEdit_alias_goto}:</td>
		<td>
			<textarea class="flat" rows="10" cols="60" name="fGoto">
{foreach from=$array item=item}
{$item}
{/foreach}
</textarea>
		</td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td colspan="3" class="hlp_center"><input class="button" type="submit" name="submit" value="{$PALANG.pEdit_alias_button}" /></td>
	</tr>
	<tr>
		<td colspan="3" class="standout">{$tMessage}</td>
	</tr>
</table>
</form>
</div>
