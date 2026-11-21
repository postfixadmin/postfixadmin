{assign var="file" value=$smarty.config.url_list_virtual}
<div id="overview" class="card">
    <div class="card-header">
        <div class="row align-items-center gy-2">
            <div class="{if $dns_check_mode > 0}col-md-4{else}col-md-5{/if}">
                <form name="frmOverview" method="get" action="{$smarty.config.url_list_virtual}">
                    {html_options name='domain' class='form-control' options=$domain_options selected=$domain_selected onchange="this.form.submit();"}
                    <input type="hidden" name="limit" value="0"/>
                    <noscript><input class="button" type="submit" name="go" value="{$PALANG.go}"/></noscript>
                </form>
            </div>
            {if $dns_check_mode > 0}
                <div class="col-md-5">
                    {if !$show_all}
                    <form method="post" action="refresh-domain-dns.php" class="d-flex align-items-center gap-2 flex-wrap">
                        {CSRF_Token}
                        <input type="hidden" name="domain" value="{$domain_selected}">
                        <button type="submit" class="btn btn-sm btn-secondary" title="{$PALANG.dns_refresh}"><span class="bi bi-arrow-clockwise" aria-hidden="true"></span> DNS</button>
                        {if isset($domain_dns_status.dns_active) && $domain_dns_status.dns_active == 0}
                            <span class="text-danger"><span class="bi bi-exclamation-triangle" aria-hidden="true"></span> {$PALANG.dns_inactive}</span>
                        {/if}
                        <small class="text-muted">{$PALANG.dns_last_check}: {$domain_dns_status.dns_checked|default:$PALANG.dns_not_checked}</small>
                    </form>
                    {/if}
                </div>
            {/if}
            <div class="{if $dns_check_mode > 0}col-md-3{else}col-md-5 offset-md-2{/if} text-right virtual-search">{include file='virtual-search.tpl'}</div>
        </div>
    </div>
    <div class="card-body">
        <div class="text-center">
            {if isset($search._)}
                <h4>{$PALANG.pSearch_welcome} {$search._} ({if $show_all}{$PALANG.pViewlog_all_domains}{else}{$fDomain}{/if})</h4>
            {elseif $show_all}
                <h4>{$PALANG.pOverview_welcome}{$PALANG.pViewlog_all_domains} :</h4>
            {else}
                <h4>{$PALANG.pOverview_welcome}{$fDomain}</h4>
                <ul>
                    <li style="list-style-type: none">{$PALANG.aliases}: {$limit.alias_count} / {$limit.aliases}</li>
                    <li style="list-style-type: none"> {$PALANG.mailboxes}: {$limit.mailbox_count}
                        / {$limit.mailboxes}</li>
                </ul>
            {/if}
        </div>
        <hr/>
        <div class="row">
            <div class="col-md-12 text-center">{$PALANG.show}
                {if isset($search._)}
                    {* plain '&' here - auto-escaping turns it into &amp; when printed *}
                    {assign var="searchsuffix" value="&search[_]={$search._|escape:url}"}
                {else}
                    {assign var="searchsuffix" value=""}
                {/if}

                {if $tab=='all'}<span class='active'>{$PALANG.all}</span>
                {else}<a href="?domain={$smarty.get.domain|escape:url}&amp;tab=all{$searchsuffix}">{$PALANG.all}</a>{/if}
                {if $tab=='mailbox'}<span class='active'>{$PALANG.pOverview_mailbox_title}</span>
                {else}<a
                    href="?domain={$smarty.get.domain|escape:url}&amp;tab=mailbox{$searchsuffix}">{$PALANG.pOverview_mailbox_title}</a>{/if}
                {if $tab=='alias'}<span class='active'>{$PALANG.pOverview_alias_title}</span>
                {else}<a
                    href="?domain={$smarty.get.domain|escape:url}&amp;tab=alias{$searchsuffix}">{$PALANG.pOverview_alias_title}</a>{/if}
                {if $boolconf_alias_domain}
                    {if $tab=='alias_domain'}<span class='active'>{$PALANG.pOverview_alias_domain_title}</span>
                    {else}<a
                        href="?domain={$smarty.get.domain|escape:url}&amp;tab=alias_domain{$searchsuffix}">{$PALANG.pOverview_alias_domain_title}</a>{/if}
                {/if}
            </div>
        </div>
    </div>
</div>
<div class="clearfix"></div><br/>
{*** Domain Aliases ***}
{if $boolconf_alias_domain}
    {if $tab=='alias_domain' || $tab=='all'}
        {include file="list-virtual_alias_domain.tpl"}
    {/if}
{/if}
{if $tab=='all'}
    <div class="clearfix"></div>
    <br/>
{/if}
{*** Aliases ***}
{if $tab=='alias' || $tab=='all'}
    <div class="text-center mb-2">{$PALANG.alias_active_filter}
        {if $alias_active=='all'}<span class='active'>{$PALANG.all}</span>
        {else}<a href="?domain={$smarty.get.domain|escape:url}&amp;tab={$tab}&amp;alias_active=all&amp;limit=0{$searchsuffix}">{$PALANG.all}</a>{/if}
        {if $alias_active=='active'}<span class='active'>{$PALANG.active}</span>
        {else}<a href="?domain={$smarty.get.domain|escape:url}&amp;tab={$tab}&amp;alias_active=active&amp;limit=0{$searchsuffix}">{$PALANG.active}</a>{/if}
        {if $alias_active=='inactive'}<span class='active'>{$PALANG.inactive}</span>
        {else}<a href="?domain={$smarty.get.domain|escape:url}&amp;tab={$tab}&amp;alias_active=inactive&amp;limit=0{$searchsuffix}">{$PALANG.inactive}</a>{/if}
    </div>
    {include file="list-virtual_alias.tpl"}
    {if $alias_active=='inactive' && $tAlias && !$show_all}
        <div class="text-center mb-2">
            <form method="post" action="delete-inactive.php" style="display:inline">
                <input type="hidden" name="fDomain" value="{$fDomain}">
                {CSRF_Token}
                <button class="btn btn-sm btn-danger"
                        onclick="return confirm('{$PALANG.delete_inactive_confirm|escape:"javascript"|escape:"force"}')"><span
                            class="bi bi-trash" aria-hidden="true"></span> {$PALANG.delete_inactive_button}</button>
            </form>
        </div>
    {/if}
{/if}
{if $tab=='all'}
    <div class="clearfix"></div>
    <br/>
{/if}
{if $tab=='mailbox' || $tab=='all'}
    <div id="mailboxes" class="card">
        {assign var="colspan" value=9}
        {if $CONF.vacation_control_admin===YES}{assign var="colspan" value="`$colspan+1`"}{/if}
        {if $CONF.alias_control_admin===YES}{assign var="colspan" value="`$colspan+1`"}{/if}


            {include file="list-virtual_mailbox.tpl"}
      


        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="btn-group">
                {if $tCanAddMailbox}
                    <a href="{#url_create_mailbox#}{if !$show_all}&amp;domain={$fDomain|escape:"url"}{/if}" role="button"
                        class="btn btn-secondary"><span class="bi bi-plus-circle"
                                                        aria-hidden="true"></span> {$PALANG.add_mailbox}</a>
                {/if}
                <a role="button" class="btn btn-secondary"
                    href="list.php?table=mailbox&amp;output=csv&amp;domain={$domain_selected|escape:url}{if isset($search._) && $search._ !== ''}&amp;search%5B_%5D={$search._|escape:url}{else}&amp;reset_search=1{/if}"><span
                            class="bi bi-box-arrow-up-right" aria-hidden="true"></span> {$PALANG.download_csv}</a>
            </div>
        </div>
    </div>
{/if}
{if $CONF.show_status===YES && $CONF.show_status_key===YES}
    <br/>
    <br/>
    {if $CONF.show_undeliverable===YES}
        &nbsp;
        <span title="{$PALANG.pStatus_undeliverable_tooltip}">
            <span style='background-color:{$CONF.show_undeliverable_color};'>{$CONF.show_status_text}</span>
            ={$PALANG.pStatus_undeliverable}
        </span>
        &nbsp;
        <span title="{$PALANG.pStatus_external_tooltip}">
            <span style='background-color:{$CONF.show_external_color};'>{$CONF.show_status_text}</span>
            ={$PALANG.pStatus_external}
        </span>
    {/if}
    {if $CONF.show_popimap===YES}
        &nbsp;
        <span style='background-color:{$CONF.show_popimap_color};'>{$CONF.show_status_text|raw}</span>
        ={$PALANG.pStatus_popimap}
    {/if}
    {if $CONF.show_custom_domains|@count>0}
        {foreach from=$CONF.show_custom_domains item=item key=i}
            &nbsp;
            <span style='background-color:{$CONF.show_custom_colors[$i]};'>{$CONF.show_status_text|raw}</span>
            ={$PALANG.pStatus_custom}{$item}
        {/foreach}
    {/if}
{/if}
