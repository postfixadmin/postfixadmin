<!-- {$smarty.template} -->
<div id="footer">
	<a target="_blank" href="http://postfixadmin.com/">Postfix Admin {$version}</a>
	&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;
	{if $smarty.session.sessid.username}
		{$PALANG.pFooter_logged_as|replace:"%s":$smarty.session.sessid.username}
		{$PALANG_pFooter_logged_as}
	{/if}
	&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;
	<a target="_blank" href="http://postfixadmin.sf.net/update-check.php?version={$version|escape:"url"}">{$PALANG.check_update}</a>
	{if $CONF.show_footer_text == 'YES' && $CONF.footer_link}
		&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;
		<a href="{$CONF.footer_link|escape:"url"}">{$CONF.footer_text|escape:"url"}</a>
	{/if}
</div>
{*
<!-- hundertmark smarty debug -->
<div style="background-color:#ffa; border:1px solid #f00;">
<a href="main.php">main</a>
<pre>
	{assign var="url_domain" value=$smarty.get.domain}
	{assign var="url_domain" value=?domain&#61;$url_domain}
{$url_domain}
{$PALANG.pFooter_logged_as}
{$smarty.session.sessid.username}
{$smarty.get.domain}

$smarty->assign ('PALANG_pFooter_logged_as', sprintf($PALANG['pFooter_logged_as'], authentication_get_username()));
{php}
print_r ($_SESSION);
//phpinfo ();
{/php}
</pre>
</div>
<!-- hundertmark smarty debug -->
*}
</body>
</html>