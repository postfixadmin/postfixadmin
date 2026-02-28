<!-- {$smarty.template} -->
<footer class="footer">
    <div class="container text-center">
        {if !isset($smarty.session.sessid.username)}
            {* see: https://github.com/postfixadmin/postfixadmin/issues/517 - only expose version number if logged in *}
            <a target="_blank" rel="noopener" href="https://github.com/postfixadmin/postfixadmin/">PostfixAdmin</a>
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

