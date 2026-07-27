#!/bin/sh

# Example script for moving a deleted Maildir domain to a recovery directory.
# PostfixAdmin passes the domain as argument 1.

# Run this script as the user that owns the Maildirs. If the web server needs
# sudo, restrict the sudoers rule to that user and this exact script. Do not
# grant a generic mv command.

# Change these paths to match the virtual mail layout.
basedir=/var/spool/maildirs
trashbase=/var/spool/deleted-maildirs

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

[ -d "$basedir" ] || fail "basedir '$basedir' is not a directory"
[ -d "$trashbase" ] || fail "trashbase '$trashbase' is not a directory"

domaindir="${basedir%/}/$domain"
[ "$domaindir" != "${trashbase%/}" ] || fail "refusing to move trashbase itself"

if [ ! -e "$domaindir" ]; then
    printf '%s\n' "$0: directory '$domaindir' does not exist; nothing to do."
    exit 0
fi
[ -d "$domaindir" ] || fail "'$domaindir' is not a directory"

timestamp=$(date '+%Y%m%dT%H%M%S') || fail "could not generate a timestamp"
trashdir="${trashbase%/}/${timestamp}_$$_${domain}"
[ ! -e "$trashdir" ] || fail "trash destination '$trashdir' already exists"

if ! mv "$domaindir" "$trashdir"; then
    fail "could not move '$domaindir' to '$trashdir'"
fi

printf '%s\n' "$0: moved '$domaindir' to '$trashdir'."
exit 0
