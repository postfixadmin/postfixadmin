<div id="edit_form">
<form name="edit_domain" method="post" action="">
<table>
	<tr>
		<td colspan="3"><h3>{$PALANG.pAdminEdit_domain_welcome}</h3></td>
	</tr>
	<tr>
		<td>{$PALANG.pAdminEdit_domain_domain}:</td>
		<td>{$domain}</td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td>{$PALANG.pAdminEdit_domain_description}:</td>
		<td><input class="flat" type="text" name="fDescription" value="{$tDescription}" /></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td>{$PALANG.pAdminEdit_domain_aliases}:</td>
		<td><input class="flat" type="text" name="fAliases" value="{$tAliases}" /></td>
		<td>{$PALANG.pAdminEdit_domain_aliases_text}</td>
	</tr>
	<tr>
		<td>{$PALANG.pAdminEdit_domain_mailboxes}:</td>
		<td><input class="flat" type="text" name="fMailboxes" value="{$tMailboxes}" /></td>
		<td>{$PALANG.pAdminEdit_domain_mailboxes_text}</td>
	</tr>
{if $CONF.quota===YES}
	<tr>
		<td>{$PALANG.pAdminEdit_domain_maxquota}:</td>
		<td><input class="flat" type="text" name="fMaxquota" value="{$tMaxquota}" /></td>
		<td>{$PALANG.pAdminEdit_domain_maxquota_text}</td>
	</tr>
{/if}
{if $CONF.transport===YES}
	<tr>
		<td>{$PALANG.pAdminEdit_domain_transport}:</td>
		<td><select class="flat" name="fTransport">{$select_options}</select></td>
		<td>{$PALANG.pAdminEdit_domain_transport_text}</td>
	</tr>
{/if}
	<tr>
		<td>{$PALANG.pAdminEdit_domain_backupmx}:</td>
		<td><input class="flat" type="checkbox" name="fBackupmx"{$tBackupmx}/></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td>{$PALANG.pAdminEdit_domain_active}:</td>
		<td><input class="flat" type="checkbox" name="fActive"{$tActive}/></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td colspan="3" class="hlp_center"><input type="submit" class="button" name="submit" value="{$PALANG.save}" /></td>
	</tr>
	<tr>
		<td colspan="3" class="standout">{$tMessage}</td>
	</tr>
</table>
</form>
</div>
