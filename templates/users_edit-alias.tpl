<div id="edit_form">
<form name="alias" method="post" action="">
<table>
	<tr>
		<th colspan="3">{$PALANG.pEdit_alias_welcome}<br /><em>{$PALANG.pEdit_alias_help}</em></th>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.alias}:</label></td>
		<td><em>{$USERID_USERNAME}</em></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.to}:</label></td>
		<td><textarea class="flat" rows="4" cols="50" name="fGoto">
{foreach from=$tGotoArray item=address}
{$address}
{/foreach}
</textarea>
		</td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td>&nbsp;</td>
		<td colspan="2">
		<input class="flat" type="radio" name="fForward_and_store" value="1"{$forward_and_store}/>
		{$PALANG.pEdit_alias_forward_and_store}<br />
		<input class="flat" type="radio" name="fForward_and_store" value="0" {$forward_only}/>
		{$PALANG.pEdit_alias_forward_only}
		</td>
	</tr>
	<tr>
		<td>&nbsp;</td>
		<td>
			<input class="button" type="submit" name="submit" value="{$PALANG.save}" />
			<input class="button" type="submit" name="fCancel" value="{$PALANG.exit}" />
		</td>
		<td>&nbsp;</td>
	</tr>
</table>
</form>
</div>
