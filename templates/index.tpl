{strip}
<!doctype html>
<html lang="{if isset($smarty.session.lang)}{$smarty.session.lang}{/if}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>

    {* see https://github.com/postfixadmin/postfixadmin/issues/497 *}
    <meta http-equiv='Content-Security-Policy'
          content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; "/>

    <title>Postfix Admin - {$smarty.server.HTTP_HOST}</title>
    <link rel="shortcut icon" href="{$CONF.theme_favicon}"/>
    <link rel="stylesheet" type="text/css" href="{$CONF.theme_css}"/>
    {if $CONF.theme_custom_css}
        <link rel="stylesheet" type="text/css" href="{$CONF.theme_custom_css}"/>
    {/if}
    <script src="{$rel_path}css/bootstrap-5.3.0-dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="{if isset($smarty.session.lang)}lang-{$smarty.session.lang}{/if} page-{$smarty_template} {if isset($table)}page-{$smarty_template}-{$table}{/if}">

{* Hide menu for some templates *}
{if !in_array($smarty_template, ["login", "login-mfa", "password-recover", "password-change"])}
{config_load file="menu.conf" section=$smarty_template}
    {if $authentication_has_role.user}
        {include file='users_menu.tpl'}
    {else}
        {include file='menu.tpl'}
    {/if}
{/if}

<div class="container-xl " style="min-width: 80%; " role="main">
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
<!-- {$smarty.template} -->
<footer class="footer mt-auto py-3 ">
    <div class="container text-center small">

        {if !isset($smarty.session.sessid.username)}

            {* see: https://github.com/postfixadmin/postfixadmin/issues/517 - only expose version number if logged in *}
            <a target="_blank" rel="noopener"
               href="https://github.com/postfixadmin/postfixadmin/">PostfixAdmin</a>
        {else}
            <a target="_blank" rel="noopener" href="https://github.com/postfixadmin/postfixadmin/">Postfix
                Admin {$version}</a>
            <span id="update-check">&nbsp;|&nbsp;
                <a target="_blank" rel="noopener"
                   href="https://github.com/postfixadmin/postfixadmin/releases">{$PALANG.check_update}</a>
            </span>
            {if isset($smarty.session.sessid)}
                {if $smarty.session.sessid.username}
                    &nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;
                    {$PALANG.pFooter_logged_as|replace:"%s":$smarty.session.sessid.username}
                {/if}
            {/if}
        {/if}
        {if $CONF.show_footer_text == 'YES' && $CONF.footer_link}
            &nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;
            <a href="{$CONF.footer_link}" rel="noopener">{$CONF.footer_text}</a>
        {/if}

        <div class="float-end">
            <span class=" form-check form-check-inline form-switch">
                <input class="form-check-input" type="checkbox" id="darkModeSwitch" checked>
                <label class="form-check-label" for="darkModeSwitch">Dark Mode</label>
            </span>
        </div>
    </div>
</footer>

<!-- bootstrap light/dark mode switch, taken from https://github.com/404GamerNotFound/bootstrap-5.3-dark-mode-light-mode-switch (MIT license) -->

{literal}
    <script>
        document.addEventListener('DOMContentLoaded', (event) => {
            const htmlElement = document.documentElement;
            const switchElement = document.getElementById('darkModeSwitch');

            // Set the default theme to dark if no setting is found in local storage
            const currentTheme = localStorage.getItem('bsTheme') || 'dark';
            htmlElement.setAttribute('data-bs-theme', currentTheme);
            switchElement.checked = currentTheme === 'dark';

            switchElement.addEventListener('change', function () {
                if (this.checked) {
                    htmlElement.setAttribute('data-bs-theme', 'dark');
                    localStorage.setItem('bsTheme', 'dark');
                } else {
                    htmlElement.setAttribute('data-bs-theme', 'light');
                    localStorage.setItem('bsTheme', 'light');
                }
            });
        });
    </script>
{/literal}

{/strip}

