	{#tr_header#}
		{if $CONF.show_status===YES}
			<td></td>
		{/if}
		<td>{$PALANG.pOverview_alias_address}</td>
		<td>{$PALANG.pOverview_alias_goto}</td>
		<td>{$PALANG.pOverview_alias_modified}</td>
		<td>{$PALANG.pOverview_alias_active}</td>
		<td colspan="2">&nbsp;</td>
	</tr>
	{foreach from=$tAlias item=item key=i}
		{#tr_hilightoff#}
		{if $CONF.show_status===YES}
			<td>{$gen_show_status[$i]}</td>
		{/if}
		<td>
			{if $search eq ""}
				{$item.address}
			{else}
				{$item.address|replace:$search:"<span class='searchresult'>$search</span>"}
			{/if}
		</td>
		{if $CONF.alias_goto_limit>0}
			<td><i>sorry, alias_goto_limit > 0 not handled</i></td>
		{else}
			<td>
				{foreach key=key2 item=singlegoto from=$item.goto}

				{if $search eq ""}
					{$singlegoto}<br />
				{else}
					{$singlegoto|replace:$search:"<span class='searchresult'>$search</span>"}<br />
				{/if}

				{/foreach}
			</td>
		{/if}
		<td>{$item.modified}</td>
		{if $authentication_has_role.global_admin==true}
			{assign var="address" value=$item.address|escape:"url"}
			<td><a href="edit-active.php?alias={$item.address|escape:"url"}&amp;domain={$fDomain|escape:"url"}&amp;return={$file|escape:"url"}?domain={$fDomain|escape:"url"}">{if $item.active==1}{$PALANG.YES}{else}{$PALANG.NO}{/if}</a></td>
			<td><a href="edit.php?table=alias&amp;edit={$item.address|escape:"url"}">{$PALANG.edit}</a></td>
			<td><a href="delete.php?table=alias&amp;delete={$item.address|escape:"url"}&amp;domain={$fDomain|escape:"url"}" onclick="return confirm ('{$PALANG.confirm}{$PALANG.pOverview_get_aliases}: {$item.address}');">{$PALANG.del}</a></td>
		{else}
			{if $CONF.special_alias_control===YES || $check_alias_owner[$i]==true}
				<td><a href="edit-active.php?alias={$item.address|escape:"url"}&amp;domain={$fDomain|escape:"url"}">{if $item.active==1}{$PALANG.YES}{else}{$PALANG.NO}{/if}</a></td>
				<td><a href="edit.php?table=alias&amp;edit={$item.address|escape:"url"}">{$PALANG.edit}</a></td>
				<td><a href="delete.php?table=alias&amp;delete={$item.address|escape:"url"}&amp;domain={$fDomain|escape:"url"}" onclick="return confirm ('{$PALANG.confirm}{$PALANG.pOverview_get_aliases}: {$item.address}');">{$PALANG.del}</a></td>
			{else}
				<td>{if $item.active==1}{$PALANG.YES}{else}{$PALANG.NO}{/if}</td>
				<td>&nbsp;</td>
				<td>&nbsp;</td>
			{/if}
		{/if}
		</tr>
	{/foreach}

