<!doctype html>
<html lang="{if isset($smarty.session.lang)}{$smarty.session.lang}{/if}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>

    {* see https://github.com/postfixadmin/postfixadmin/issues/497 *}
    <meta http-equiv='Content-Security-Policy' content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; "/>

    <!-- Apply theme before page loads to prevent flashing -->
    <script>
        (function() {
            var savedTheme = localStorage.getItem('theme-preference');
            if (savedTheme) {
                document.documentElement.setAttribute('data-theme', savedTheme);
            } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>

    <title>Postfix Admin - {$smarty.server.HTTP_HOST}</title>
    <link rel="shortcut icon" href="{$CONF.theme_favicon}"/>
    <link rel="stylesheet" type="text/css" href="{$CONF.theme_css}"/>
    {if isset($CONF.postfixadmin_css)}
        <link rel="stylesheet" type="text/css" href="{$CONF.postfixadmin_css}"/>
    {/if}
    {if $CONF.theme_custom_css}
        <link rel="stylesheet" type="text/css" href="{$CONF.theme_custom_css}"/>
    {/if}
    {if isset($CONF.dark_theme_css)}
        <link rel="stylesheet" type="text/css" href="{$CONF.dark_theme_css}" id="dark-theme-css" disabled/>
    {/if}

    <style>
        /* Theme toggle button styles */
        .theme-toggle {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            margin: 8px 5px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .theme-toggle .glyphicon {
            margin-right: 8px;
            font-size: 16px;
        }
        .theme-toggle:hover {
            background-color: #444;
            color: #fff;
        }
        [data-theme="dark"] .theme-toggle {
            background-color: #333;
            color: #fff;
            border-color: #555;
        }
        [data-theme="dark"] .theme-toggle:hover {
            background-color: #444;
        }
        /* Make toggle more prominent on login page */
        #login .theme-toggle {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            font-size: 14px;
        }
    </style>

    <!-- needed for datetimepicker -->
    <script src="{$rel_path}jquery-3.7.0.min.js"></script>
    <script src="{$rel_path}css/bootstrap-3.4.1-dist/js/moment-with-locales.min.js"></script>
    <script src="{$rel_path}css/bootstrap-3.4.1-dist/js/bootstrap.min.js"></script>
    <script src="{$rel_path}css/bootstrap-3.4.1-dist/js/bootstrap-datetimepicker.min.js"></script>
    <script src="{$rel_path}js/theme-switcher.js"></script>
</head>
<body class="lang-{if isset($smarty.session.lang)}{$smarty.session.lang}{/if} page-{$smarty_template} {if isset($table)}page-{$smarty_template}-{$table}{/if}">
