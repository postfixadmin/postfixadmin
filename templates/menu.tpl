<!-- {$smarty.template} -->
{strip}
{if !empty($smarty.get) && !empty($smarty.get.domain)}
{*** zuweisung muss eleganter gehen ***}
	{assign var="url_domain" value=$smarty.get.domain}
	{assign var="url_domain" value="&amp;domain={$url_domain|escape:url}"}
{/if}
{/strip}
<div id="menu">
<ul>
{* list-admin *}
{if $authentication_has_role.global_admin}
{strip}
	<li><a target="_top" href="{#url_list_admin#}">{$PALANG.pAdminMenu_list_admin}</a>
		<ul>
			<li><a target="_top" href="{#url_list_admin#}">{$PALANG.pAdminMenu_list_admin}</a></li>
			<li><a target="_top" href="{#url_create_admin#}">{$PALANG.pAdminMenu_create_admin}</a></li>
		</ul>
	</li>
{/strip}
{else}
	<li><a target="_top" href="{#url_main#}">{$PALANG.pMenu_main}</a></li>
{/if}
{* list-domain *}
{strip}
	<li><a target="_top" href="{#url_list_domain#}">{$PALANG.pAdminMenu_list_domain}</a>
		<ul>
			<li><a target="_top" href="{#url_list_domain#}">{$PALANG.pAdminMenu_list_domain}</a></li>
{if $authentication_has_role.global_admin}
			<li><a target="_top" href="{#url_edit_domain#}">{$PALANG.pAdminMenu_create_domain}</a></li>
{/if}
		</ul>
	</li>
{/strip}
{* list-virtual *}
{strip}
	<li><a target="_top" href="{#url_list_virtual#}">{$PALANG.pAdminMenu_list_virtual}</a>
		<ul>
			<li><a target="_top" href="{#url_list_virtual#}">{$PALANG.pAdminMenu_list_virtual}</a></li>
			<li><a target="_top" href="{#url_create_mailbox#}{$url_domain}">{$PALANG.add_mailbox}</a></li>
			<li><a target="_top" href="{#url_create_alias#}{$url_domain}">{$PALANG.add_alias}</a></li>
{if $boolconf_alias_domain}
			<li><a target="_top" href="{#url_create_alias_domain#}{$url_domain}">{$PALANG.add_alias_domain}</a></li>
{/if}
		</ul>
	</li>
{/strip}
{* fetchmail *}
{if $CONF.fetchmail==='YES'}
{strip}
	<li><a target="_top" href="{#url_fetchmail#}">{$PALANG.pMenu_fetchmail}</a>
		<ul>
			<li><a target="_top" href="{#url_fetchmail#}">{$PALANG.pMenu_fetchmail}</a></li>
			<li><a target="_top" href="{#url_fetchmail_new_entry#}">{$PALANG.pFetchmail_new_entry}</a></li>
		</ul>
	</li>
{/strip}
{/if}
{* sendmail *}
{if $CONF.sendmail==='YES'}
{strip}
	<li><a target="_top" href="{#url_sendmail#}">{$PALANG.pMenu_sendmail}</a>
		<ul>
			<li><a target="_top" href="{#url_sendmail#}">{$PALANG.pMenu_sendmail}</a></li>
{if $authentication_has_role.global_admin}
			<li><a target="_top" href="{#url_broadcast_message#}">{$PALANG.pAdminMenu_broadcast_message}</a></li>
{/if}
		</ul>
	</li>
{/strip}
{/if}
{* password *}
	<li><a target="_top" href="{#url_password#}">{$PALANG.pMenu_password}</a></li>
{* backup *}
{if $authentication_has_role.global_admin && $CONF.database_type!=='pgsql' && $CONF.backup === 'YES'}
	<li><a target="_top" href="{#url_backup#}">{$PALANG.pAdminMenu_backup}</a></li>
{/if}
{* viewlog *}
	<li><a target="_top" href="{#url_viewlog#}">{$PALANG.pMenu_viewlog}</a></li>
{* logout *}
	<li class="logout"><a target="_top" href="{#url_logout#}">{$PALANG.pMenu_logout}</a></li>
</ul>
</div>
{literal}
<script type='text/javascript'>
// <![CDATA[
sfHover = function()
{
	var sfEls = document.getElementById("menu").getElementsByTagName("LI");
	for (var i=0; i<sfEls.length; i++)
	{
		sfEls[i].onmouseover=function()
		{
			this.className+=" sfhover";
		}
		sfEls[i].onmouseout=function()
		{
			this.className=this.className.replace(new RegExp(" sfhover\\b"), "");
		}
	}
}
if (window.attachEvent)
	window.attachEvent("onload", sfHover);
// ]]>
</script>
{/literal}
