#!/bin/bash

# Example script for Dovecot's mail-crypt plugin.
# Requires Dovecot 2.3.19 or newer for the -O/-N prompt behaviour.
# https://doc.dovecot.org/2.3/configuration_manual/mail_crypt_plugin/

# Dovecot 2.3 setting name. For Dovecot 2.4, use:
# private_password_setting=crypt_user_key_password
private_password_setting=plugin/mail_crypt_private_password

fail()
{
    printf '%s\n' "$0: $*" >&2
    exit 1
}

[[ $# -eq 2 ]] || fail "expected username and domain arguments"
username=$1
domain=$2
[[ -n $username && -n $domain && $username == *@"$domain" ]] || fail "invalid username or domain"

IFS= read -r -d '' old_password || fail "could not read the NUL-terminated old password"
IFS= read -r -d '' new_password || fail "could not read the NUL-terminated new password"
[[ -n $new_password ]] || fail "new password must not be empty"

openssl_path=$(command -v openssl 2>/dev/null) || fail "could not find openssl in PATH"
doveadm_path=$(command -v doveadm 2>/dev/null) || fail "could not find doveadm in PATH"

if [[ -z $old_password ]]; then
    old_password=$("$openssl_path" rand -hex 16) || fail "could not generate a temporary password"
    [[ -n $old_password ]] || fail "openssl returned an empty temporary password"

    if ! "$doveadm_path" -o "$private_password_setting=$old_password" \
        mailbox cryptokey generate -u "$username" -U; then
        fail "could not generate the mailbox cryptokey"
    fi
fi

# Since Dovecot 2.3.19, -O asks for the old password and -N asks for the new
# password twice. Keep passwords on stdin instead of command-line arguments.
if ! printf '%s\n%s\n%s\n' "$old_password" "$new_password" "$new_password" | \
    "$doveadm_path" mailbox cryptokey password -u "$username" -O -N; then
    fail "could not update the mailbox cryptokey password"
fi

exit 0
