<!-- {$smarty.template} -->
<div id="main_menu">
<table>
	<tr>
		<td nowrap="nowrap"><a target="_top" href="{#url_list_domain#}">{$PALANG.pMenu_overview}</a></td>
		<td>{$PALANG.pMain_overview}</td>
	</tr>
	<tr>
		<td nowrap="nowrap"><a target="_top" href="{#url_create_alias#}">{$PALANG.add_alias}</a></td>
		<td>{$PALANG.pMain_create_alias}</td>
	</tr>
	<tr>
		<td nowrap="nowrap"><a target="_top" href="{#url_create_mailbox#}">{$PALANG.add_mailbox}</a></td>
		<td>{$PALANG.pMain_create_mailbox}</td>
	</tr>
{if $CONF.sendmail==='YES'}
	<tr>
		<td nowrap="nowrap"><a target="_top" href="{#url_sendmail#}">{$PALANG.pMenu_sendmail}</a></td>
		<td>{$PALANG.pMain_sendmail}</td>
	</tr>
{/if}
	<tr>
		<td nowrap="nowrap"><a target="_top" href="{#url_password#}">{$PALANG.pMenu_password}</a></td>
		<td>{$PALANG.pMain_password}</td>
	</tr>
	<tr>
		<td nowrap="nowrap"><a target="_top" href="{#url_viewlog#}">{$PALANG.pMenu_viewlog}</a></td>
		<td>{$PALANG.pMain_viewlog}</td>
	</tr>
	<tr>
		<td nowrap="nowrap"><a target="_top" href="{#url_logout#}">{$PALANG.pMenu_logout}</a></td>
		<td>{$PALANG.pMain_logout}</td>
	</tr>
</table>
</div>
