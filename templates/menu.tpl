<!-- {$smarty.template} -->
{strip}
    {if !empty($smarty.get) && !empty($smarty.get.domain)}
        {*** zuweisung muss eleganter gehen ***}
        {assign var="url_domain" value=$smarty.get.domain}
        {assign var="url_domain" value="&amp;domain={$url_domain|escape:url}"}
    {/if}
{/strip}

{strip}
    <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top">
        <div class="container-fluid">
            {*** <a class="navbar-brand" href='main.php'><img id="login_header_logo" src="{$CONF.theme_logo}" alt="Logo" /></a> ***}
            <a class="navbar-brand" href='main.php'><img id="login_header_logo"
                                                         src="{$CONF.theme_logo|default:'images/postbox.png'}"
                                                         alt="Logo"/></a>
            <button type="button" class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navbar"
                    aria-expanded="false" aria-controls="navbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div id="navbar" class="collapse navbar-collapse">
                <ul class="nav navbar-nav">
                    {* list-admin *}
                    {if $authentication_has_role.global_admin}
                        {strip}
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button"
                                   aria-haspopup="true"
                                   aria-expanded="false" ><span
                                            class="bi bi-list"
                                            aria-hidden="true"></span> {$PALANG.pAdminMenu_list_admin} <span
                                            class="caret"></span></a>
                                <ul class="dropdown-menu">
                                    <li class="nav-item" class="nav-item">
                                        <a class="dropdown-item" href="{#url_list_admin#}"><span class="bi bi-list"
                                                                           aria-hidden="true"></span> {$PALANG.pAdminMenu_list_admin}
                                        </a>
                                    </li>
                                    <li class="nav-item" class="nav-item">
                                        <a class="dropdown-item" href="{#url_create_admin#}"><span class="bi bi-plus"
                                                                             aria-hidden="true"></span> {$PALANG.pAdminMenu_create_admin}
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        {/strip}
                    {else}
                        <li>
                            <a class="nav-link" href="{#url_main#}">
                                <span class="bi bi-house" aria-hidden="true"></span> {$PALANG.pMenu_main}
                            </a>
                        </li>
                    {/if}
                    {* list-domain *}
                    {strip}
                        <li>
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button"
                               aria-haspopup="true"
                               aria-expanded="false" ><span
                                        class="bi bi-list-task"
                                        aria-hidden="true"></span> {$PALANG.pAdminMenu_list_domain} <span
                                        class="caret"></span></a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{#url_list_domain#}"><span class="bi bi-list-task"
                                                                        aria-hidden="true"></span> {$PALANG.pAdminMenu_list_domain}
                                    </a></li>
                                {if $authentication_has_role.global_admin}
                                    <li><a class="dropdown-item" href="{#url_edit_domain#}"><span class="bi bi-plus"
                                                                            aria-hidden="true"></span> {$PALANG.pAdminMenu_create_domain}
                                        </a></li>
                                {/if}
                            </ul>
                        </li>
                    {/strip}
                    {* list-virtual *}
                    {strip}
                        <li class="dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button"
                               aria-haspopup="true"
                               aria-expanded="false" ><span
                                        class="bi bi-list-ul"
                                        aria-hidden="true"></span> {$PALANG.pAdminMenu_list_virtual} <span
                                        class="caret"></span></a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{#url_list_virtual#}"><span class="bi bi-list-ul"
                                                                         aria-hidden="true"></span> {$PALANG.pAdminMenu_list_virtual}
                                    </a></li>
                                <li><a class="dropdown-item" href="{#url_create_mailbox#}{$url_domain}"><span
                                                class="bi bi-inbox"
                                                aria-hidden="true"></span> {$PALANG.add_mailbox}</a></li>
                                <li><a class="dropdown-item" href="{#url_create_alias#}{$url_domain}"><span
                                                class="bi bi-plus-circle"
                                                aria-hidden="true"></span> {$PALANG.add_alias}</a></li>
                                {if $boolconf_alias_domain}
                                    <li>
                                        <a class="dropdown-item" href="{#url_create_alias_domain#}{$url_domain}">
                                            <span class="bi bi-plus-circle"
                                                  aria-hidden="true"></span> {$PALANG.add_alias_domain}</a>
                                    </li>
                                {/if}
                            </ul>
                        </li>
                    {/strip}
                    {* fetchmail *}
                    {if $CONF.fetchmail==='YES'}
                        {strip}
                            <li class="dropdown">
                                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button"
                                   aria-haspopup="true"
                                   aria-expanded="false" >{$PALANG.pMenu_fetchmail} <span
                                            class="caret"></span></a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="{#url_fetchmail#}">
                                            <span class="bi bi-list-ul"
                                                  aria-hidden="true"></span> {$PALANG.pMenu_fetchmail}</a></li>
                                    <li><a class="dropdown-item" href="{#url_fetchmail_new_entry#}">
                                              <span class="bi bi-plus-circle"
                                                    aria-hidden="true"></span> {$PALANG.pFetchmail_new_entry}</a></li>
                                </ul>
                            </li>
                        {/strip}
                    {/if}
                    {* sendmail *}
                    {if $CONF.sendmail==='YES'}
                        {strip}
                            <li class="dropdown">
                                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button"
                                   aria-haspopup="true"
                                   aria-expanded="false" ><span class="bi bi-send"
                                                                                       aria-hidden="true"></span> {$PALANG.pMenu_sendmail}
                                    <span
                                            class="caret"></span></a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="{#url_sendmail#}"><span class="bi bi-send"
                                                                         aria-hidden="true"></span> {$PALANG.pMenu_sendmail}
                                        </a></li>
                                    {if $authentication_has_role.global_admin || (isset($CONF.sendmail_all_admins) && $CONF.sendmail_all_admins === 'YES') }
                                        <li>
                                            <a class="dropdown-item" href="{#url_broadcast_message#}"><span class="bi bi-share"
                                                                                      aria-hidden="true"></span> {$PALANG.pAdminMenu_broadcast_message}
                                            </a>
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
                                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button"
                                   aria-haspopup="true"
                                   aria-expanded="false" ><span
                                            class="bi bi-patch-check"
                                            aria-hidden="true"></span> {$PALANG.pMenu_dkim} <span
                                            class="caret"></span></a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="{#url_dkim#}"><span class="bi bi-patch-check"
                                                                     aria-hidden="true"></span> {$PALANG.pMenu_dkim}</a>
                                    </li>
                                    <li><a class="dropdown-item" href="{#url_dkim_signing#}"><span class="bi bi-list"
                                                                             aria-hidden="true"></span> {$PALANG.pMenu_dkim_signing}
                                        </a></li>
                                    <li><a class="dropdown-item" href="{#url_dkim_newkey#}"><span class="bi bi-plus"
                                                                            aria-hidden="true"></span> {$PALANG.pDkim_new_key}
                                        </a></li>
                                    <li><a class="dropdown-item" href="{#url_dkim_newsign#}"><span class="bi bi-plus"
                                                                             aria-hidden="true"></span> {$PALANG.pDkim_new_sign}
                                        </a></li>
                                </ul>
                            </li>
                        {/strip}
                    {/if}
                    {* TOTP *}
                    {if $CONF.totp==='YES'}
                        {strip}
                            <li class="dropdown">
                                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button"
                                   aria-haspopup="true"
                                   aria-expanded="false" ><span class="bi bi-lock"
                                                                                   aria-hidden="true"></span> {$PALANG.pMenu_security}
                                    <span
                                            class="caret"></span></a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="{#url_password#}"><span class="bi bi-lock"
                                                                         aria-hidden="true"></span> {$PALANG.pMenu_password}
                                        </a></li>
                                    <li><a class="dropdown-item" href="{#url_totp#}"><span class="bi bi-lock"
                                                                     aria-hidden="true"></span> {$PALANG.pMenu_totp}</a>
                                    </li>
                                    <li><a class="dropdown-item" href="{#url_totp_exceptions#}"><span class="bi bi-lock"
                                                                                aria-hidden="true"></span> {$PALANG.pMenu_totp_exceptions}
                                        </a></li>
                                    <li><a class="dropdown-item" href="{#url_app_passwords#}"><span class="bi bi-lock"
                                                                              aria-hidden="true"></span> {$PALANG.pMenu_app_passwords}
                                        </a></li>
                                </ul>
                            </li>
                        {/strip}
                    {else}
                        {* password *}
                        <li><a class="nav-link" type="button" href="{#url_password#}">
                                <span class="bi bi-lock" aria-hidden="true"></span>
                                {$PALANG.pMenu_password}</a></li>
                    {/if}
                    {* backup *}
                    {if $authentication_has_role.global_admin && $CONF.database_type!=='pgsql' && $CONF.backup === 'YES'}
                        <li><a class="btn btn-secondary navbar-btn btn-sm" type="button"
                               href="{#url_backup#}">
                                <span class="bi bi-list" aria-hidden="true"></span>
                                {$PALANG.pAdminMenu_backup}</a></li>
                    {/if}
                    {* viewlog *}
                    {if $CONF.logging==='YES'}
                        <li><a class="nav-link" type="button"
                               href="{#url_viewlog#}">
                                <span class="bi bi-file-text	" aria-hidden="true"></span>
                                {$PALANG.pMenu_viewlog}</a></li>
                    {/if}
                    {* logout *}
                    <li><a class="nav-link" type="button"
                           href="{#url_logout#}">
                            <span class="bi bi-box-arrow-right" aria-hidden="true"></span>
                            {$PALANG.pMenu_logout}</a></li>
                </ul>
            </div><!--/.nav-collapse -->
        </div>
    </nav>
{/strip}
