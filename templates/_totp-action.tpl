{if $has_totp}
    <button type="submit" name="reset_totp" value="1" class="btn btn-warning btn-sm">{$PALANG.pUsersMenu_reset_totp}</button>
{else}
    {$PALANG.pUsersMenu_no_totp_set}
{/if}
