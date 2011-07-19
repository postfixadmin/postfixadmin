<div id="edit_form">
<form name="alias_domain" method="post" action="">
<table>
	<tr>
		<th colspan="3">{$PALANG.pCreate_alias_domain_welcome}</th>
	</tr>
{if $alias_domains}
	<tr>
		<td>{$PALANG.pCreate_alias_domain_alias}:</td>
		<td><select class="flat" name="alias_domain">{$select_options_alias}</select></td>
		<td>{$PALANG.pCreate_alias_domain_alias_text}</td>
	</tr>
	<tr>
		<td>{$PALANG.pCreate_alias_domain_target}:</td>
		<td><select class="flat" name="target_domain">{$select_options_target}</select></td>
		<td>{$PALANG.pCreate_alias_domain_target_text}</td>
	</tr>
	<tr>
		<td>{$PALANG.pCreate_alias_domain_active}:</td>
		<td><input class="flat" type="checkbox" name="active" value="1"{$fActive}/></td>
		<td>&nbsp;</td>
	</tr>
{/if}
	<tr>
		<td colspan="3" class="standout">{$tMessage}</td>
	</tr>
{if $alias_domains}
	<tr>
		<td>&nbsp;</td>
		<td colspan="2"><input class="button" type="submit" name="submit" value="{$PALANG.pCreate_alias_domain_button}" /></td>
	</tr>
{/if}
</table>
</form>
</div>
