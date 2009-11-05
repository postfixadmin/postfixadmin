{*** Domain Aliases ***}
<table id="alias_domain_table">
	<tr>
		<td colspan="4"><h3>{$PALANG.pOverview_alias_domain_title}</h3></td>
	</tr>
	{if $tAliasDomains|@count>0 || $tTargetDomain|@count>1}
		{if $tAliasDomains|@count>0} {* -> HAT alias-domains *}
			{#tr_header#}
			<td>{$PALANG.pOverview_alias_domain_aliases}</td>
			<td>{$PALANG.pOverview_alias_domain_modified}</td>
			<td>{$PALANG.pOverview_alias_domain_active}</td>
			<td>&nbsp;</td>
			</tr>
			{foreach from=$tAliasDomains item=item}
				{#tr_hilightoff#}
				<td><a href="{$smarty.config.url_list_virtual}?domain={$item.alias_domain|escape:"url"}&amp;limit={$current_limit|escape:"url"}">{$item.alias_domain}</a></td>
				<td>{$item.modified}</td>
				<td><a href="{#url_edit_active#}?alias_domain=true&amp;domain={$item.alias_domain|escape:"url"}&amp;return={$smarty.config.url_list_virtual|escape:"url"}?domain={$fDomain|escape:"url"}&amp;limit={$current_limit|escape:"url"}">{if $item.active==1}{$PALANG.YES}{else}{$PALANG.NO}{/if}</a></td>
				<td><a href="{#url_delete#}?table=alias_domain&amp;delete={$item.alias_domain|escape:"url"}&amp;domain={$fDomain|escape:"url"}" onclick="return confirm ('{$PALANG.confirm}{$PALANG.pOverview_get_alias_domains}: {$item.alias_domain}');">{$PALANG.del}</a></td>
				</tr>
			{/foreach}
		{/if}
		{if $tTargetDomain|@count>1} {* IST alias-domain *}
			<tr class="header">
				<td>{$PALANG_pOverview_alias_domain_target}</td>
				<td>{$PALANG.pOverview_alias_domain_modified}</td>
				<td>{$PALANG.pOverview_alias_domain_active}</td>
				<td>&nbsp;</td>
			</tr>
			{#tr_hilightoff#}
				<td><a href="{$smarty.config.url_list_virtual}?domain={$tTargetDomain.target_domain|escape:"url"}&amp;limit={$current_limit|escape:"url"}">{$tTargetDomain.target_domain}</a></td>
				<td>{$tTargetDomain.modified}</td>
				<td><a href="{#url_edit_active#}?alias_domain=true&amp;domain={$fDomain|escape:"url"}&amp;return={$smarty.config.url_list_virtual|escape:"url"}?domain={$fDomain|escape:"url"}&amp;limit={$current_limit|escape:"url"}">{if $tTargetDomain.active==1}{$PALANG.YES}{else}{$PALANG.NO}{/if}</a></td>
				<td><a href="{#url_delete#}?table=alias_domain&amp;delete={$fDomain|escape:"url"}&amp;domain={$fDomain|escape:"url"}" onclick="return confirm ('{$PALANG.confirm}{$PALANG.pOverview_get_alias_domains}: {$fDomain})');">{$PALANG.del}</a></td>
			</tr>
		{/if}
	{/if}
</table>
{if $tTargetDomain|@count<2}
	<p><a href="{#url_create_alias_domain#}?target_domain={$fDomain|escape:"url"}">{$PALANG.pMenu_create_alias_domain}</a></p>
{/if}
