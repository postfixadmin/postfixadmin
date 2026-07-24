#!/bin/sh

# Example script for adding a Maildir to a Courier-IMAP virtual mail
# hierarchy. PostfixAdmin passes username, domain, Maildir and quota as
# arguments 1 through 4. This example uses only the relative Maildir path.

# Run this script as the user that owns the Maildirs. If the web server needs
# sudo, restrict the sudoers rule to that user and this exact script.

# Change this to where you keep your virtual mail users' Maildirs.
basedir=/var/spool/maildirs

fail()
{
    printf '%s\n' "$0: $*" >&2
    exit 1
}

valid_relative_maildir()
{
    candidate=${1%/}

    [ -n "$candidate" ] || return 1
    case "$candidate" in
        /*|*\\*|*//*|*[![:print:]]*) return 1 ;;
    esac

    remainder=$candidate
    while :; do
        case "$remainder" in
            */*)
                component=${remainder%%/*}
                remainder=${remainder#*/}
                ;;
            *)
                component=$remainder
                remainder=
                ;;
        esac

        case "$component" in
            ''|.|..) return 1 ;;
        esac
        [ -n "$remainder" ] || break
    done
}

[ "$#" -eq 4 ] || fail "expected username, domain, Maildir and quota arguments"
relative_maildir=${3%/}
valid_relative_maildir "$relative_maildir" || fail "invalid relative Maildir '$3'"

[ -d "$basedir" ] || fail "basedir '$basedir' is not a directory"

maildir="${basedir%/}/$relative_maildir"
parent=$(dirname "$maildir") || fail "could not determine the parent directory"

if [ ! -d "$parent" ]; then
    [ ! -e "$parent" ] || fail "'$parent' exists but is not a directory"
    if ! mkdir -p "$parent"; then
        fail "could not create parent directory '$parent'"
    fi
fi

[ ! -e "$maildir" ] || fail "Maildir '$maildir' already exists"

if maildirmake_path=$(command -v maildirmake 2>/dev/null); then
    :
elif maildirmake_path=$(command -v courier-maildirmake 2>/dev/null); then
    :
else
    fail "could not find maildirmake or courier-maildirmake in PATH"
fi

if ! "$maildirmake_path" "$maildir"; then
    fail "maildirmake failed for '$maildir'"
fi
[ -d "$maildir" ] || fail "maildirmake did not create '$maildir'"

exit 0
