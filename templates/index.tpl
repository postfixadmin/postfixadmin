<!-- {$smarty.template} -->
{strip}
{include file="header.tpl"}
{if not $smarty_template|needle:"login"}
	{config_load file="menu.conf" section=$smarty_template}
	{if $smarty_template|needle:"users_"}
		{include file='users_menu.tpl'}
	{else}
		{include file='menu.tpl'}
	{/if}
{/if}
{if $smarty_template}
	{include file="$smarty_template.tpl"}
{else}
	<h3>Kein Template angegeben</h3>({php}print $_SERVER ['PHP_SELF'];{/php})
{/if}
{include file='footer.tpl'}
{/strip}