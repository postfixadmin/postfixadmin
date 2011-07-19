<div id="edit_form">
<form name="alias" method="post" action="">
<table>
	<tr>
		<th colspan="3">{$PALANG.pEdit_alias_welcome}<br /><em>{$PALANG.pEdit_alias_help}</em></th>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pEdit_alias_address}:</label></td>
		<td><em>{$fAddress}</em></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pEdit_alias_goto}:</label></td>
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
		<td>&nbsp;</td>
		<td colspan="2"><input class="button" type="submit" name="submit" value="{$PALANG.save}" /></td>
	</tr>
	<tr>
		<td colspan="3" class="standout">{$tMessage}</td>
	</tr>
</table>
</form>
</div>
