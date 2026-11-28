<form name="search" method="get" action="list-virtual.php">
    <input type="hidden" name="domain" value="{$fDomain}">
    <div class="input-group">
        <input class="form-control" name="search[_]" value="{$search._|default:''}" size="10" title="{$PALANG.search_mailboxes_aliases}" placeholder="{$PALANG.search_mailboxes_aliases}" aria-label="{$PALANG.search_mailboxes_aliases}">
        <button class="btn btn-secondary" type="submit" title="{$PALANG.pMain_search_button}" aria-label="{$PALANG.pMain_search_button}"><span class="bi bi-search" aria-hidden="true"></span></button>
    </div>
</form>
