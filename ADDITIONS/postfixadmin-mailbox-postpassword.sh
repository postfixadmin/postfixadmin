#!/bin/bash

# Example script for dovecot mail-crypt-plugin
# https://doc.dovecot.org/configuration_manual/mail_crypt_plugin/

IFS= read -r -d $'\0' OLD_PASSWORD
IFS= read -r -d $'\0' NEW_PASSWORD

# New user
if [ -z "$OLD_PASSWORD" ]; then
    OLD_PASSWORD="$(openssl rand -hex 16)"
    doveadm -o plugin/mail_crypt_private_password="$OLD_PASSWORD" mailbox cryptokey generate -u "$1" -U
fi

# If you're using dovecot >= 2.3.19, try this instead (See: https://github.com/postfixadmin/postfixadmin/issues/646)
# printf "%s\n%s\n" "$OLD_PASSWORD" "$NEW_PASSWORD" "$NEW_PASSWORD" | doveadm mailbox cryptokey password -u "$1" -N -O 

# Password change
printf "%s\n%s\n" "$OLD_PASSWORD" "$NEW_PASSWORD" | doveadm mailbox cryptokey password -u "$1" -N -O ""
