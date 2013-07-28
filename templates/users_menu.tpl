<div id="menu">
<ul>
	<li><a target="_top" href="{#url_user_main#}">{$PALANG.pMenu_main}</a></li>
{if $CONF.vacation===YES}
	<li><a target="_top" href="{#url_user_vacation#}">{$PALANG.pUsersMenu_vacation}</a></li>
{/if}
	<li><a target="_top" href="{#url_user_edit_alias#}">{$PALANG.pUsersMenu_edit_alias}</a></li>
	<li><a target="_top" href="{#url_user_password#}">{$PALANG.change_password}</a></li>
	<li class="logout"><a target="_top" href="{#url_user_logout#}">{$PALANG.pMenu_logout}</a></li>
</ul>
</div>
