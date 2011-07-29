<!-- {$smarty.template} -->
{strip}
{include file="header.tpl"}
{if $smarty_template != 'login'}
	{config_load file="menu.conf" section=$smarty_template}
	{if $authentication_has_role.user}
		{include file='users_menu.tpl'}
	{else}
		{include file='menu.tpl'}
	{/if}
{/if}
{include file='flash_error.tpl'}
{if $smarty_template}
	{include file="$smarty_template.tpl"}
{else}
	<h3>Template not found</h3>({php}print $_SERVER ['PHP_SELF'];{/php})
{/if}
{include file='footer.tpl'}
{/strip}
