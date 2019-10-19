<!-- {$smarty.template} -->
<!doctype html>
<html lang="{if isset($smarty.session.lang)}{$smarty.session.lang}{/if}">
	<head>
        <meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />

		<title>Postfix Admin - {$smarty.server.HTTP_HOST}</title>
		<link rel="shortcut icon" href="{$CONF.theme_favicon}" />
		<link rel="stylesheet" type="text/css" href="{$CONF.theme_css}" />
		{if $CONF.theme_custom_css}
		<link rel="stylesheet" type="text/css" href="{$CONF.theme_custom_css}" />
		{/if}
		<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
		<script src="css/bootstrap-3.3.7-dist/js/moment-with-locales.min.js"></script>
		<script src="css/bootstrap-3.3.7-dist/js/bootstrap.min.js"></script>
		<script src="css/bootstrap-3.3.7-dist/js/bootstrap-datetimepicker.min.js"></script>
	</head>
	<body class="lang-{if isset($smarty.session.lang)}{$smarty.session.lang}{/if} page-{$smarty_template} {if isset($table)}page-{$smarty_template}-{$table}{/if}">
		{***
		<div id="container" class="container">
		<div id="login_header">
		<a href="main.php"><img id="login_header_logo" src="{$CONF.theme_logo}" alt="Logo" /></a>
{if $CONF.show_header_text==='YES' && $CONF.header_text}
		<h2>{$CONF.header_text}</h2>
{/if}
		<h2>test</h2>
		</div>
		***}
