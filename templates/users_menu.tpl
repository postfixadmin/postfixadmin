<div id="menu">
<ul>
	<li><a target="_top" href="{$CONF.user_footer_link}">{$PALANG.pMenu_main}</a></li>
{if $CONF.vacation===YES}
	<li><a target="_top" href="{#url_user_vacation#}">{$PALANG.pUsersMenu_vacation}</a></li>
{/if}
	<li><a target="_top" href="{#url_user_edit_alias#}">{$PALANG.pUsersMenu_edit_alias}</a></li>
	<li><a target="_top" href="{#url_user_password#}">{$PALANG.pUsersMenu_password}</a></li>
	<li><a target="_top" href="{#url_user_logout#}">{$PALANG.pMenu_logout}</a></li>
</ul>
</div>
<br clear="all"/><br/>
{php}
if (file_exists (realpath ("../motd-users.txt"))) 
{
   print "<div id=\"motd\">\n";
   include ("../motd-users.txt");
   print "</div>";
}
{/php}
