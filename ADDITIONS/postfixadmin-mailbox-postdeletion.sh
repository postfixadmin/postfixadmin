#!/bin/sh

# Example script for moving a deleted Maildir to a recovery directory.
# PostfixAdmin passes username and domain as arguments 1 and 2.

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

valid_mailbox_component()
{
    case "$1" in
        ''|.|..|*/*|*\\*|*[![:print:]]*) return 1 ;;
    esac
}

[ "$#" -eq 2 ] || fail "expected username and domain arguments"
username=$1
domain=$2
valid_domain "$domain" || fail "invalid domain '$domain'"

case "$username" in
    *@"$domain") subdir=${username%"@$domain"} ;;
    *) fail "username '$username' does not belong to domain '$domain'" ;;
esac
valid_mailbox_component "$subdir" || fail "unsafe mailbox directory component"

[ -d "$basedir" ] || fail "basedir '$basedir' is not a directory"
[ -d "$trashbase" ] || fail "trashbase '$trashbase' is not a directory"

maildir="${basedir%/}/$domain/$subdir"
if [ ! -e "$maildir" ]; then
    printf '%s\n' "$0: Maildir '$maildir' does not exist; nothing to do."
    exit 0
fi
[ -d "$maildir" ] || fail "'$maildir' is not a directory"

trashparent="${trashbase%/}/$domain"
if [ ! -d "$trashparent" ]; then
    [ ! -e "$trashparent" ] || fail "'$trashparent' exists but is not a directory"
    if ! mkdir -p "$trashparent"; then
        fail "could not create trash directory '$trashparent'"
    fi
fi

timestamp=$(date '+%Y%m%dT%H%M%S') || fail "could not generate a timestamp"
trashdir="${trashparent}/${timestamp}_$$_${subdir}"
[ ! -e "$trashdir" ] || fail "trash destination '$trashdir' already exists"

if ! mv "$maildir" "$trashdir"; then
    fail "could not move '$maildir' to '$trashdir'"
fi

printf '%s\n' "$0: moved '$maildir' to '$trashdir'."
exit 0
