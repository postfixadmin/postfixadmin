<!-- {$smarty.template} -->
{strip}
    {if !empty($smarty.get) && !empty($smarty.get.domain)}
        {*** zuweisung muss eleganter gehen ***}
        {assign var="url_domain" value=$smarty.get.domain}
        {assign var="url_domain" value="&amp;domain={$url_domain|escape:url}"}
    {/if}
{/strip}

{strip}


    <nav class="navbar navbar-default fixed-top">
        <div class="container-fluid">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar"
                        aria-expanded="false" aria-controls="navbar">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                {*** <a class="navbar-brand" href='main.php'><img id="login_header_logo" src="{$CONF.theme_logo}" alt="Logo" /></a> ***}
                <a class="navbar-brand" href='main.php'><img id="login_header_logo" src="{$CONF.theme_logo|default:'images/postbox.png'}" alt="Logo"/></a>
            </div>
            <div id="navbar" class="collapse navbar-collapse">
                <ul class="nav navbar-nav">
                    {* list-admin *}
                    {if $authentication_has_role.global_admin}
                        {strip}
                            <li class="dropdown">
                                <a class="btn navbar-btn dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true"
                                   aria-expanded="false" href="{#url_list_admin#}"><span class="glyphicon glyphicon-list" aria-hidden="true"></span> {$PALANG.pAdminMenu_list_admin} <span
                                            class="caret"></span></a>
                                <ul class="dropdown-menu">
                                    <li><a href="{#url_list_admin#}"><span class="glyphicon glyphicon-list" aria-hidden="true"></span> {$PALANG.pAdminMenu_list_admin}</a></li>
                                    <li><a href="{#url_create_admin#}"><span class="glyphicon glyphicon-plus" aria-hidden="true"></span> {$PALANG.pAdminMenu_create_admin}</a></li>
                                </ul>
                            </li>
                        {/strip}
                    {else}
                        <li><a class="btn navbar-btn" href="{#url_main#}"><span class="glyphicon glyphicon-home" aria-hidden="true"></span> {$PALANG.pMenu_main}</a></li>
                    {/if}
                    {* list-domain *}
                    {strip}
                        <li>
                            <a class="btn navbar-btn dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true"
                               aria-expanded="false" href="{#url_list_domain#}"><span class="glyphicon glyphicon-th-list" aria-hidden="true"></span> {$PALANG.pAdminMenu_list_domain} <span
                                        class="caret"></span></a>
                            <ul class="dropdown-menu">
                                <li><a href="{#url_list_domain#}"><span class="glyphicon glyphicon-th-list" aria-hidden="true"></span> {$PALANG.pAdminMenu_list_domain}</a></li>
                                {if $authentication_has_role.global_admin}
                                    <li><a href="{#url_edit_domain#}"><span class="glyphicon glyphicon-plus" aria-hidden="true"></span> {$PALANG.pAdminMenu_create_domain}</a></li>
                                {/if}
                            </ul>
                        </li>
                    {/strip}
                    {* list-virtual *}
                    {strip}
                        <li class="dropdown">
                            <a class="btn navbar-btn dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true"
                               aria-expanded="false" href="{#url_list_virtual#}"><span class="glyphicon glyphicon-list-alt" aria-hidden="true"></span> {$PALANG.pAdminMenu_list_virtual} <span
                                        class="caret"></span></a>
                            <ul class="dropdown-menu">
                                <li><a href="{#url_list_virtual#}"><span class="glyphicon glyphicon-list-alt" aria-hidden="true"></span> {$PALANG.pAdminMenu_list_virtual}</a></li>
                                <li><a href="{#url_create_mailbox#}{$url_domain}"><span class="glyphicon glyphicon-inbox" aria-hidden="true"></span> {$PALANG.add_mailbox}</a></li>
                                <li><a href="{#url_create_alias#}{$url_domain}"><span class="glyphicon glyphicon-plus-sign" aria-hidden="true"></span> {$PALANG.add_alias}</a></li>
                                {if $boolconf_alias_domain}
                                    <li>
                                        <a href="{#url_create_alias_domain#}{$url_domain}">{$PALANG.add_alias_domain}</a>
                                    </li>
                                {/if}
                            </ul>
                        </li>
                    {/strip}
                    {* fetchmail *}
                    {if $CONF.fetchmail==='YES'}
                        {strip}
                            <li class="dropdown">
                                <a class="btn navbar-btn dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true"
                                   aria-expanded="false" href="{#url_fetchmail#}">{$PALANG.pMenu_fetchmail} <span
                                            class="caret"></span></a>
                                <ul class="dropdown-menu">
                                    <li><a href="{#url_fetchmail#}">{$PALANG.pMenu_fetchmail}</a></li>
                                    <li><a href="{#url_fetchmail_new_entry#}">{$PALANG.pFetchmail_new_entry}</a></li>
                                </ul>
                            </li>
                        {/strip}
                    {/if}
                    {* sendmail *}
                    {if $CONF.sendmail==='YES'}
                        {strip}
                            <li class="dropdown">
                                <a class="btn navbar-btn dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true"
                                   aria-expanded="false" href="{#url_sendmail#}"><span class="glyphicon glyphicon-send" aria-hidden="true"></span> {$PALANG.pMenu_sendmail} <span
                                            class="caret"></span></a>
                                <ul class="dropdown-menu">
                                    <li><a href="{#url_sendmail#}"><span class="glyphicon glyphicon-send" aria-hidden="true"></span> {$PALANG.pMenu_sendmail}</a></li>
                                    {if $authentication_has_role.global_admin || (isset($CONF.sendmail_all_admins) && $CONF.sendmail_all_admins === 'YES') }
                                        <li>
                                            <a href="{#url_broadcast_message#}"><span class="glyphicon glyphicon-share" aria-hidden="true"></span> {$PALANG.pAdminMenu_broadcast_message}</a>
                                        </li>
                                    {/if}
                                </ul>
                            </li>
                        {/strip}
                    {/if}
                    {* dkim *}
                    {if $CONF.dkim==='YES' && (
                        $authentication_has_role.global_admin ||
                        (isset($CONF.dkim_all_admins) && $CONF.dkim_all_admins === 'YES') )
                    }
                        {strip}
                            <li class="dropdown">
                                <a class="btn navbar-btn dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true"
                                   aria-expanded="false" href="{#url_dkim#}"><span class="glyphicon glyphicon-certificate" aria-hidden="true"></span> {$PALANG.pMenu_dkim} <span
                                            class="caret"></span></a>
                                <ul class="dropdown-menu">
                                    <li><a href="{#url_dkim#}"><span class="glyphicon glyphicon-certificate" aria-hidden="true"></span> {$PALANG.pMenu_dkim}</a></li>
                                    <li><a href="{#url_dkim_signing#}"><span class="glyphicon glyphicon-list" aria-hidden="true"></span> {$PALANG.pMenu_dkim_signing}</a></li>
                                    <li><a href="{#url_dkim_newkey#}"><span class="glyphicon glyphicon-plus" aria-hidden="true"></span> {$PALANG.pDkim_new_key}</a></li>
                                    <li><a href="{#url_dkim_newsign#}"><span class="glyphicon glyphicon-plus" aria-hidden="true"></span> {$PALANG.pDkim_new_sign}</a></li>
                                </ul>
                            </li>
                        {/strip}
                    {/if}
                    {* TOTP *}
                    {if $CONF.totp==='YES'}
                        {strip}
                            <li class="dropdown">
                                <a class="btn navbar-btn dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true"
                                   aria-expanded="false" href="{#url_dkim#}"><span class="glyphicon glyphicon-lock" aria-hidden="true"></span> {$PALANG.pMenu_security} <span
                                            class="caret"></span></a>
                                <ul class="dropdown-menu">
                                    <li><a href="{#url_password#}"><span class="glyphicon glyphicon-lock" aria-hidden="true"></span> {$PALANG.pMenu_password}</a></li>
                                    <li><a href="{#url_totp#}"><span class="glyphicon glyphicon-lock" aria-hidden="true"></span> {$PALANG.pMenu_totp}</a></li>
                                    <li><a href="{#url_totp_exceptions#}"><span class="glyphicon glyphicon-lock" aria-hidden="true"></span> {$PALANG.pMenu_totp_exceptions}</a></li>
                                    <li><a href="{#url_app_passwords#}"><span class="glyphicon glyphicon-lock" aria-hidden="true"></span> {$PALANG.pMenu_app_passwords}</a></li>
                                </ul>
                            </li>
                        {/strip}
                    {else}
                        {* password *}
                        <li><a class="btn navbar-btn" type="button" href="{#url_password#}">
            				<span class="glyphicon glyphicon-lock" aria-hidden="true"></span>
			            	{$PALANG.pMenu_password}</a></li>
	                {/if}	
                    {* backup *}
                    {if $authentication_has_role.global_admin && $CONF.database_type!=='pgsql' && $CONF.backup === 'YES'}
                        <li><a class="btn btn-default navbar-btn btn-sm" type="button"
                               href="{#url_backup#}">
				<span class="glyphicon glyphicon-menu-hamburger" aria-hidden="true"></span>
				{$PALANG.pAdminMenu_backup}</a></li>
                    {/if}
                    {* viewlog *}
                    {if $CONF.logging==='YES'}
                        <li><a class="btn navbar-btn" type="button"
                               href="{#url_viewlog#}">
				<span class="glyphicon glyphicon-file	" aria-hidden="true"></span>
				{$PALANG.pMenu_viewlog}</a></li>
                    {/if}
                    {* logout *}
                    <li><a class="btn navbar-btn" type="button"
                           href="{#url_logout#}">
				<span class="glyphicon glyphicon-log-out" aria-hidden="true"></span>
			 	{$PALANG.pMenu_logout}</a></li>
                </ul>
            </div><!--/.nav-collapse -->
        </div>
    </nav>
{/strip}
