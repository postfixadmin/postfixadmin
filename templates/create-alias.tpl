{* checkbox *}
<div id="edit_form">
<form name="alias" method="post" action="">
<table>
	<tr>
		<td colspan="3"><h3>{$PALANG.pCreate_alias_welcome}</h3></td>
	</tr>
	<tr>
		<td>{$PALANG.pCreate_alias_address}</td>
		<td><input class="flat" type="text" name="fAddress" value="{$tAddress}" /></td>
		<td>@
		<select class="flat" name="fDomain">{$select_options}</select>
		{$pCreate_alias_address_text}
		</td>
	</tr>
	<tr>
		<td>{$PALANG.pCreate_alias_goto}:</td>
      	<td colspan="2"><textarea class="flat" rows="10" cols="60" name="fGoto">{$tGoto}</textarea></td>
	</tr>
	<tr>
		<td>{$PALANG.pCreate_alias_active}:</td>
		<td><input class="flat" type="checkbox" name="fActive" checked="checked"/></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td colspan="3" class="hlp_center"><input class="button" type="submit" name="submit" value="{$PALANG.pCreate_alias_button}" /></td>
	</tr>
	<tr>
		<td colspan="3" class="standout">{$tMessage}</td>
	</tr>
	<tr>
		<td colspan="3" class="help_text">{$PALANG.pCreate_alias_catchall_text}</td>
	</tr>
</table>
</form>
</div>
