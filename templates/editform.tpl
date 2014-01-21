<div id="edit_form">
<form name="edit_{$table}" method="post" action="">
<input class="flat" type="hidden" name="table" value="{$table}" />

<table>
	<tr>
		<th colspan="4">{$formtitle}</th>
	</tr>

{foreach key=key item=field from=$struct}
	{if $field.display_in_form == 1}

		{if $table == 'foo' && $key == 'bar'}
			<tr><td>Special handling (complete table row) for {$table} / {$key}</td></tr>
		{else}
			<tr>
				<td class="label">{$field.label}</td>
				<td>
				{if $field.editable == 0}
					{$value_{$key}}
				{else}
					{if $table == 'foo' && $key == 'bar'}
						Special handling (td content) for {$table} / {$key}
					{elseif $field.type == 'bool'}
						<input class="flat" type="checkbox" value='1' name="value[{$key}]"{if {$value_{$key}} == 1} checked="checked"{/if}/>
					{elseif $field.type == 'enum'}
						<select class="flat" name="value[{$key}]">
						{html_options output=$struct.{$key}.options values=$struct.{$key}.options selected=$value_{$key}}
						</select>
					{elseif $field.type == 'list'}
						<select class="flat" name="value[{$key}][]" size="10" multiple="multiple">
						{html_options output=$struct.{$key}.options values=$struct.{$key}.options selected=$value_{$key}}
						</select>

<!-- alternative: 
						<div style='max-height:30em; overflow:auto;'>
							{html_checkboxes name="value[{$key}]" output=$struct.{$key}.options values=$struct.{$key}.options selected=$value_{$key} separator="<br />"}
						</div>
-->
					{elseif $field.type == 'pass'}
						<input class="flat" type="password" name="value[{$key}]" />
					{elseif $field.type == 'txtl'}
						<textarea class="flat" rows="10" cols="35" name="value[{$key}]">{foreach key=key2 item=field2 from=$value_{$key}}{$field2}
{/foreach}</textarea>
					{else}
						<input class="flat" type="text" name="value[{$key}]" value="{$value_{$key}}" />
					{/if}
				{/if}	
				</td>
				<td>{$field.desc}</td>
				<td class="error_msg">{$fielderror.{$key}}</td>
			</tr>
		{/if}

	{/if}
{/foreach}

	<tr>
		<td>&nbsp;</td>
		<td colspan="3"><input class="button" type="submit" name="submit" value="{$submitbutton}" /></td>
	</tr>
</table>

</form>
</div>
