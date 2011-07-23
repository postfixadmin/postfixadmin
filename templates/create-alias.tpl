{* checkbox *}
<div id="edit_form">
<form name="alias" method="post" action="">
<table>
	<tr>
		<th colspan="4">{$PALANG.pCreate_alias_welcome}</th>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pCreate_alias_address}:</label></td>
		<td>
			<input class="flat" type="text" name="fAddress" value="{$tAddress}" />
			@
			<select class="flat" name="fDomain">{$select_options}</select>
		</td>
		<td>{$PALANG.pCreate_alias_catchall_text}</td>
		<td><span class="error_msg">{$pCreate_alias_address_text_error}</span></td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pCreate_alias_goto}:</label></td>
      	<td><textarea class="flat" rows="10" cols="35" name="fGoto">{$tGoto}</textarea></td>
		<td>{$PALANG.pCreate_alias_goto_text}<br /><br />{$PALANG.pEdit_alias_help}</td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pCreate_alias_active}:</label></td>
		<td><input class="flat" type="checkbox" name="fActive" checked="checked"/></td>
		<td colspan="2">&nbsp;</td>
	</tr>
	<tr>
		<td>&nbsp;</td>
		<td><input class="button" type="submit" name="submit" value="{$PALANG.pCreate_alias_button}" /></td>
		<td colspan="2">&nbsp;</td>
	</tr>
</table>
</form>
</div>
