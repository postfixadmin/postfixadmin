#!/bin/sh

# Dovecot quota warning delivery helper.
#
# Called by Dovecot quota warnings, for example:
#   execute quota-warning {
#     args = 95 %{user}
#   }
#
# It delivers a local warning message directly to the user's INBOX using
# doveadm. Review sender address, support text and local doveadm permissions.

set -u

PERCENT=${1:-}
USER=${2:-}
DOVEADM=${DOVEADM:-/usr/bin/doveadm}
FROM_ADDRESS=${FROM_ADDRESS:-postmaster@domain.com}
SERVER_NAME=${SERVER_NAME:-mail.domain.com}
SUPPORT_SIGNATURE=${SUPPORT_SIGNATURE:-Mail Support}

if [ -z "$PERCENT" ] || [ -z "$USER" ]; then
    echo "Usage: $0 <percent|below> <user@example.com>" >&2
    exit 1
fi

case "$PERCENT" in
    below|[0-9]|[0-9][0-9]|100) ;;
    *)
        echo "ERROR: unsafe quota percentage argument: $PERCENT" >&2
        exit 1
        ;;
esac

case "$USER" in
    *@*.*) ;;
    *)
        echo "ERROR: unsafe user argument: $USER" >&2
        exit 1
        ;;
esac

if [ "$PERCENT" = "below" ]; then
    SUBJECT="Storage alert: your mailbox is below the quota limit"
    STATUS_TEXT="Your mailbox is now below the configured quota limit."
else
    SUBJECT="Storage alert: your mailbox is at ${PERCENT}% capacity"
    STATUS_TEXT="Your mailbox (${USER}) has reached ${PERCENT}% of its assigned storage."
fi

cat <<EOF | "$DOVEADM" -f utf-8 submaster deliver -u "$USER" -m INBOX
From: ${FROM_ADDRESS}
To: ${USER}
Subject: ${SUBJECT}
Content-Type: text/plain; charset=UTF-8

Hello,

This is an automatic message from ${SERVER_NAME}.
${STATUS_TEXT}

If the mailbox reaches 100%, it may stop receiving new messages.

Please delete old messages or request a quota increase if needed.

Regards,
${SUPPORT_SIGNATURE}
EOF
