<div id="edit_form">
<form name="create_domain" method="post" action="">
<table>
	<tr>
		<td colspan="3"><h3>{$PALANG.pAdminCreate_domain_welcome}</h3></td>
	</tr>
	<tr>
		<td>{$PALANG.pAdminCreate_domain_domain}:</td>
		<td><input class="flat" type="text" name="fDomain" value="{$tDomain}" /></td>
		<td>{$pAdminCreate_domain_domain_text}</td>
	</tr>
	<tr>
		<td>{$PALANG.pAdminCreate_domain_description}:</td>
		<td><input class="flat" type="text" name="fDescription" value="{$tDescription}" /></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
	<td>{$PALANG.pAdminCreate_domain_aliases}:</td>
		<td><input class="flat" type="text" name="fAliases" value="{$tAliases}" /></td>
		<td>{$PALANG.pAdminCreate_domain_aliases_text}</td>
	</tr>
	<tr>
		<td>{$PALANG.pAdminCreate_domain_mailboxes}:</td>
		<td><input class="flat" type="text" name="fMailboxes" value="{$tMailboxes}" /></td>
		<td>{$PALANG.pAdminCreate_domain_mailboxes_text}</td>
	</tr>
{if $CONF.domain_quota===YES}
	<tr>
		<td>{$PALANG.pAdminEdit_domain_quota}:</td>
		<td><input class="flat" type="text" name="fDomainquota" value="{$tDomainquota}" /></td>
		<td>{$PALANG.pAdminCreate_domain_maxquota_text}</td>
	</tr>
{/if}
{if $CONF.quota===YES}
	<tr>
		<td>{$PALANG.pAdminCreate_domain_maxquota}:</td>
		<td><input class="flat" type="text" name="fMaxquota" value="{$tMaxquota}" /></td>
		<td>{$PALANG.pAdminCreate_domain_maxquota_text}</td>
	</tr>
{/if}
{if $CONF.transport===YES}
	<tr>
		<td>{$PALANG.pAdminCreate_domain_transport}:</td>
		<td><select class="flat" name="fTransport">{$select_options}</select></td>
		<td>{$PALANG.pAdminCreate_domain_transport_text}</td>
	</tr>
{/if}
	<tr>
		<td>{$PALANG.pAdminCreate_domain_defaultaliases}:</td>
		<td><input class="flat" type="checkbox" value='on' name="fDefaultaliases"{$tDefaultaliases}/></td>
		<td>{$PALANG.pAdminCreate_domain_defaultaliases_text}</td>
	</tr>
	<tr>
		<td>{$PALANG.pAdminCreate_domain_backupmx}:</td>
		<td><input class="flat" type="checkbox" value='on' name="fBackupmx"{$tBackupmx}/></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td colspan="3" class="hlp_center"><input class="button" type="submit" name="submit" value="{$PALANG.pAdminCreate_domain_button}" /></td>
	</tr>
	<tr>
		<td colspan="3" class="standout">{$tMessage}</td>
	</tr>
</table>
</form>
</div>
