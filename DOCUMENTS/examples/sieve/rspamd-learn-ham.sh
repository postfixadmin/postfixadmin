#!/bin/sh

# Rspamd learning helper for Dovecot Sieve pipe.
# Dovecot passes the message through stdin.

set -u

RSPAMC=${RSPAMC:-/usr/bin/rspamc}

exec "$RSPAMC" learn_ham
