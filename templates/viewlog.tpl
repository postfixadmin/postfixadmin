<div id="overview">
<form name="frmOverview" method="post" action="">
	<select name="fDomain" onchange="this.form.submit();">{$select_options}</select>
	<input class="button" type="submit" name="go" value="{$PALANG.go}" />
</form>
</div>
{if $tLog}
<table id="log_table">
	<tr>
		<th colspan="5">{$PALANG.pViewlog_welcome} {$fDomain}</th>
	</tr>
	{#tr_header#}
		<td>{$PALANG.pViewlog_timestamp}</td>
		<td>{$PALANG.admin}</td>
		<td>{$PALANG.domain}</td>
		<td>{$PALANG.pViewlog_action}</td>
		<td>{$PALANG.pViewlog_data}</td>
	</tr>
	{assign var="PALANG_pViewlog_data" value=$PALANG.pViewlog_data}

	{foreach from=$tLog item=item}
		{assign var=log_data value=$item.data|truncate:35:"...":true}
		{assign var=item_data value=$item.data}
		{$smarty.config.tr_hilightoff|replace:'>':" style=\"cursor:pointer;\" onclick=\"alert('$PALANG_pViewlog_data = $item_data')\">"}
		<td nowrap="nowrap">{$item.timestamp}</td>
		<td nowrap="nowrap">{$item.username}</td>
		<td nowrap="nowrap">{$item.domain}</td>
		<td nowrap="nowrap">{$item.action}</td>
		<td nowrap="nowrap">{$log_data}</td>
		</tr>
{/foreach}
</table>
{/if}
