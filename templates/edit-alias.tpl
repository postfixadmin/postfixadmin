<div id="edit_form">
<form name="alias" method="post" action="">
<table>
	<tr>
		<th colspan="4">
{if $mode == 'edit'}
			{$PALANG.pEdit_alias_welcome}
{else}
			{$PALANG.pCreate_alias_welcome}</th>
{/if}
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pEdit_alias_address}:</label></td>
		<td>
{if $mode == 'edit'}
			<em>{$fAddress}</em>
{else}
			<input class="flat" type="text" name="fAddress" value="{$tAddress}" />
			@
			<select class="flat" name="fDomain">{$select_options}</select>
{/if}
		</td>
		<td>{if $mode == 'create'}{$PALANG.pCreate_alias_catchall_text}{/if}</td>
		<td><span class="error_msg">{$pCreate_alias_address_text_error}</span></td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pEdit_alias_goto}:</label></td>
		<td><textarea class="flat" rows="10" cols="35" name="fGoto">{$tGoto}</textarea></td>
		<td>{$PALANG.pCreate_alias_goto_text}<br /><br />{$PALANG.pEdit_alias_help}</td>
		<td>&nbsp;</td>
	</tr>
{if $mode == 'create'}
<!-- TODO: 'active' should also be available in edit-alias -->
	<tr>
		<td class="label"><label>{$PALANG.pCreate_alias_active}:</label></td>
		<td><input class="flat" type="checkbox" name="fActive" checked="checked"/></td>
		<td colspan="2">&nbsp;</td>
	</tr>
{/if}
	<tr>
		<td>&nbsp;</td>
		<td colspan="3"><input class="button" type="submit" name="submit" 
			value="{if $mode == 'edit'}{$PALANG.save}{else}{$PALANG.pCreate_alias_button}{/if}" /></td>
	</tr>
</table>
</form>
</div>
