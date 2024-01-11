<!doctype html>
<html lang="{if isset($smarty.session.lang)}{$smarty.session.lang}{/if}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>

    {* see https://github.com/postfixadmin/postfixadmin/issues/497 *}
    <meta http-equiv='Content-Security-Policy' content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; "/>

    <title>Postfix Admin - {$smarty.server.HTTP_HOST}</title>
    <link rel="shortcut icon" href="{$CONF.theme_favicon}"/>
    <link rel="stylesheet" type="text/css" href="{$CONF.theme_css}"/>
    {if $CONF.theme_custom_css}
        <link rel="stylesheet" type="text/css" href="{$CONF.theme_custom_css}"/>
    {/if}

    <!-- needed for datetimepicker -->
    <script src="{$rel_path}jquery-3.7.0.min.js"></script>
    <script src="{$rel_path}css/bootstrap-3.4.1-dist/js/moment-with-locales.min.js"></script>
    <script src="{$rel_path}css/bootstrap-3.4.1-dist/js/bootstrap.min.js"></script>
    <script src="{$rel_path}css/bootstrap-3.4.1-dist/js/bootstrap-datetimepicker.min.js"></script>
</head>
<body class="lang-{if isset($smarty.session.lang)}{$smarty.session.lang}{/if} page-{$smarty_template} {if isset($table)}page-{$smarty_template}-{$table}{/if}">
