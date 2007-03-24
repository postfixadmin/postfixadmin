#!/bin/sh

# Example script for removing a Maildir from a Courier-IMAP virtual mail
# hierarchy.

# The script looks at arguments 1 and 2, assuming that they 
# indicate username and domain, respectively.

# The script will not actually delete the maildir. I moves it
# to a special directory which may once in a while be cleaned up
# by the system administrator.

# This script should be run as the user which owns the maildirs. If 
# the script is actually run by the apache user (e.g. through PHP),
# then you could use "sudo" to grant apache the rights to run
# this script as the relevant user.
# Assume this script has been saved as
# /usr/local/bin/postfixadmin-mailbox-postdeletion.sh and has been
# made executable. Now, an example /etc/sudoers line:
# apache ALL=(courier) NOPASSWD: /usr/local/bin/postfixadmin-mailbox-postdeletion.sh
# The line states that the apache user may run the script as the
# user "courier" without providing a password.


# Change this to where you keep your virtual mail users' maildirs.
basedir=/var/spool/maildirs

# Change this to where you would like deleted maildirs to reside.
trashbase=/var/spool/deleted-maildirs


if [ ! -e "$trashbase" ]; then
    echo "trashbase '$trashbase' does not exist; bailing out."
    exit 1
fi

if [ `echo $1 | fgrep '..'` ]; then
    echo "First argument contained a double-dot sequence; bailing out."
    exit 1
fi
if [ `echo $2 | fgrep '..'` ]; then
    echo "First argument contained a double-dot sequence; bailing out."
    exit 1
fi

subdir=`echo "$1" | sed 's/@.*//'`

maildir="${basedir}/$2/${subdir}"
trashdir="${trashbase}/$2/`date +%F_%T`_${subdir}"

parent=`dirname "$trashdir"`
if [ ! -d "$parent" ]; then
    if [ -e "$parent" ]; then
        echo "Strainge - directory '$parent' exists, but is not a directory."
        echo "Bailing out."
        exit 1
    else
        mkdir -p "$parent"
        if [ $? -ne 0 ]; then
            echo "mkdir -p '$parent' returned non-zero; bailing out."
            exit 1
        fi
    fi
fi

if [ ! -e "$maildir" ]; then
    echo "maildir '$maildir' does not exist; nothing to do."
    exit 1
fi
if [ -e "$trashdir" ]; then
    echo "trashdir '$trashdir' already exists; bailing out."
    exit 1
fi

mv $maildir $trashdir

exit $?
