<div id="edit_form">
<form name="alias" method="post" action="">
<table>
	<tr>
		<td colspan="3"><h3>{$PALANG.pEdit_alias_welcome}<br />{$PALANG.pEdit_alias_help}</h3></td>
	</tr>
	<tr>
		<td>{$PALANG.pEdit_alias_address}:</td>
		<td>{$USERID_USERNAME}</td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td>{$PALANG.pEdit_alias_goto}:</td>
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
		<input class="flat" type="radio" name="fForward_and_store" value="YES"{$forward_and_store}/>
		{$PALANG.pEdit_alias_forward_and_store}<br />
		<input class="flat" type="radio" name="fForward_and_store" value="NO" {$forward_only}/>
		{$PALANG.pEdit_alias_forward_only}
		</td>
	</tr>
	<tr>
		<td colspan="3" class="hlp_center">
			<input class="button" type="submit" name="submit" value="{$PALANG.save}" />
			<input class="button" type="submit" name="fCancel" value="{$PALANG.exit}" />
		</td>
	</tr>
	<tr>
		<td colspan="3" class="standout">{$tMessage}</td>
	</tr>
</table>
</form>
</div>
