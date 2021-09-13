{strip}
    {include file="header.tpl"}
    {if $smarty_template !== 'login' && $smarty_template !== 'password-recover' && $smarty_template !== 'password-change'}
    {config_load file="menu.conf" section=$smarty_template}
        {if $authentication_has_role.user}
            {include file='users_menu.tpl'}
        {else}
            {include file='menu.tpl'}
        {/if}
    {/if}
    <div class="container" style="min-width: 600px; width: min-content" role="main">
        {if $authentication_has_role.user && $CONF.motd_user}
            <div id="motd">{$CONF.motd_user}</div>
        {elseif $authentication_has_role.global_admin && $CONF.motd_superadmin}
            <div id="motd">{$CONF.motd_superadmin}</div>
        {elseif $authentication_has_role.admin && $CONF.motd_admin}
            <div id="motd">{$CONF.motd_admin}</div>
        {/if}

        {include file='flash_error.tpl'}
        {if $smarty_template}
            {include file="$smarty_template.tpl"}
        {else}
            <h3>Template not found</h3>
            ({$smarty.server.PHP_SELF|escape:"html"})
        {/if}
    </div>
    {include file='footer.tpl'}
{/strip}
