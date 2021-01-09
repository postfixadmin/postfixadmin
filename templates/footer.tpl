<!-- {$smarty.template} -->
<footer class="footer">
    <div class="container text-center">
        <a target="_blank" rel="noopener" href="https://github.com/postfixadmin/postfixadmin/">Postfix Admin {$version}</a>
        <span id="update-check">&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;
	<a target="_blank" rel="noopener"
       href="http://postfixadmin.sf.net/update-check.php?version={$version|escape:"url"}">{$PALANG.check_update}</a>
        </span>
        {if isset($smarty.session.sessid)}
            {if $smarty.session.sessid.username}
                &nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;
                {$PALANG.pFooter_logged_as|replace:"%s":$smarty.session.sessid.username}
            {/if}
        {/if}
        {if $CONF.show_footer_text == 'YES' && $CONF.footer_link}
            &nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;
            <a href="{$CONF.footer_link}" rel="noopener">{$CONF.footer_text}</a>
        {/if}
    </div>
</footer>
</body>
</html>
