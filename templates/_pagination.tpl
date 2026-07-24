{*
 * Reusable Bootstrap 5 pagination.
 *
 * $pagination       - array of items; each item is an array with keys:
 *                       label    - text/entity to display
 *                       url      - href (used unless disabled/active/ellipsis)
 *                       active   - bool, marks the current page
 *                       disabled - bool, rendered as a non-clickable span
 *                       ellipsis - bool, rendered as a "..." gap
 *                       aria     - optional aria-label (for nav arrows)
 * $pagination_label - optional aria-label for the <nav> (defaults to "Pagination")
 *}
{if $pagination}
    <nav aria-label="{$pagination_label|default:'Pagination'}">
        <ul class="pagination justify-content-end mb-0">
            {foreach from=$pagination item=pi}
                {if $pi.ellipsis|default:false}
                    <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                {elseif $pi.disabled|default:false}
                    <li class="page-item disabled"><span class="page-link" aria-hidden="true">{$pi.label}</span></li>
                {elseif $pi.active|default:false}
                    <li class="page-item active"><span class="page-link">{$pi.label}</span></li>
                {else}
                    <li class="page-item"><a class="page-link" href="{$pi.url}"{if $pi.aria|default:''} aria-label="{$pi.aria}"{/if}>{$pi.label}</a></li>
                {/if}
            {/foreach}
        </ul>
    </nav>
{/if}
