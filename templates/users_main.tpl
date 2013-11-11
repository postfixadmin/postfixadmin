<div id="main_menu">
<table>
{if $CONF.vacation===YES}
	<tr>
		<td nowrap="nowrap"><a target="_top" href="vacation.php">{$PALANG.pUsersMenu_vacation}</a></td>
		<td>{$tummVacationtext}</td>
	</tr>
{/if}
	<tr>
		<td nowrap="nowrap"><a target="_top" href="edit-alias.php">{$PALANG.pUsersMenu_edit_alias}</a></td>
		<td>{$PALANG.pUsersMain_edit_alias}</td>
	</tr>
	<tr>
		<td nowrap="nowrap"><a target="_top" href="password.php">{$PALANG.change_password}</a></td>
		<td>{$PALANG.pUsersMain_password}</td>
	</tr>
	<tr>
		<td nowrap="nowrap"><a target="_top" href="{#url_user_logout#}">{$PALANG.pMenu_logout}</a></td>
		<td>{$PALANG.pMain_logout}</td>
	</tr>
</table>
</div>
