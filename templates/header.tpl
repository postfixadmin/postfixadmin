<!-- {$smarty.template} -->
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<link rel="stylesheet" type="text/css" href="{$CONF.theme_css}" />
{if $CONF.theme_custom_css}
		<link rel="stylesheet" type="text/css" href="{$CONF.theme_custom_css}" />
{/if}
		<title>Postfix Admin - {$smarty.server.HTTP_HOST}</title>
	</head>
	<body class="lang-{$smarty.session.lang} page-{$smarty_template} {if isset($table)}page-{$smarty_template}-{$table}{/if}">
		<div id="container">
		<div id="login_header">
		<a href='main.php'><img id="login_header_logo" src="{$CONF.theme_logo}" alt="Logo" /></a>
{if $CONF.show_header_text==='YES' && $CONF.header_text}
		<h2>{$CONF.header_text}</h2>
{/if}
		</div>
