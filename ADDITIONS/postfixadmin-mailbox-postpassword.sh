#!/bin/bash

# Example script for dovecot mail-crypt-plugin
# https://doc.dovecot.org/configuration_manual/mail_crypt_plugin/

# New user
if [ -z "$3" ]; then
    doveadm -o plugin/mail_crypt_private_password="$4" mailbox cryptokey generate -u "$1" -U
    exit 0
fi

# Password change
doveadm mailbox cryptokey password -u "$1" -o "$3" -n "$4"
