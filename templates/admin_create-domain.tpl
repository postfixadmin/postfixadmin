<div id="edit_form">
<form name="create_domain" method="post" action="">
<table>
	<tr>
		<th colspan="4">{$PALANG.pAdminCreate_domain_welcome}</th>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pAdminCreate_domain_domain}:</label></td>
		<td><input class="flat" type="text" name="fDomain" value="{$tDomain}" /></td>
		<td>&nbsp;</td>
		<td><span class="error_msg">{$pAdminCreate_domain_domain_text_error}</span></td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pAdminCreate_domain_description}:</label></td>
		<td><input class="flat" type="text" name="fDescription" value="{$tDescription}" /></td>
		<td colspan="2">&nbsp;</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pAdminCreate_domain_aliases}:</label></td>
		<td><input class="flat" type="text" name="fAliases" value="{$tAliases}" /></td>
		<td>{$PALANG.pAdminCreate_domain_aliases_text}</td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pAdminCreate_domain_mailboxes}:</label></td>
		<td><input class="flat" type="text" name="fMailboxes" value="{$tMailboxes}" /></td>
		<td>{$PALANG.pAdminCreate_domain_mailboxes_text}</td>
		<td>&nbsp;</td>
	</tr>
{if $CONF.domain_quota===YES}
	<tr>
		<td class="label"><label>{$PALANG.pAdminEdit_domain_quota}:</label></td>
		<td><input class="flat" type="text" name="fDomainquota" value="{$tDomainquota}" /></td>
		<td>{$PALANG.pAdminCreate_domain_maxquota_text}</td>
		<td>&nbsp;</td>
	</tr>
{/if}
{if $CONF.quota===YES}
	<tr>
		<td class="label"><label>{$PALANG.pAdminCreate_domain_maxquota}:</label></td>
		<td><input class="flat" type="text" name="fMaxquota" value="{$tMaxquota}" /></td>
		<td>{$PALANG.pAdminCreate_domain_maxquota_text}</td>
		<td>&nbsp;</td>
	</tr>
{/if}
{if $CONF.transport===YES}
	<tr>
		<td class="label"><label>{$PALANG.pAdminCreate_domain_transport}:</label></td>
		<td><select class="flat" name="fTransport">{$select_options}</select></td>
		<td>{$PALANG.pAdminCreate_domain_transport_text}</td>
		<td>&nbsp;</td>
	</tr>
{/if}
	<tr>
		<td class="label"><label>{$PALANG.pAdminCreate_domain_defaultaliases}:</label></td>
		<td><input class="flat" type="checkbox" value='on' name="fDefaultaliases"{$tDefaultaliases}/></td>
		<td>{$PALANG.pAdminCreate_domain_defaultaliases_text}</td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pAdminCreate_domain_backupmx}:</label></td>
		<td><input class="flat" type="checkbox" value='on' name="fBackupmx"{$tBackupmx}/></td>
		<td>&nbsp;</td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td>&nbsp;</td>
		<td><input class="button" type="submit" name="submit" value="{$PALANG.pAdminCreate_domain_button}" /></td>
		<td colspan="2">&nbsp;</td>
	</tr>
</table>
</form>
</div>
