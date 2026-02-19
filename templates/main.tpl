<!-- {$smarty.template} -->
<div class="card" id="main_menu">

    <section>
        <h2>Global Search</h2>
        <form method=GET class=form action="">
            <div class="input-group">
                <input type="text" id=q name=q class="form-control" value="{$q}" autofocus="autofocus"
                       placeholder="Global search (mailbox, alias ...)"
                       aria-label="Global Search">
                <span class="input-group-btn">
                    <button class="btn btn-secondary" type="submit">Search</button>
                </span>
            </div>
        </form>


        {if !empty($domains)}
            <h3>Domains</h3>
            <ul>
                {foreach from=$domains item=row}
                    <li>Domain: <a
                                href="list-virtual.php?domain={$row['domain']|escape:url}">{$row['domain']}</a>
                    </li>
                {/foreach}
            </ul>
        {/if}

        {if !empty($mailboxes)}
            <h3>Mailboxes</h3>
            <ul>
                {foreach from=$mailboxes item=row}
                    <li>Mailbox: <a
                                href="edit.php?table=mailbox&edit={$row['username']|escape:url}">{$row['username']}</a>
                    </li>
                {/foreach}
            </ul>
        {/if}


        {if !empty($aliases)}
            <h3>Aliases</h3>
            <ul>
                {foreach from=$aliases item=row}
                    <li>Alias: <a
                                href="edit.php?table=alias&edit={$row['address']|escape:url}">{$row['address']}</a>
                    </li>
                {/foreach}
            </ul>
        {/if}
    </section>

    <section>
        <h2>Dashboard</h2>
        <table class="table">
            <tr>
                <td nowrap="nowrap"><a style="text-align:left; padding-left:15px" class="btn btn-primary w-100"
                                       href="{#url_list_domain#}"><span class="bi bi-grid-3x3-gap"
                                                                        aria-hidden="true"></span> {$PALANG.pMenu_overview}
                    </a></td>
                <td style="padding-top: 15px;">{$PALANG.pMain_overview}</td>
            </tr>
            <tr>
                <td nowrap="nowrap"><a style="text-align:left; padding-left:15px" class="btn btn-primary w-100"
                                       href="{#url_create_alias#}"><span class="bi bi-plus-circle"
                                                                         aria-hidden="true"></span> {$PALANG.add_alias}
                    </a>
                </td>
                <td style="padding-top: 15px;">{$PALANG.pMain_create_alias}</td>
            </tr>
            <tr>
                <td nowrap="nowrap"><a style="text-align:left; padding-left:15px" class="btn btn-primary w-100"
                                       href="{#url_create_mailbox#}"><span class="bi bi-inbox"
                                                                           aria-hidden="true"></span> {$PALANG.add_mailbox}
                    </a></td>
                <td style="padding-top: 15px;">{$PALANG.pMain_create_mailbox}</td>
            </tr>
            {if $CONF.sendmail==='YES'}
                <tr>
                    <td nowrap="nowrap"><a style="text-align:left; padding-left:15px" class="btn btn-primary w-100"
                                           href="{#url_sendmail#}"><span class="bi bi-send"
                                                                         aria-hidden="true"></span> {$PALANG.pMenu_sendmail}
                        </a></td>
                    <td style="padding-top: 15px;">{$PALANG.pMain_sendmail}</td>
                </tr>
            {/if}
            {if $CONF.dkim==='YES' && (
            $authentication_has_role.global_admin ||
            (isset($CONF.dkim_all_admins) && $CONF.dkim_all_admins === 'YES') )
            }
                <tr>
                    <td nowrap="nowrap"><a style="text-align:left; padding-left:15px" class="btn btn-primary w-100"
                                           href="{#url_dkim#}"><span class="bi bi-patch-check"
                                                                     aria-hidden="true"></span> {$PALANG.pMenu_dkim}</a>
                    </td>
                    <td style="padding-top: 15px;">{$PALANG.pMain_dkim}</td>
                </tr>
            {/if}
            <tr>
                <td nowrap="nowrap"><a style="text-align:left; padding-left:15px" class="btn btn-primary w-100"
                                       href="{#url_password#}"><span class="bi bi-lock"
                                                                     aria-hidden="true"></span> {$PALANG.pMenu_password}
                    </a>
                </td>
                <td style="padding-top: 15px;">{$PALANG.pMain_password}</td>
            </tr>
            {* viewlog *}
            {if $CONF.logging==='YES'}
                <tr>
                    <td nowrap="nowrap"><a style="text-align:left; padding-left:15px" class="btn btn-primary w-100"
                                           href="{#url_viewlog#}"><span class="bi bi-file-text"
                                                                        aria-hidden="true"></span> {$PALANG.pMenu_viewlog}
                        </a></td>
                    <td style="padding-top: 15px;">{$PALANG.pMain_viewlog}</td>
                </tr>
            {/if}
            <tr>
                <td style="width: 150px;" nowrap="nowrap"><a style="text-align:left; padding-left:15px"
                                                             class="btn btn-primary w-100"
                                                             href="{#url_logout#}"><span
                                style="padding-left: 5px;" class="bi bi-box-arrow-right"
                                aria-hidden="true"></span> {$PALANG.pMenu_logout}</a></td>
                <td style="padding-top: 15px;">{$PALANG.pMain_logout}</td>
            </tr>
        </table>
    </section>
</div>
