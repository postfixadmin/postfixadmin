{*** Domain Aliases ***}
<table id="alias_domain_table">
	<tr>
		<th colspan="6">{$PALANG.pOverview_alias_domain_title}</th>
	</tr>
	{if $tAliasDomains|@count>0}
		{if $tAliasDomains|@count>0} {* -> HAT alias-domains *}
			{#tr_header#}
			<td>{$PALANG.pOverview_alias_address}</td>
			<td>{$PALANG.to}</td>
			<td>{$PALANG.last_modified}</td>
			<td>{$PALANG.active}</td>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
			</tr>
			{foreach from=$tAliasDomains item=item}
				{#tr_hilightoff#}
				<td>{if $item.alias_domain != $fDomain}<a href="{$smarty.config.url_list_virtual}?domain={$item.alias_domain|escape:"url"}">{/if}
					{if $search eq ""}
						{$item.alias_domain}
					{else}
						{$item.alias_domain|replace:$search:"<span class='searchresult'>$search</span>"}
					{/if}
					{if $item.alias_domain != $fDomain}</a>{/if}</td>
				<td>{if $item.target_domain != $fDomain}<a href="{$smarty.config.url_list_virtual}?domain={$item.target_domain|escape:"url"}">{/if}
					{if $search eq ""}
						{$item.target_domain}
					{else}
						{$item.target_domain|replace:$search:"<span class='searchresult'>$search</span>"}
					{/if}
					{if $item.target_domain != $fDomain}</a>{/if}</td>
				<td>{$item.modified}</td>
				<td><a href="{#url_editactive#}aliasdomain&amp;id={$item.alias_domain|escape:"url"}&amp;active={if ($item.active==0)}1{else}0{/if}&amp;token={$smarty.session.PFA_token|escape:"url"}">{if $item.active==1}{$PALANG.YES}{else}{$PALANG.NO}{/if}</a></td>
				<td><a href="{#url_create_alias_domain#}&amp;edit={$item.alias_domain|escape:"url"}">{$PALANG.edit}</a></td>
				<td><a href="{#url_delete#}?table=aliasdomain&amp;delete={$item.alias_domain|escape:"url"}&amp;token={$smarty.session.PFA_token|escape:"url"}"
					onclick="return confirm ('{$PALANG.confirm}{$PALANG.pOverview_get_alias_domains}: {$item.alias_domain} -&gt; {$item.target_domain}');">{$PALANG.del}</a></td>
				</tr>
			{/foreach}
		{/if}
	{/if}
</table>
{if $can_create_alias_domain}
	<br/>
	<br /><a href="{#url_create_alias_domain#}&amp;target_domain={$fDomain|escape:"url"}" class="button">{$PALANG.add_alias_domain}</a><br />

{/if}
