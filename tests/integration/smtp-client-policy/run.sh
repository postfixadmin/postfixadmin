#!/bin/bash
#
# Integration test: postfixadmin-smtp-client-policy with a real Postfix.
#
# Starts a throw-away Postfix instance on 127.0.0.1:2525 (own config, queue
# and data directories below a temporary directory; mail is discarded) and a
# copy of this PostfixAdmin checkout with an SQLite or MariaDB database. Client addresses
# are simulated with XCLIENT, so Postfix applies the policy result together
# with the remaining restrictions (permit_mynetworks, reject_unauth_destination).
#
# Not part of the PHPUnit suite. Run as root on a disposable machine/container:
#
#   apt install postfix swaks php-cli php-sqlite3     # postfix may stay stopped
#   sh install.sh                                     # composer dependencies
#   bash tests/integration/smtp-client-policy/run.sh
#
# With a real database server (stopped and started during the test):
#
#   apt install mariadb-server php-mysql
#   PFA_TEST_DB=mariadb bash tests/integration/smtp-client-policy/run.sh
#
# The MariaDB mode creates and drops the database/user pfa_policy_test and
# stops/starts the "mariadb" service - use a disposable machine.
#
# Exit code 0 = all cases passed.

set -u

SRC="$(cd "$(dirname "$0")/../../.." && pwd)"
PORT=2525
TMP="$(mktemp -d /tmp/pfa-smtp-policy-test.XXXXXX)"
PF="$TMP/postfix"
PFA="$TMP/pfa"
DB="$TMP/pfa.sqlite"
FAILED=0
DB_BACKEND="${PFA_TEST_DB:-sqlite}"
MYDB=pfa_policy_test
MYPASS="test-$$-$RANDOM"

case "$DB_BACKEND" in
    sqlite) ;;
    mariadb)
        for cmd in mysql mysqladmin systemctl; do
            command -v "$cmd" >/dev/null || { echo "missing: $cmd (apt install mariadb-server)"; exit 2; }
        done
        php -m | grep -qi pdo_mysql || { echo "missing PHP extension pdo_mysql (apt install php-mysql)"; exit 2; }
        ;;
    *) echo "PFA_TEST_DB must be sqlite or mariadb"; exit 2 ;;
esac

db_up() {
    systemctl start mariadb
    for _ in $(seq 1 30); do mysqladmin ping >/dev/null 2>&1 && return 0; sleep 1; done
    echo "mariadb did not start"; return 1
}
db_down() {
    systemctl stop mariadb
}

for cmd in postfix postconf swaks php; do
    command -v "$cmd" >/dev/null || { echo "missing: $cmd"; exit 2; }
done
[ "$(id -u)" = 0 ] || { echo "must run as root (starts a Postfix instance)"; exit 2; }
[ -f "$SRC/vendor/autoload.php" ] || { echo "run install.sh first"; exit 2; }

cleanup() {
    postfix -c "$PF" stop >/dev/null 2>&1
    if [ "$DB_BACKEND" = mariadb ]; then
        db_up >/dev/null 2>&1
        mysql -e "DROP DATABASE IF EXISTS $MYDB; DROP USER IF EXISTS '$MYDB'@'localhost';" 2>/dev/null
    fi
    [ "${KEEP:-0}" = 1 ] && echo "kept $TMP" || rm -rf "$TMP"
}
trap cleanup EXIT

# --- PostfixAdmin copy + database -------------------------------------------
mkdir -p "$PFA"
cp -a "$SRC/." "$PFA/"
rm -f "$PFA/config.local.php"
if [ "$DB_BACKEND" = mariadb ]; then
    db_up || exit 2
    mysql -e "DROP DATABASE IF EXISTS $MYDB; DROP USER IF EXISTS '$MYDB'@'localhost';
              CREATE DATABASE $MYDB; CREATE USER '$MYDB'@'localhost' IDENTIFIED BY '$MYPASS';
              GRANT ALL ON $MYDB.* TO '$MYDB'@'localhost';" || { echo "creating the test database failed"; exit 2; }
    DB_CONF="\$CONF['database_type'] = 'mysqli';
\$CONF['database_host'] = 'localhost';
\$CONF['database_user'] = '$MYDB';
\$CONF['database_password'] = '$MYPASS';
\$CONF['database_name'] = '$MYDB';"
else
    DB_CONF="\$CONF['database_type'] = 'sqlite';
\$CONF['database_name'] = '$DB';"
fi
cat > "$PFA/config.local.php" <<EOF
<?php
\$CONF['configured'] = true;
$DB_CONF
\$CONF['domain_smtp_client_ips'] = 'YES';
\$CONF['smtp_client_policy'] = array(
    'client_networks' => array('192.0.2.0/24'),
    'cache_ttl' => 0,
);
EOF
[ "$DB_BACKEND" = sqlite ] && touch "$DB"
php "$PFA/tests/integration/smtp-client-policy/fixtures.php" setup >/dev/null || { echo "fixture setup failed"; exit 2; }
# policy runs as nobody (test only)
chmod -R a+rX "$TMP"
chmod a+rw "$TMP"
[ "$DB_BACKEND" = sqlite ] && chmod a+rw "$DB"

# --- Postfix instance ---------------------------------------------------------
mkdir -p "$PF" "$TMP/spool" "$TMP/data"
chown postfix "$TMP/data"
# works without /etc/postfix/main.cf (package installed with "No configuration")
touch "$PF/main.cf" "$PF/master.cf"
DAEMON_DIR="$(postconf -c "$PF" -d -h daemon_directory)"
[ -x "$DAEMON_DIR/smtpd" ] || { echo "postfix daemon directory not found: '$DAEMON_DIR'"; exit 2; }

POLICY_ARGV="/usr/bin/env php $PFA/scripts/postfixadmin-smtp-client-policy.php"

write_master() {
    cat > "$PF/master.cf" <<EOF
127.0.0.1:$PORT inet n - n - - smtpd
pickup    unix  n       -       n       60      1       pickup
cleanup   unix  n       -       n       -       0       cleanup
qmgr      unix  n       -       n       300     1       qmgr
tlsmgr    unix  -       -       n       1000?   1       tlsmgr
rewrite   unix  -       -       n       -       -       trivial-rewrite
bounce    unix  -       -       n       -       0       bounce
defer     unix  -       -       n       -       0       bounce
trace     unix  -       -       n       -       0       bounce
verify    unix  -       -       n       -       1       verify
flush     unix  n       -       n       1000?   0       flush
proxymap  unix  -       -       n       -       -       proxymap
proxywrite unix -       -       n       -       1       proxymap
smtp      unix  -       -       n       -       -       smtp
relay     unix  -       -       n       -       -       smtp
showq     unix  n       -       n       -       -       showq
error     unix  -       -       n       -       -       error
retry     unix  -       -       n       -       -       error
discard   unix  -       -       n       -       -       discard
local     unix  -       n       n       -       -       local
virtual   unix  -       n       n       -       -       virtual
lmtp      unix  -       -       n       -       -       lmtp
anvil     unix  -       -       n       -       1       anvil
scache    unix  -       -       n       -       1       scache
postlog   unix-dgram n  -       n       -       1       postlogd
pfa-smtp-client-policy unix - n n - 0 spawn
  user=nobody argv=$1
EOF
}

cat > "$PF/main.cf" <<EOF
compatibility_level = 3.6
queue_directory = $TMP/spool
data_directory = $TMP/data
meta_directory = /etc/postfix
daemon_directory = $DAEMON_DIR
mail_owner = postfix
setgid_group = postdrop
myhostname = policy-test.invalid
mydestination =
inet_interfaces = 127.0.0.1
inet_protocols = ipv4
maillog_file_prefixes = /var, /dev/stdout, /tmp
maillog_file = $TMP/maillog
default_transport = discard
relay_transport = discard
# 192.0.2.0/24: forwarding hosts (also client_networks of the policy)
# 198.51.100.0/24: other trusted hosts, outside client_networks
mynetworks = 127.0.0.0/8 192.0.2.0/24 198.51.100.0/24
smtpd_authorized_xclient_hosts = 127.0.0.1
smtpd_delay_reject = yes
smtpd_policy_service_timeout = 3s
pfa-smtp-client-policy_time_limit = 3600
# the policy is only queried for the forwarding hosts (restriction class
# via a cidr table), so other clients do not depend on PHP / the database
smtpd_restriction_classes = pfa_smtp_client_policy
pfa_smtp_client_policy = check_policy_service unix:private/pfa-smtp-client-policy
smtpd_sender_restrictions =
    check_client_access cidr:$PF/smtp_client_networks.cidr
    permit_mynetworks
smtpd_relay_restrictions =
    permit_mynetworks
    reject_unauth_destination
EOF
write_master "$POLICY_ARGV"
cat > "$PF/smtp_client_networks.cidr" <<CIDR
# same networks as smtp_client_policy / client_networks
192.0.2.0/24    pfa_smtp_client_policy
CIDR

postfix -c "$PF" check || { echo "postfix check failed"; exit 2; }
postfix -c "$PF" start >/dev/null 2>&1 || { echo "postfix start failed"; cat "$TMP/maillog" 2>/dev/null; exit 2; }
sleep 1

reload() { postfix -c "$PF" reload >/dev/null 2>&1; sleep 1; }

# expect <description> <client> <sender> <expected text in RCPT reply>
# An empty sender means the null sender MAIL FROM:<> (swaks: --from '<>';
# an empty --from would make swaks prompt for the address).
expect() {
    local desc="$1" client="$2" sender="$3" want="$4" out
    [ -n "$sender" ] || sender='<>'
    out="$(timeout 60 swaks --server 127.0.0.1 --port $PORT --xclient-addr "$client" \
        --from "$sender" --to "rcpt@remote.invalid" --quit-after RCPT \
        --timeout 20 </dev/null 2>&1)"
    local reply
    reply="$(printf '%s\n' "$out" | grep -A1 -- '-> RCPT TO' | tail -1)"
    if printf '%s' "$reply" | grep -qF -- "$want"; then
        echo "ok    $desc"
    else
        echo "FAIL  $desc"
        echo "      expected: $want"
        echo "      got:      $reply"
        FAILED=1
    fi
}

fix() { php "$PFA/tests/integration/smtp-client-policy/fixtures.php" "$@" >/dev/null || { echo "fixture command failed: $*"; FAILED=1; }; }

echo "== restriction ordering / allowed and forbidden senders"
expect "assigned host, own domain -> permit_mynetworks"         192.0.2.10   noreply@example.com "250 2.1.5"
expect "assigned host, foreign domain -> policy REJECT wins"    192.0.2.10   noreply@example.org "554 5.7.1"
expect "assigned host, unknown sender address"                  192.0.2.10   nobody@example.com  "554 5.7.1"
expect "null sender from assigned host"                         192.0.2.10   ""                  "554 5.7.1"

echo "== unassigned clients"
expect "unassigned host inside client_networks"                 192.0.2.99   noreply@example.com "554 5.7.1"
expect "trusted host outside policy networks -> not queried"    198.51.100.5 noreply@example.org "250 2.1.5"
expect "untrusted host -> not queried, relay denied"            203.0.113.5  noreply@example.com "554 5.7.1"

echo "== lifecycle (cache_ttl = 0)"
fix set-ips example.org ""
expect "last assignment removed -> rejected, not permitted"     192.0.2.11   noreply@example.org "554 5.7.1"
fix set-ips example.org "192.0.2.11"
expect "assignment restored"                                    192.0.2.11   noreply@example.org "250 2.1.5"
fix set-active example.org 0
expect "only domain deactivated -> rejected"                    192.0.2.11   noreply@example.org "554 5.7.1"
fix set-active example.org 1

echo "== failures ($DB_BACKEND)"
if [ "$DB_BACKEND" = mariadb ]; then db_down; else fix db-break; fi
expect "database unavailable -> temporary failure"              192.0.2.10   noreply@example.com "4.3.0"
expect "database unavailable: other trusted host unaffected"    198.51.100.5 noreply@example.org "250 2.1.5"
expect "database unavailable: untrusted host unaffected"        203.0.113.5  noreply@example.com "Relay access denied"
if [ "$DB_BACKEND" = mariadb ]; then db_up; else fix db-restore; fi
expect "database back -> answered by a new connection"         192.0.2.10   noreply@example.com "250 2.1.5"

write_master "/bin/false"; reload
expect "policy service terminates -> 4xx, not permitted"        192.0.2.10   noreply@example.com "451 4.3.5"
expect "policy service down: other trusted host unaffected"     198.51.100.5 noreply@example.org "250 2.1.5"

write_master "/bin/sleep 30"; reload
expect "policy service timeout -> 4xx, not permitted"           192.0.2.10   noreply@example.com "451 4.3.5"

write_master "$POLICY_ARGV"; reload
expect "policy service restored"                                192.0.2.10   noreply@example.com "250 2.1.5"

echo
if [ $FAILED = 0 ]; then echo "all cases passed"; else echo "FAILED - log: KEEP=1 $0, then see maillog"; fi
exit $FAILED
