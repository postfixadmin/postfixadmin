#!/bin/sh

# Example script for adding a Maildir to a Courier-IMAP virtual mail
# hierarchy.

# The script only looks at argument 3, assuming that it 
# indicates the relative name of a maildir, such as
# "somedomain.com/peter/".

# This script should be run as the user which owns the maildirs. If 
# the script is actually run by the apache user (e.g. through PHP),
# then you could use "sudo" to grant apache the rights to run
# this script as the relevant user.
# Assume this script has been saved as
# /usr/local/bin/postfixadmin-mailbox-postcreation.sh and has been
# made executable. Now, an example /etc/sudoers line:
# apache ALL=(courier) NOPASSWD: /usr/local/bin/postfixadmin-mailbox-postcreation.sh
# The line states that the apache user may run the script as the
# user "courier" without providing a password.


# Change this to where you keep your virtual mail users' maildirs.
basedir=/var/spool/maildirs

if [ ! -e "$basedir" ]; then
    echo "basedir '$basedir' does not exist; bailing out."
    exit 1
fi

if [ `echo $3 | fgrep '..'` ]; then
    echo "An argument contained a double-dot sequence; bailing out."
    exit 1
fi

maildir="${basedir}/$3"
parent=`dirname "$maildir"`
if [ ! -d "$parent" ]; then
    if [ -e "$parent" ]; then
        echo "Strainge - directory '$parent' exists, but is not a directory."
        echo "Bailing out."
        exit 1
    else
        mkdir -p "${parent}"
        if [ $? -ne 0 ]; then
            echo "mkdir -p '$parent' returned non-zero; bailing out."
            exit 1
        fi
    fi
fi

if [ -e "$maildir" ]; then
    echo "Directory '$maildir' already exists! Bailing out"
    exit 1
fi

maildirmake "$maildir"
if [ ! -d "$maildir" ]; then
    echo "maildirmake didn't produce a directory"
    echo "Bailing out."
    exit 1
fi

exit 0
