<!-- {$smarty.template} -->
{php}
	@header ("Expires: Sun, 16 Mar 2003 05:00:00 GMT");
	@header ("Last-Modified: " . gmdate ("D, d M Y H:i:s") . " GMT");
	@header ("Cache-Control: no-store, no-cache, must-revalidate");
	@header ("Cache-Control: post-check=0, pre-check=0", false);
	@header ("Pragma: no-cache");
	@header ("Content-Type: text/html; charset=UTF-8");
{/php}
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<link rel="stylesheet" type="text/css" href="{$CONF.theme_css}"/>
		<title>Postfix Admin - {$smarty.server.HTTP_HOST}</title>
	</head>
	<body>
		<div id="login_header">
		<img id="login_header_logo" src="{$CONF.theme_logo}" alt="Logo"/>
{if $CONF.show_header_text==='YES' && $CONF.header_text}
		<h2>{$CONF.header_text}</h2>
{/if}
		</div>

{strip}
		{if $smarty.session.flash}
			{if $smarty.session.flash.info}
				<ul class="flash-info">
					{foreach from=$smarty.session.flash.info item=msg}
						<li>{$msg}</li>
					{/foreach}
				</ul>
			{/if}
			{if $smarty.session.flash.error}
				<ul class="flash-error">
					{foreach from=$smarty.session.flash.error item=msg}
						<li>{$msg}</li>
					{/foreach}
				</ul>
			{/if}
		{php}$_SESSION['flash'] = array();{/php}
		{/if}
{/strip}
