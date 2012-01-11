<!-- {$smarty.template} -->
<br clear="all"/><br />
{strip}
		{if isset($smarty.session.flash)}
			{if isset($smarty.session.flash.info)}
				<ul class="flash-info">
					{foreach from=$smarty.session.flash.info item=msg}
						<li>{$msg|escape:html}</li>
					{/foreach}
				</ul>
			{/if}
			{if isset($smarty.session.flash.error)}
				<ul class="flash-error">
					{foreach from=$smarty.session.flash.error item=msg}
						<li>{$msg|escape:html}</li>
					{/foreach}
				</ul>
			{/if}
		{/if}
{/strip}
