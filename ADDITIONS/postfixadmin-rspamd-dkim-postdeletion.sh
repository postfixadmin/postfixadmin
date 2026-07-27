#!/bin/sh

# Optional Rspamd DKIM deletion helper. It moves all selector files belonging
# to a domain into a Rspamd-owned recovery directory. Run it as the Rspamd
# service account, never as root.

# Adjust these values for the local Rspamd installation.
dkimdir=/var/db/rspamd/dkim
trashbase=/var/db/rspamd/deleted-dkim

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

[ "$#" -eq 1 ] || fail "expected exactly one domain argument"
domain=$1
valid_domain "$domain" || fail "invalid domain '$domain'"

[ -d "$dkimdir" ] || fail "DKIM directory '$dkimdir' does not exist"
[ -d "$trashbase" ] || fail "DKIM trash directory '$trashbase' does not exist"
[ -w "$dkimdir" ] || fail "DKIM directory '$dkimdir' is not writable"
[ -w "$trashbase" ] || fail "DKIM trash directory '$trashbase' is not writable"

timestamp=$(date '+%Y%m%dT%H%M%S') || fail "could not generate a timestamp"
trashdir="${trashbase%/}/${timestamp}_$$_${domain}"
found=false
status=0

umask 077
for keyfile in "${dkimdir%/}/${domain}".*; do
    if [ ! -e "$keyfile" ] && [ ! -L "$keyfile" ]; then
        continue
    fi
    found=true

    if [ -L "$keyfile" ] || [ ! -f "$keyfile" ]; then
        printf '%s\n' "$0: refusing non-regular DKIM entry '$keyfile'" >&2
        status=1
        continue
    fi

    if [ ! -d "$trashdir" ] && ! mkdir "$trashdir"; then
        fail "could not create DKIM recovery directory '$trashdir'"
    fi
    if ! mv "$keyfile" "$trashdir/"; then
        printf '%s\n' "$0: could not move '$keyfile' to '$trashdir'" >&2
        status=1
    fi
done

if [ "$found" = false ]; then
    printf '%s\n' "$0: no DKIM files exist for '$domain'; nothing to do."
    exit 0
fi

if [ "$status" -ne 0 ]; then
    fail "one or more DKIM files could not be moved"
fi

printf '%s\n' "$0: moved DKIM files for '$domain' to '$trashdir'."
exit 0
