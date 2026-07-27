#!/bin/sh

# Optional PostfixAdmin domain-postcreation hook for Rspamd DKIM keys.
# Run this script as the Rspamd service account, never as root.

# Adjust these values for the local Rspamd installation.
dkimdir=/var/db/rspamd/dkim
selector=default
key_bits=2048

fail()
{
    printf '%s\n' "$0: $*" >&2
    exit 1
}

valid_domain()
{
    candidate=$1

    [ -n "$candidate" ] && [ "${#candidate}" -le 253 ] || return 1
    case "$candidate" in
        -*|.*|*.|*..*|*/*|*\\*|*[!A-Za-z0-9.-]*) return 1 ;;
    esac

    remainder=$candidate
    while :; do
        case "$remainder" in
            *.*)
                label=${remainder%%.*}
                remainder=${remainder#*.}
                ;;
            *)
                label=$remainder
                remainder=
                ;;
        esac

        [ -n "$label" ] && [ "${#label}" -le 63 ] || return 1
        case "$label" in
            -*|*-) return 1 ;;
        esac
        [ -n "$remainder" ] || break
    done
}

temporary_private=
temporary_public=
# Invoked by the traps installed below.
# shellcheck disable=SC2329
cleanup()
{
    [ -z "$temporary_private" ] || rm -f "$temporary_private"
    [ -z "$temporary_public" ] || rm -f "$temporary_public"
}
trap cleanup 0
trap 'exit 1' HUP INT TERM

[ "$#" -eq 1 ] || fail "expected exactly one domain argument"
domain=$1
valid_domain "$domain" || fail "invalid domain '$domain'"

[ -d "$dkimdir" ] || fail "DKIM directory '$dkimdir' does not exist"
[ -w "$dkimdir" ] || fail "DKIM directory '$dkimdir' is not writable"

rspamadm_path=$(command -v rspamadm 2>/dev/null) || fail "could not find rspamadm in PATH"
mktemp_path=$(command -v mktemp 2>/dev/null) || fail "could not find mktemp in PATH"

private_key="${dkimdir%/}/${domain}.${selector}.private"
public_record="${dkimdir%/}/${domain}.${selector}.txt"

if [ -e "$private_key" ] || [ -L "$private_key" ] ||
    [ -e "$public_record" ] || [ -L "$public_record" ]; then
    fail "DKIM files already exist for domain '$domain' and selector '$selector'"
fi

umask 077
temporary_private=$("$mktemp_path" "${dkimdir%/}/.${domain}.${selector}.private.XXXXXX") ||
    fail "could not create a temporary private-key file"
temporary_public=$("$mktemp_path" "${dkimdir%/}/.${domain}.${selector}.txt.XXXXXX") ||
    fail "could not create a temporary public-record file"

if ! "$rspamadm_path" dkim_keygen -k "$temporary_private" -b "$key_bits" \
    -s "$selector" -d "$domain" > "$temporary_public"; then
    fail "rspamadm could not generate DKIM keys for '$domain'"
fi

[ -s "$temporary_private" ] || fail "rspamadm produced an empty private key"
[ -s "$temporary_public" ] || fail "rspamadm produced an empty public record"
chmod 600 "$temporary_private" || fail "could not protect the private key"
chmod 644 "$temporary_public" || fail "could not set public-record permissions"

# Hard links publish both files without overwriting an existing key created by
# another concurrent invocation. The temporary files are on the same filesystem.
if ! ln "$temporary_private" "$private_key"; then
    fail "could not publish private key '$private_key'"
fi
if ! ln "$temporary_public" "$public_record"; then
    rm -f "$private_key"
    fail "could not publish public record '$public_record'"
fi

rm -f "$temporary_private" "$temporary_public"
temporary_private=
temporary_public=
trap - 0 HUP INT TERM

printf '%s\n' "$0: generated DKIM files for '$domain':"
printf '  %s\n  %s\n' "$private_key" "$public_record"
exit 0
