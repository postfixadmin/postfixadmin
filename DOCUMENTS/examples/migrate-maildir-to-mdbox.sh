#!/bin/sh

# Gradual per-user Maildir to mdbox migration helper for Dovecot 2.4.
#
# Usage:
#   migrate-maildir-to-mdbox.sh user@example.com
#   migrate-maildir-to-mdbox.sh @example.com
#   migrate-maildir-to-mdbox.sh ALL
#   migrate-maildir-to-mdbox.sh user@example.com --real
#
# The default mode is dry-run. Use --real only after testing, backing up the
# database and mail storage, and reviewing all paths below.

set -u

MYSQL=${MYSQL:-/usr/bin/mysql}
DOVEADM=${DOVEADM:-/usr/bin/doveadm}
MYSQL_CNF=${MYSQL_CNF:-/etc/dovecot/mysql-postfix.cnf}
DB_NAME=${DB_NAME:-postfix}

VMAIL_ROOT=${VMAIL_ROOT:-/var/vmail}
MIGRATION_ROOT=${MIGRATION_ROOT:-/var/vmail/mdbox}
BACKUP_ROOT=${BACKUP_ROOT:-/var/vmail/MIGRATION_TMP}
VMAIL_USER=${VMAIL_USER:-vmail}
VMAIL_GROUP=${VMAIL_GROUP:-mail}

PARAM=${1:-}
OPTION=${2:-}
MODE=DRY_RUN

if [ -z "$PARAM" ]; then
    echo "Usage: $0 [user@example.com | @example.com | ALL] [--real]" >&2
    exit 1
fi

if [ "$OPTION" = "--real" ]; then
    MODE=REAL
elif [ -n "$OPTION" ]; then
    echo "ERROR: unsupported option: $OPTION" >&2
    exit 1
fi

log() {
    printf '%s\n' "$*"
}

err() {
    printf '%s\n' "$*" >&2
}

sql_escape() {
    printf "%s" "$1" | sed "s/'/''/g"
}

is_safe_email() {
    printf "%s" "$1" | grep -Eq '^[A-Za-z0-9._%+~-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$'
}

is_safe_domain() {
    printf "%s" "$1" | grep -Eq '^[A-Za-z0-9.-]+\.[A-Za-z]{2,}$'
}

require_safe_child_path() {
    child=$1
    parent=$2
    case "$child" in
        "$parent"/*) return 0 ;;
        *)
            err "SAFETY_ERROR: path '$child' is outside '$parent'"
            return 1
            ;;
    esac
}

mysql_exec() {
    "$MYSQL" --defaults-extra-file="$MYSQL_CNF" "$DB_NAME" -e "$1"
}

mysql_scalar() {
    "$MYSQL" --defaults-extra-file="$MYSQL_CNF" -N -B "$DB_NAME" -e "$1"
}

if [ ! -f "$MYSQL_CNF" ]; then
    err "ERROR: missing MySQL defaults file: $MYSQL_CNF"
    exit 1
fi

if ! mysql_exec "SELECT 1;" >/dev/null 2>&1; then
    err "ERROR: MySQL connection test failed."
    exit 1
fi

log "=== Selected mode: $MODE ==="
log "MySQL connection: OK"

if [ "$MODE" = "REAL" ]; then
    mkdir -p "$MIGRATION_ROOT" "$BACKUP_ROOT"
    chown "$VMAIL_USER:$VMAIL_GROUP" "$MIGRATION_ROOT" "$BACKUP_ROOT"
    chmod 750 "$MIGRATION_ROOT" "$BACKUP_ROOT"
fi

migrate_user() {
    mailbox=$1

    if ! is_safe_email "$mailbox"; then
        err "SKIP: '$mailbox' does not look like a safe email address."
        return 1
    fi

    mailbox_sql=$(sql_escape "$mailbox")
    current_format=$(mysql_scalar "SELECT mail_format FROM mailbox WHERE username='${mailbox_sql}' LIMIT 1;" 2>/dev/null)

    if [ "$current_format" = "mdbox" ]; then
        log "SKIP: $mailbox is already mdbox."
        return 0
    fi

    if [ "$MODE" = "DRY_RUN" ]; then
        log "[DRY-RUN] Would migrate: $mailbox (current format: ${current_format:-unknown})"
        return 0
    fi

    domain=${mailbox#*@}
    account=${mailbox%@*}

    base_dir="$VMAIL_ROOT/$domain/$account"
    tmp_mdbox="$MIGRATION_ROOT/$domain/$account"
    backup_domain="$BACKUP_ROOT/$domain"
    timestamp=$(date +%Y%m%d_%H%M%S)
    backup_old="$backup_domain/${account}_MAILDIR_OLD_${timestamp}"

    require_safe_child_path "$base_dir" "$VMAIL_ROOT" || return 1
    require_safe_child_path "$tmp_mdbox" "$MIGRATION_ROOT" || return 1
    require_safe_child_path "$backup_old" "$BACKUP_ROOT" || return 1

    log ">>> [REAL] Processing: $mailbox"

    if [ ! -d "$base_dir" ]; then
        err "SAFETY_ERROR: source Maildir does not exist: $base_dir"
        return 1
    fi

    "$DOVEADM" auth cache flush "$mailbox" >/dev/null 2>&1 || true

    rm -rf "$tmp_mdbox"
    mkdir -p "$MIGRATION_ROOT/$domain" "$backup_domain"
    chown -R "$VMAIL_USER:$VMAIL_GROUP" "$MIGRATION_ROOT/$domain" "$backup_domain"

    log "[1/5] Syncing mailbox to temporary mdbox location..."
    if ! "$DOVEADM" sync -u "$mailbox" "mdbox:$tmp_mdbox"; then
        err "SAFETY_ERROR: initial doveadm sync failed."
        rm -rf "$tmp_mdbox"
        return 1
    fi

    log "[2/5] Disabling account and closing active sessions..."
    mysql_exec "UPDATE mailbox SET active='0' WHERE username='${mailbox_sql}';"
    "$DOVEADM" kick "$mailbox" >/dev/null 2>&1 || true
    "$DOVEADM" sync -u "$mailbox" "mdbox:$tmp_mdbox" >/dev/null 2>&1 || true

    if [ ! -d "$tmp_mdbox/storage" ] || [ ! -d "$tmp_mdbox/mailboxes" ]; then
        err "SAFETY_ERROR: temporary mdbox structure is incomplete."
        mysql_exec "UPDATE mailbox SET active='1' WHERE username='${mailbox_sql}';"
        rm -rf "$tmp_mdbox"
        return 1
    fi

    log "[3/5] Backing up old Maildir and installing mdbox..."
    if ! mv "$base_dir" "$backup_old"; then
        err "SAFETY_ERROR: could not move original Maildir to backup."
        mysql_exec "UPDATE mailbox SET active='1' WHERE username='${mailbox_sql}';"
        return 1
    fi

    if ! mv "$tmp_mdbox" "$base_dir"; then
        err "ERROR: could not install mdbox. Restoring original Maildir..."
        mv "$backup_old" "$base_dir"
        mysql_exec "UPDATE mailbox SET active='1' WHERE username='${mailbox_sql}';"
        return 1
    fi

    chown -R "$VMAIL_USER:$VMAIL_GROUP" "$base_dir"

    log "[4/5] Updating mailbox format and re-enabling account..."
    mysql_exec "UPDATE mailbox SET mail_format='mdbox', active='1' WHERE username='${mailbox_sql}';"
    "$DOVEADM" auth cache flush "$mailbox" >/dev/null 2>&1 || true

    log "[5/5] Rebuilding FTS indexes..."
    "$DOVEADM" fts rescan -u "$mailbox" || true
    "$DOVEADM" index -u "$mailbox" "*" || true

    rm -rf "$base_dir/cur" "$base_dir/new" "$base_dir/tmp" "$base_dir/maildirfolder"

    log "SUCCESS: $mailbox migrated to mdbox. Old Maildir backup: $backup_old"
    log "------------------------------------------------"
}

case "$PARAM" in
    ALL)
        log "Selecting active accounts: all mailboxes"
        accounts=$(mysql_scalar "SELECT username FROM mailbox WHERE active='1';")
        ;;
    @*)
        domain=${PARAM#@}
        if ! is_safe_domain "$domain"; then
            err "ERROR: unsafe domain argument: $PARAM"
            exit 1
        fi
        domain_sql=$(sql_escape "$domain")
        log "Selecting active accounts for domain: @$domain"
        accounts=$(mysql_scalar "SELECT username FROM mailbox WHERE active='1' AND username LIKE '%@${domain_sql}';")
        ;;
    *)
        if ! is_safe_email "$PARAM"; then
            err "ERROR: unsafe mailbox argument: $PARAM"
            exit 1
        fi
        log "Selecting one mailbox: $PARAM"
        accounts=$PARAM
        ;;
esac

if [ -z "${accounts:-}" ]; then
    err "No active accounts found."
    exit 1
fi

for account in $accounts; do
    migrate_user "$account"
done

log "=== Process finished in $MODE mode ==="
