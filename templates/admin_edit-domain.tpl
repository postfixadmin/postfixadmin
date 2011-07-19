<div id="edit_form">
<form name="edit_domain" method="post" action="">
<table>
	<tr>
		<th colspan="3">{$PALANG.pAdminEdit_domain_welcome}</th>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pAdminEdit_domain_domain}:</label></td>
		<td><em>{$domain}</em></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pAdminEdit_domain_description}:</label></td>
		<td><input class="flat" type="text" name="fDescription" value="{$tDescription}" /></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pAdminEdit_domain_aliases}:</label></td>
		<td><input class="flat" type="text" name="fAliases" value="{$tAliases}" /></td>
		<td>{$PALANG.pAdminEdit_domain_aliases_text}</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pAdminEdit_domain_mailboxes}:</label></td>
		<td><input class="flat" type="text" name="fMailboxes" value="{$tMailboxes}" /></td>
		<td>{$PALANG.pAdminEdit_domain_mailboxes_text}</td>
	</tr>
{if $CONF.domain_quota===YES}
	<tr>
		<td class="label"><label>{$PALANG.pAdminEdit_domain_quota}:</label></td>
		<td><input class="flat" type="text" name="fDomainquota" value="{$tDomainquota}" /></td>
		<td>{$PALANG.pAdminEdit_domain_maxquota_text}</td>
	</tr>
{/if}
{if $CONF.quota===YES}
	<tr>
		<td class="label"><label>{$PALANG.pAdminEdit_domain_maxquota}:</label></td>
		<td><input class="flat" type="text" name="fMaxquota" value="{$tMaxquota}" /></td>
		<td>{$PALANG.pAdminEdit_domain_maxquota_text}</td>
	</tr>
{/if}
{if $CONF.transport===YES}
	<tr>
		<td class="label"><label>{$PALANG.pAdminEdit_domain_transport}:</label></td>
		<td><select class="flat" name="fTransport">{$select_options}</select></td>
		<td>{$PALANG.pAdminEdit_domain_transport_text}</td>
	</tr>
{/if}
	<tr>
		<td class="label"><label>{$PALANG.pAdminEdit_domain_backupmx}:</label></td>
		<td><input class="flat" type="checkbox" name="fBackupmx"{$tBackupmx}/></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td class="label"><label>{$PALANG.pAdminEdit_domain_active}:</label></td>
		<td><input class="flat" type="checkbox" name="fActive"{$tActive}/></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td>&nbsp;</td>
		<td colspan="2"><input type="submit" class="button" name="submit" value="{$PALANG.save}" /></td>
	</tr>
	<tr>
		<td colspan="3" class="standout">{$tMessage}</td>
	</tr>
</table>
</form>
</div>
