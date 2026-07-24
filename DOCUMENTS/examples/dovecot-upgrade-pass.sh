#!/bin/sh

# Dovecot post-login password hash migration helper.
#
# Purpose:
#   After a successful login, re-hash the user's password with ARGON2ID and
#   update the PostfixAdmin mailbox row. Future logins skip this script once the
#   stored password starts with {ARGON2ID}$argon2id$.
#
# Requirements:
#   - Dovecot passdb query temporarily returns:
#       '%{password}' AS userdb_plain_pass
#   - Dovecot exposes the clear password to this script as PLAIN_PASS.
#   - /etc/dovecot/mysql-postfix.cnf is readable by the script user and contains
#     safe MySQL credentials.
#
# Security:
#   Use only during a controlled migration window. Revert the passdb query after
#   migration and keep auth_verbose_passwords disabled in production.

set -u

MYSQL_CNF=${MYSQL_CNF:-/etc/dovecot/mysql-postfix.cnf}
DB_NAME=${DB_NAME:-postfix}
DOVEADM=${DOVEADM:-/usr/bin/doveadm}
MYSQL=${MYSQL:-/usr/bin/mysql}

log() {
    printf '%s\n' "$*" >&2
}

sql_escape() {
    # Escape single quotes for SQL string literals.
    printf "%s" "$1" | sed "s/'/''/g"
}

finish_login() {
    exec "$@"
}

if [ -z "${USER:-}" ]; then
    log "MIGRATION_SKIP: USER is empty; continuing login."
    finish_login "$@"
fi

if [ -z "${PLAIN_PASS:-}" ]; then
    log "MIGRATION_SKIP: PLAIN_PASS is empty; continuing login."
    finish_login "$@"
fi

if [ ! -f "$MYSQL_CNF" ]; then
    log "MIGRATION_SKIP: missing MySQL defaults file: $MYSQL_CNF"
    finish_login "$@"
fi

USER_SQL=$(sql_escape "$USER")

CURRENT_HASH=$("$MYSQL" --defaults-extra-file="$MYSQL_CNF" -N -B "$DB_NAME" \
    -e "SELECT password FROM mailbox WHERE username='${USER_SQL}' AND active='1' LIMIT 1;" 2>/dev/null)

case "$CURRENT_HASH" in
    "{ARGON2ID}\$argon2id\$"*)
        if [ ${#CURRENT_HASH} -gt 50 ]; then
            log "MIGRATION_SKIP: $USER already uses ARGON2ID."
            finish_login "$@"
        fi
        ;;
esac

NEW_HASH=$("$DOVEADM" pw -s ARGON2ID -p "$PLAIN_PASS" 2>/dev/null)

case "$NEW_HASH" in
    "{ARGON2ID}\$argon2id\$"*) ;;
    *)
        log "MIGRATION_ERROR: doveadm returned an invalid ARGON2ID hash; database not updated."
        finish_login "$@"
        ;;
esac

if [ ${#NEW_HASH} -lt 50 ]; then
    log "MIGRATION_ERROR: generated hash is unexpectedly short; database not updated."
    finish_login "$@"
fi

NEW_HASH_SQL=$(sql_escape "$NEW_HASH")

if "$MYSQL" --defaults-extra-file="$MYSQL_CNF" "$DB_NAME" \
    -e "UPDATE mailbox SET password='${NEW_HASH_SQL}' WHERE username='${USER_SQL}' AND active='1';" >/dev/null 2>&1; then
    log "MIGRATION_SUCCESS: updated $USER to ARGON2ID."
else
    log "MIGRATION_ERROR: MySQL update failed for $USER."
fi

finish_login "$@"
