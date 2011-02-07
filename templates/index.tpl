<!-- {$smarty.template} -->
{strip}
{include file="header.tpl"}
{if $smarty_template|needle:"login" neq 1}
	{config_load file="menu.conf" section=$smarty_template}
	{if $smarty_template|needle:"users_" eq 1}
		{include file='users_menu.tpl'}
	{else}
		{include file='menu.tpl'}
	{/if}
{/if}
{if $smarty_template}
	{include file="$smarty_template.tpl"}
{else}
	<h3>Template not found</h3>({php}print $_SERVER ['PHP_SELF'];{/php})
{/if}
{/strip}
