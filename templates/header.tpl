<!-- {$smarty.template} -->
<!doctype html>
<html lang="{if isset($smarty.session.lang)}{$smarty.session.lang}{/if}">
	<head>
        <meta charset="utf-8">
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<title>Postfix Admin - {$smarty.server.HTTP_HOST}</title>
		<link rel="shortcut icon" href="images/favicon.ico">
		<link rel="stylesheet" type="text/css" href="{$CONF.theme_css}" />
{if $CONF.theme_custom_css}
		<link rel="stylesheet" type="text/css" href="{$CONF.theme_custom_css}" />
{/if}
	</head>
	<body class="lang-{if isset($smarty.session.lang)}{$smarty.session.lang}{/if} page-{$smarty_template} {if isset($table)}page-{$smarty_template}-{$table}{/if}">
		<div id="container">
		<div id="login_header">
		<a href='main.php'><img id="login_header_logo" src="{$CONF.theme_logo}" alt="Logo" /></a>
{if $CONF.show_header_text==='YES' && $CONF.header_text}
		<h2>{$CONF.header_text}</h2>
{/if}
		</div>
