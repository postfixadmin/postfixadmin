{if $edit || $new}
	<div id="edit_form">
	<form name="fetchmail" method="post" action="">
	{$fetchmail_edit_row}
{else}
	{assign var="colspan" value=$headers|@count}
	<div id="overview">
		<form name="frmOverview" method="post" action="">
		<table id="log_table" border="0">
			<tr>
				<th colspan="{$colspan+2}">{$PALANG.pFetchmail_welcome}{$user_domains}</th>
			</tr>
			{#tr_header#}
			{foreach from=$headers item=header}
				<td>{$header}</td>
			{/foreach}
			<td colspan="2">&nbsp;</td>
			</tr>
		{if $tFmail}
			{foreach from=$tFmail item=row}
				{#tr_hilightoff#}
					<td nowrap="nowrap">{$row.mailbox}&nbsp;</td>
					<td nowrap="nowrap">{$row.src_server}&nbsp;</td>
					<td nowrap="nowrap">{$row.src_auth}&nbsp;</td>
					<td nowrap="nowrap">{$row.src_user}&nbsp;</td>
					<td nowrap="nowrap">{$row.src_folder}&nbsp;</td>
					<td nowrap="nowrap">{$row.poll_time}&nbsp;</td>
					<td nowrap="nowrap">{$row.fetchall}&nbsp;</td>
					<td nowrap="nowrap">{$row.keep}&nbsp;</td>
					<td nowrap="nowrap">{$row.protocol}&nbsp;</td>
					<td nowrap="nowrap">{$row.usessl}&nbsp;</td>
					<td nowrap="nowrap">{$row.sslcertck}&nbsp;</td>
{if $extra_options}
					<td nowrap="nowrap">{$row.sslcertpath}&nbsp;</td>
					<td nowrap="nowrap">{$row.sslfingerprint}&nbsp;</td>
					<td nowrap="nowrap">{$row.extra_options}&nbsp;</td>
					<td nowrap="nowrap">{$row.mda}&nbsp;</td>
{/if}
					<td nowrap="nowrap">{$row.date}&nbsp;</td>
					<td nowrap="nowrap">{$row.returned_text}--x--&nbsp;</td> <!-- Inhalt mit if auswerten!  -->
					<td><a href="fetchmail.php?edit={$row.id|escape:"url"}">{$PALANG.edit}</a></td>
					<td><a href="fetchmail.php?delete={$row.id|escape:"url"}&amp;token={$smarty.session.PFA_token|escape:"url"}"
						onclick="return confirm('{$PALANG.confirm}{$PALANG.pMenu_fetchmail}:{$row.src_user}@{$row.src_server}')">{$PALANG.del}</a></td>
				</tr>
			{/foreach}
		{/if}
		</table>
</form>
</div>
<br /><a href='?new=1' class="button">{$PALANG.pFetchmail_new_entry}</a><br />
{/if}
