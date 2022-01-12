<div class="panel panel-default">
    <div class="panel-heading">
        <form name="frmOverview" method="post" action="">
	    {html_options name='fDomain' output=$domain_list values=$domain_list selected=$domain_selected onchange="this.form.submit();"}
            <noscript><input class="button" type="submit" name="go" value="{$PALANG.go}"/></noscript>
        </form>
    </div>
    {if $tLog}
        <div class="panel-body">
	    {if $domain_selected}
                <h4>{$PALANG.pViewlog_welcome|replace:"%s":$CONF.page_size} {$fDomain} </h4>
            {else}
                <h4>{$PALANG.pViewlog_welcome_all|replace:"%s":$CONF.page_size}</h4>
            {/if}
        </div>
        <table id="log_table" class="table">
            {#tr_header#}
            <th>{$PALANG.pViewlog_timestamp}</th>
            <th>{$PALANG.admin}</th>
            <th>{$PALANG.domain}</th>
            <th>{$PALANG.pViewlog_action}</th>
            <th>{$PALANG.pViewlog_data}</th>
            </tr>
            {assign var="PALANG_pViewlog_data" value=$PALANG.pViewlog_data}

            {foreach from=$tLog item=item}
                {assign var=log_data value=$item.data|truncate:35:"...":true}
                {assign var=item_data value=$item.data}
                {$smarty.config.tr_hilightoff|replace:'>':" style=\"cursor:pointer;\" onclick=\"alert('$PALANG_pViewlog_data = $item_data')\">"}
                <td nowrap="nowrap">{$item.timestamp}</td>
                <td nowrap="nowrap">{$item.username}</td>
                <td nowrap="nowrap">{$item.domain}</td>
                <td nowrap="nowrap">{$item.action}</td>
                <td nowrap="nowrap">{$log_data}</td>
                </tr>
            {/foreach}
        </table>
   	
		<div class="row">
	  		<div class="container">
				
	    		<div class="col-md-4">
	    		</div>

	    		<div class="col-md-4 col-md-offset-4">
					<div class="pull-right" style="margin: 10px;">

			   			{if $number_of_pages < 6}
							<div class="btn-group mr-2" role="group">
								{for $i=1 to $number_of_pages}
									<button type="button" onclick=go2page({$i}) class="btn btn-secondary">{$i}</button>
								{/for}
							</div>
			   			{elseif $page_number > 3 && $page_number < ($number_of_pages -2 )}
							<div class="btn-group mr-2" role="group">
								<button type="button" onclick=go2page(1) class="btn btn-secondary">1</button>
							</div>
							<div class="btn-group mr-2" role="group">
								<button type="button" onclick=go2page({$page_number-1}) class="btn btn-secondary">{$page_number-1}</button>
								<button type="button" onclick=go2page({$page_number}) class="btn btn-secondary">{$page_number}</button>
								<button type="button" onclick=go2page({$page_number+1}) class="btn btn-secondary">{$page_number+1}</button>
							</div>
							<div class="btn-group mr-2" role="group">
								<button type="button" onclick=go2page({$number_of_pages}) class="btn btn-secondary">{$number_of_pages}</button>
							</div>

							
			   			{else}
							{if $page_number < 4}
								<div class="btn-group mr-2" role="group">
									<button type="button" onclick=go2page(1) class="btn btn-secondary">1</button>
									<button type="button" onclick=go2page(2) class="btn btn-secondary">2</button>
									<button type="button" onclick=go2page(3) class="btn btn-secondary">3</button>
									<button type="button" onclick=go2page(4) class="btn btn-secondary">4</button>
								</div>
								<div class="btn-group mr-2" role="group">
									<button type="button" onclick=go2page({$number_of_pages}) class="btn btn-secondary">{$number_of_pages}</button>
								</div>
								
							{else}
								<div class="btn-group mr-2" role="group">
									<button type="button" onclick=go2page(1) class="btn btn-secondary">1</button>
								</div>
								<div class="btn-group mr-2" role="group">
									<button type="button" onclick=go2page({$number_of_pages-3}) class="btn btn-secondary">{$number_of_pages-3}</button>
									<button type="button" onclick=go2page({$number_of_pages-2}) class="btn btn-secondary">{$number_of_pages-2}</button>
									<button type="button" onclick=go2page({$number_of_pages-1}) class="btn btn-secondary">{$number_of_pages-1}</button>
									<button type="button" onclick=go2page({$number_of_pages}) class="btn btn-secondary">{$number_of_pages}</button>
								</div>
			  		 		{/if}
						{/if}

						<div class="btn-group" role="group"><div class="dropdown">
							<button class="btn dropdown-toggle" type="button" data-toggle="dropdown">Page <span class="caret"></span></button>
								<ul class="dropdown-menu" style="max-height: 200px; overflow: auto">
									{for $i=1 to $number_of_pages}
										<li><a onclick=go2page({$i}) href="#">{$i}</a></li>
									{/for}
								</ul>
						</div>

			    	</div>
		  		</div>
			</div>
		</div>	
	
    {/if}
</div>

<script>
        function go2page(page){
                window.location.href = '{$url}?page='+page+'&fDomain={$fDomain}';
        }
</script>
