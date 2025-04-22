<!-- {$smarty.template} -->
<div class="panel panel-default" id="main_menu">

    <section>
        <h2>Global Search</h2>
        <form method=GET class=form action="">
            <div class="input-group">
                <input type="text" id=q name=q class="form-control" value="{$q}" autofocus="autofocus"
                       placeholder="Global search (mailbox, alias ...)"
                       aria-label="Global Search">
                <span class="input-group-btn">
                    <button class="btn btn-default" type="submit">Search</button>
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
                <td nowrap="nowrap"><a style="text-align:left; padding-left:15px" class="btn btn-primary btn-block"
                                       href="{#url_list_domain#}"><span class="glyphicon glyphicon-th-large"
                                                                        aria-hidden="true"></span> {$PALANG.pMenu_overview}
                    </a></td>
                <td style="padding-top: 15px;">{$PALANG.pMain_overview}</td>
            </tr>
            <tr>
                <td nowrap="nowrap"><a style="text-align:left; padding-left:15px" class="btn btn-primary btn-block"
                                       href="{#url_create_alias#}"><span class="glyphicon glyphicon-plus-sign"
                                                                         aria-hidden="true"></span> {$PALANG.add_alias}
                    </a>
                </td>
                <td style="padding-top: 15px;">{$PALANG.pMain_create_alias}</td>
            </tr>
            <tr>
                <td nowrap="nowrap"><a style="text-align:left; padding-left:15px" class="btn btn-primary btn-block"
                                       href="{#url_create_mailbox#}"><span class="glyphicon glyphicon-inbox"
                                                                           aria-hidden="true"></span> {$PALANG.add_mailbox}
                    </a></td>
                <td style="padding-top: 15px;">{$PALANG.pMain_create_mailbox}</td>
            </tr>
            {if $CONF.sendmail==='YES'}
                <tr>
                    <td nowrap="nowrap"><a style="text-align:left; padding-left:15px" class="btn btn-primary btn-block"
                                           href="{#url_sendmail#}"><span class="glyphicon glyphicon-send"
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
                    <td nowrap="nowrap"><a style="text-align:left; padding-left:15px" class="btn btn-primary btn-block"
                                           href="{#url_dkim#}"><span class="glyphicon glyphicon-certificate"
                                                                     aria-hidden="true"></span> {$PALANG.pMenu_dkim}</a>
                    </td>
                    <td style="padding-top: 15px;">{$PALANG.pMain_dkim}</td>
                </tr>
            {/if}
            <tr>
                <td nowrap="nowrap"><a style="text-align:left; padding-left:15px" class="btn btn-primary btn-block"
                                       href="{#url_password#}"><span class="glyphicon glyphicon-lock"
                                                                     aria-hidden="true"></span> {$PALANG.pMenu_password}
                    </a>
                </td>
                <td style="padding-top: 15px;">{$PALANG.pMain_password}</td>
            </tr>
            {* viewlog *}
            {if $CONF.logging==='YES'}
                <tr>
                    <td nowrap="nowrap"><a style="text-align:left; padding-left:15px" class="btn btn-primary btn-block"
                                           href="{#url_viewlog#}"><span class="glyphicon glyphicon-file"
                                                                        aria-hidden="true"></span> {$PALANG.pMenu_viewlog}
                        </a></td>
                    <td style="padding-top: 15px;">{$PALANG.pMain_viewlog}</td>
                </tr>
            {/if}
            <tr>
                <td style="width: 150px;" nowrap="nowrap"><a style="text-align:left; padding-left:15px"
                                                             class="btn btn-primary btn-block"
                                                             href="{#url_logout#}"><span
                                style="padding-left: 5px;" class="glyphicon glyphicon-log-out"
                                aria-hidden="true"></span> {$PALANG.pMenu_logout}</a></td>
                <td style="padding-top: 15px;">{$PALANG.pMain_logout}</td>
            </tr>
        </table>
    </section>
</div>
