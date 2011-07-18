<!-- {$smarty.template} -->
<br clear="all"/><br />
{strip}
		{if $smarty.session.flash}
			{if $smarty.session.flash.info}
				<ul class="flash-info">
					{foreach from=$smarty.session.flash.info item=msg}
						<li>{$msg}</li>
					{/foreach}
				</ul>
			{/if}
			{if $smarty.session.flash.error}
				<ul class="flash-error">
					{foreach from=$smarty.session.flash.error item=msg}
						<li>{$msg}</li>
					{/foreach}
				</ul>
			{/if}
		{/if}
{/strip}