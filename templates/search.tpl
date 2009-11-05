<div id="overview">
<form name="search" method="post" action="{#url_search#}">
<table width="750">
	<tr>
		<td>
			<h4>{$PALANG.pSearch_welcome}{$fSearch}</h4>
		</td>
		<td>{$PALANG.pSearch}:<input name="search" /></td>
{if $authentication_has_role.global_admin}
		<td></td>
{/if}
		<td align="right">
			<select class="flat" name="fDomain" >{$select_options}</select>
{if $authentication_has_role.global_admin}
			<input class="button" type="submit" name="fGo" value="{$PALANG.pReturn_to} {$PALANG.pAdminMenu_list_virtual}" /></td>
{else}
			<input class="button" type="submit" name="fGo" value="{$PALANG.pReturn_to} {$PALANG.pMenu_overview}" /></td>
{/if}
	</tr>
</table>
</form>
</div>
{if $tAlias}
<table id="alias_table">
	<tr>
		<td colspan="5"><h3>{$PALANG.pOverview_alias_title}</h3></td>
	</tr>
	{#tr_header#}
		<td>{$PALANG.pOverview_alias_address}</td>
		<td>{$PALANG.pOverview_alias_goto}</td>
		<td>{$PALANG.pOverview_alias_modified}</td>
		<td>{$PALANG.pOverview_alias_active}</td>
		<td colspan="2">&nbsp;</td>
	</tr>
	{foreach from=$tAlias item=item key=i}
		{#tr_hilightoff#}
		<td>{$item.display_address}</td>
		<td>{$item.goto}</td>
		<td>{$item.modified}</td>
		{if $CONF.special_alias_control===YES || $authentication_has_role.global_admin}
			<td><a href="edit-active.php?alias={$item.address|escape:"url"}&amp;domain={$item.domain|escape:"url"}&amp;return=search.php?search={$fSearch|escape:"url"}">{$item.active}</a></td>
			<td><a href="edit-alias.php?address={$item.address|escape:"url"}&amp;domain={$item.domain|escape:"url"}">{$PALANG.edit}</a></td>
			<td><a href="delete.php?table=alias&amp;delete={$item.address|escape:"url"}&amp;domain={$item.domain|escape:"url"}" onclick="return confirm ('{$PALANG.confirm}{$PALANG.pOverview_get_aliases|escape:"url"}: {$item.address|escape:"url"}');">{$PALANG.del}</a></td>
		{else}
			{if $check_alias_owner[$i]}
				<td><a href="edit-active.php?alias={$item.address|escape:"url"}&amp;domain={$item.domain|escape:"url"}&amp;return=search.php?search={$fSearch|escape:"url"}">{$item.active}</a></td>
				<td><a href="edit-alias.php?address={$item.address|escape:"url"}&amp;domain={$item.domain|escape:"url"}">{$PALANG.edit}</a></td>
				<td><a href="delete.php?table=alias&amp;delete={$item.address|escape:"url"}&amp;domain={$item.domain|escape:"url"}" onclick="return confirm ('{$PALANG.confirm}{$PALANG.pOverview_get_aliases}: {$item.address}');">{$PALANG.del}</a></td>
			{else}
				<td>&nbsp;</td>
				<td>&nbsp;</td>
			{/if}
		{/if}
		</tr>
	{/foreach}
</table>
{/if}
{if $tMailbox}
	<table id="mailbox_table">
		<tr>
			<td colspan="7"><h3>{$PALANG.pOverview_mailbox_title}</h3></td>
		</tr>
		<tr class="header">
			<td>{$PALANG.pOverview_mailbox_username}</td>
			<td>{$PALANG.pOverview_mailbox_name}</td>
			{if $CONF.quota===YES}<td>{$PALANG.pOverview_mailbox_quota}</td>{/if}
				<td>{$PALANG.pOverview_mailbox_modified}</td>
				<td>{$PALANG.pOverview_mailbox_active}</td>
				<td colspan="2">&nbsp;</td>
					{assign var="colspan" value=2}
					{if $CONF.vacation_control_admin===YES}{assign var="colspan" value=$colspan+1}{/if}
					{if $CONF.alias_control_admin===YES}{assign var="colspan" value=$colspan+1}{/if}
					{if $authentication_has_role.global_admin && $CONF.alias_control===YES}{assign var="colspan" value=3}{/if}
				<td colspan="{$colspan}">&nbsp;</td>
		</tr>
		{foreach from=$tMailbox item=item key=i}
			{#tr_hilightoff#}
				<td>{$item.display_username}</td>
				<td>{$item.name}</td>
				{if $CONF.quota===YES}
					<td>{$divide_quota.quota[$i]}</td>
				{/if}
				<td>{$item.modified}</td>
				<td><a href="edit-active.php?username={$item.username|escape:"url"}&amp;domain={$item.domain}&amp;return=search.php?search={$fSearch|escape:"url"}">{$item.active}</a></td>
				{if $CONF.vacation_control_admin===YES}
					<td><a href="edit-vacation.php?username={$item.username|escape:"url"}&amp;domain={$item.domain}">{$item.v_active}</a></td>
				{/if}
				{if $CONF.alias_control===YES || $CONF.alias_control_admin===YES}
					<td><a href="edit-alias.php?address={$item.username|escape:"url"}&amp;domain={$item.domain}">{$PALANG.pOverview_alias_edit}</a></td>
				{/if}
				<td><a href="edit-mailbox.php?username={$item.username|escape:"url"}&amp;domain={$item.domain}">{$PALANG.edit}</a></td>
				<td><a href="delete.php?table=mailbox&amp;delete={$item.username|escape:"url"}&amp;domain={$item.domain}" onclick="return confirm ('{$PALANG.confirm}{$PALANG.pOverview_get_mailboxes}: {$item.username}');">{$PALANG.del}</a></td>
			</tr>
		{/foreach}
	</table>
{/if}
