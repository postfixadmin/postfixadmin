#!/bin/sh

# Optional composite domain-postdeletion hook for a Maildir installation that
# also manages per-domain Rspamd DKIM keys.
#
# Run this wrapper as the Maildir owner. It calls the generic Maildir helper as
# the current user and the DKIM helper through a narrowly scoped sudoers rule.

# Adjust these installation paths before enabling the hook.
maildir_delete_script=/usr/local/libexec/postfixadmin/postfixadmin-domain-postdeletion.sh
dkim_delete_script=/usr/local/libexec/postfixadmin/postfixadmin-rspamd-dkim-postdeletion.sh
sudo_path=/usr/bin/sudo
rspamd_user=_rspamd

fail()
{
    printf '%s\n' "$0: $*" >&2
    exit 1
}

[ "$#" -eq 1 ] || fail "expected exactly one domain argument"
domain=$1

[ -x "$maildir_delete_script" ] || fail "Maildir helper '$maildir_delete_script' is not executable"
[ -x "$dkim_delete_script" ] || fail "DKIM helper '$dkim_delete_script' is not executable"
[ -x "$sudo_path" ] || fail "sudo executable '$sudo_path' was not found"

status=0
if ! "$maildir_delete_script" "$domain"; then
    printf '%s\n' "$0: Maildir deletion helper failed for '$domain'" >&2
    status=1
fi

if ! "$sudo_path" -n -u "$rspamd_user" -- "$dkim_delete_script" "$domain"; then
    printf '%s\n' "$0: Rspamd DKIM deletion helper failed for '$domain'" >&2
    status=1
fi

exit "$status"
