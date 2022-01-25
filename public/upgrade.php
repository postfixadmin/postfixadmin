<?php

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */

# Note: run with upgrade.php?debug=1 to see all SQL error messages

require_once('common.php');

if (empty($CONF)) {
    die("config.inc.php seems invalid");
}

/**
 * Use this to check whether an object (table, index etc) exists within a
 * PostgreSQL database.
 * @param string the object name
 * @return boolean true if it exists
 */
function _pgsql_object_exists($name) {
    $sql = "select relname from pg_class where relname = '$name'";
    $r = db_query_one($sql);
    return !empty($r);
}

/**
 * @param string $table
 * @param string $field
 * @return bool
 */
function _pgsql_field_exists($table, $field) {
    # $table = table_by_key($table); # _pgsql_field_exists is always called with the expanded table name - don't expand it twice
    $sql = '
    SELECT
        a.attname,
        pg_catalog.format_type(a.atttypid, a.atttypmod) AS "Datatype"
    FROM
        pg_catalog.pg_attribute a
    WHERE
        a.attnum > 0
        AND NOT a.attisdropped
        AND a.attrelid = (
            SELECT c.oid
            FROM pg_catalog.pg_class c
                LEFT JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
            WHERE c.relname ~ ' . "'^($table)\$' 
                AND pg_catalog.pg_table_is_visible(c.oid)
        )
        AND a.attname = '$field' ";
    $r = db_query_all($sql);

    return !empty($r);
}

function _mysql_field_exists($table, $field) {
    # $table = table_by_key($table); # _mysql_field_exists is always called with the expanded table name - don't expand it twice
    $sql = "SHOW COLUMNS FROM $table LIKE ?";
    $r = db_query_all($sql, array( $field));

    return !empty($r);
}

function _sqlite_field_exists($table, $field) {
    $sql = "PRAGMA table_info($table)";
    $r = db_query_all($sql);

    foreach ($r as $row) {
        if ($row['name'] == $field) {
            return true;
        }
    }
    return false;
}

function _db_field_exists($table, $field) {
    global $CONF;
    if ($CONF['database_type'] == 'pgsql') {
        return _pgsql_field_exists($table, $field);
    } elseif ($CONF['database_type'] == 'sqlite') {
        return _sqlite_field_exists($table, $field);
    } else {
        return _mysql_field_exists($table, $field);
    }
}
function _upgrade_filter_function($name) {
    return preg_match('/upgrade_[\d]+(_mysql|_pgsql|_sqlite|_mysql_pgsql)?$/', $name) == 1;
}

function _db_add_field($table, $field, $fieldtype, $after = '') {
    global $CONF;

    $query = "ALTER TABLE " . table_by_key($table) . " ADD COLUMN $field $fieldtype";
    if ($CONF['database_type'] == 'mysql' && !empty($after)) {
        $query .= " AFTER $after "; # PgSQL does not support to specify where to add the column, MySQL does
    }

    if (! _db_field_exists(table_by_key($table), $field)) {
        db_query_parsed($query);
    } else {
        printdebug("field already exists: $table.$field");
    }
}

function echo_out($text) {
    if (defined('PHPUNIT_TEST')) {
        //error_log("" . $text);
    } else {
        echo $text . "\n";
    }
}

function printdebug($text) {
    if (safeget('debug') != "") {
        echo_out("<p style='color:#999'>$text</p>");
    }
}

$table = table_by_key('config');
if ($CONF['database_type'] == 'pgsql') {
    // check if table already exists, if so, don't recreate it
    if (!_pgsql_object_exists($table)) {
        $pgsql = "
            CREATE TABLE  $table ( 
                    id SERIAL,
                    name VARCHAR(20) NOT NULL UNIQUE,
                    value VARCHAR(20) NOT NULL,
                    PRIMARY KEY(id)
                    )";
        db_query_parsed($pgsql);
    }
} elseif (db_sqlite()) {
    $enc = 'PRAGMA encoding = "UTF-8"';
    db_query_parsed($enc);
    $sql = "
        CREATE TABLE {IF_NOT_EXISTS} $table (
        `id` {AUTOINCREMENT},
        `name` TEXT NOT NULL UNIQUE DEFAULT '',
        `value` TEXT NOT NULL DEFAULT ''
        )
    ";
    db_query_parsed($sql);
} else {
    $mysql = "
        CREATE TABLE {IF_NOT_EXISTS} $table (
        `id` {AUTOINCREMENT} {PRIMARY},
        `name`  VARCHAR(20) {LATIN1} NOT NULL DEFAULT '',
        `value` VARCHAR(20) {LATIN1} NOT NULL DEFAULT '',
        UNIQUE name ( `name` )
        )
    ";
    db_query_parsed($mysql, 0, " COMMENT = 'PostfixAdmin settings'");
}

$version = check_db_version(false);
_do_upgrade($version);

function _do_upgrade($current_version) {
    global $CONF;

    $target_version = 0;
    // Rather than being bound to an svn revision number, just look for the largest function name that matches upgrade_\d+...
    // $target_version = preg_replace('/[^0-9]/', '', '$Revision$');
    $funclist = get_defined_functions();
    $our_upgrade_functions = array_filter($funclist['user'], '_upgrade_filter_function');
    foreach ($our_upgrade_functions as $function_name) {
        $bits = explode("_", $function_name);
        $function_number = $bits[1];
        if (is_numeric($function_number)) {
            $target_version = max($target_version, $function_number);
        }
    }

    if ($current_version >= $target_version) {
        # already up to date
        echo_out("<p>Database is up to date: $current_version/$target_version </p>");
        return true;
    }

    echo_out("<p>Updating database:</p><p>- old version: $current_version; target version: $target_version</p>\n");
    echo_out("<div style='color:#999'>&nbsp;&nbsp;(If the update doesn't work, run setup.php?debug=1 to see the detailed error messages and SQL queries.)</div>");

    if (db_sqlite() && $current_version < 1824) {
        // Fast forward to the first revision supporting SQLite
        $current_version = 1823;
    }

    for ($i = $current_version +1; $i <= $target_version; $i++) {
        $function = "upgrade_$i";
        $function_mysql_pgsql = $function . "_mysql_pgsql";
        $function_mysql = $function . "_mysql";
        $function_pgsql = $function . "_pgsql";
        $function_sqlite = $function . "_sqlite";

        if (function_exists($function)) {
            echo_out("<p>updating to version $i (all databases)...");
            $function();
            echo_out(" &nbsp; done");
        }
        if ($CONF['database_type'] == 'mysql' || $CONF['database_type'] == 'mysqli' || $CONF['database_type'] == 'pgsql') {
            if (function_exists($function_mysql_pgsql)) {
                echo_out("<p>updating to version $i (MySQL and PgSQL)...");
                $function_mysql_pgsql();
                echo_out(" &nbsp; done");
            }
        }
        if ($CONF['database_type'] == 'mysql' || $CONF['database_type'] == 'mysqli') {
            if (function_exists($function_mysql)) {
                echo_out("<p>updating to version $i (MySQL)...");
                $function_mysql();
                echo_out(" &nbsp; done");
            }
        } elseif (db_sqlite()) {
            if (function_exists($function_sqlite)) {
                echo_out("<p>updating to version $i (SQLite)...");
                $function_sqlite();
                echo_out(" &nbsp; done");
            }
        } elseif ($CONF['database_type'] == 'pgsql') {
            if (function_exists($function_pgsql)) {
                echo_out("<p>updating to version $i (PgSQL)...");
                $function_pgsql();
                echo_out(" &nbsp; done");
            }
        }
        // Update config table so we don't run the same query twice in the future.
        $table = table_by_key('config');
        $sql = "UPDATE $table SET value = :value WHERE name = 'version'";
        db_execute($sql, array('value' => $i));
    };
}

/**
 * Replaces database specific parts in a query
 * @param string sql query with placeholders
 * @param int (optional) whether errors should be ignored (0=false)
 * @param string (optional) MySQL specific code to attach, useful for COMMENT= on CREATE TABLE
 * @return void
 */

function db_query_parsed($sql, $ignore_errors = 0, $attach_mysql = "") {
    global $CONF;

    if (db_mysql()) {
        $replace = array(
                '{AUTOINCREMENT}'   => 'int(11) not null auto_increment',
                '{PRIMARY}'         => 'primary key',
                '{UNSIGNED}'        => 'unsigned'  ,
                '{FULLTEXT}'        => 'FULLTEXT',
                '{BOOLEAN}'         => "tinyint(1) NOT NULL DEFAULT '" . db_get_boolean(false) . "'",
                '{UTF-8}'           => '/*!40100 CHARACTER SET utf8 */',
                '{LATIN1}'          => '/*!40100 CHARACTER SET latin1 COLLATE latin1_general_ci */',
                '{IF_NOT_EXISTS}'   => 'IF NOT EXISTS',
                '{RENAME_COLUMN}'   => 'CHANGE COLUMN',
                '{MYISAM}'          => '',
                '{INNODB}'          => 'ENGINE=InnoDB',
                '{INT}'             => 'integer NOT NULL DEFAULT 0',
                '{BIGINT}'          => 'bigint NOT NULL DEFAULT 0',
                '{DATETIME}'        => "datetime NOT NULL default '2000-01-01 00:00:00'", # different from {DATE} only for MySQL
                '{DATE}'            => "timestamp NOT NULL default '2000-01-01'", # MySQL needs a sane default (no default is interpreted as CURRENT_TIMESTAMP, which is ...
                '{DATEFUTURE}'      => "timestamp NOT NULL default '2038-01-18'", # different default timestamp for vacation.activeuntil
                '{DATECURRENT}'     => 'timestamp NOT NULL default CURRENT_TIMESTAMP', # only allowed once per table in MySQL
                '{COLLATE}'         => "CHARACTER SET latin1 COLLATE latin1_general_ci", # just incase someone has a unicode collation set.

        );
        $sql = "$sql $attach_mysql";
    } elseif (db_sqlite()) {
        $replace = array(
                '{AUTOINCREMENT}'   => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL',
                '{PRIMARY}'         => 'PRIMARY KEY',
                '{UNSIGNED}'        => 'unsigned',
                '{FULLTEXT}'        => 'text',
                '{BOOLEAN}'         => "tinyint(1) NOT NULL DEFAULT '" . db_get_boolean(false) . "'",
                '{BOOLEAN_TRUE}'    => "tinyint(1) NOT NULL DEFAULT '" . db_get_boolean(true) . "'",
                '{UTF-8}'           => '',
                '{LATIN1}'          => '',
                '{IF_NOT_EXISTS}'   => 'IF NOT EXISTS',
                '{RENAME_COLUMN}'   => 'CHANGE COLUMN',
                '{MYISAM}'          => '',
                '{INNODB}'          => '',
                '{INT}'             => 'int(11) NOT NULL DEFAULT 0',
                '{BIGINT}'          => 'bigint(20) NOT NULL DEFAULT 0',
                '{DATETIME}'        => "datetime NOT NULL default '2000-01-01'",
                '{DATE}'            => "datetime NOT NULL default '2000-01-01'",
                '{DATEFUTURE}'      => "datetime NOT NULL default '2038-01-18'", # different default timestamp for vacation.activeuntil
                '{DATECURRENT}'     => 'datetime NOT NULL default CURRENT_TIMESTAMP',
                '{COLLATE}'         => ''
        );
    } elseif ($CONF['database_type'] == 'pgsql') {
        $replace = array(
                '{AUTOINCREMENT}'   => 'SERIAL',
                '{PRIMARY}'         => 'primary key',
                '{UNSIGNED}'        => '',
                '{FULLTEXT}'        => '',
                '{BOOLEAN}'         => "BOOLEAN NOT NULL DEFAULT '" . db_get_boolean(false) . "'",
                '{UTF-8}'           => '', # UTF-8 is simply ignored.
                '{LATIN1}'          => '', # same for latin1
                '{IF_NOT_EXISTS}'   => '', # does not work with PgSQL
                '{RENAME_COLUMN}'   => 'ALTER COLUMN', # PgSQL : ALTER TABLE x RENAME x TO y
                '{MYISAM}'          => '',
                '{INNODB}'          => '',
                '{INT}'             => 'integer NOT NULL DEFAULT 0',
                '{BIGINT}'          => 'bigint NOT NULL DEFAULT 0',
                'int(1)'            => 'int',
                'int(10)'           => 'int',
                'int(11)'           => 'int',
                'int(4)'            => 'int',
                '{DATETIME}'        => "timestamp with time zone default '2000-01-01'", # stay in sync with MySQL
                '{DATE}'            => "timestamp with time zone default '2000-01-01'", # stay in sync with MySQL
                '{DATEFUTURE}'      => "timestamp with time zone default '2038-01-18'", # stay in sync with MySQL
                '{DATECURRENT}'     => 'timestamp with time zone default now()',
                '{COLLATE}'         => '',
        );
    } else {
        echo_out("Sorry, unsupported database type " . $CONF['database_type']);
        exit;
    }

    $replace['{BOOL_TRUE}'] = db_get_boolean(true);
    $replace['{BOOL_FALSE}'] = db_get_boolean(false);

    $query = trim(str_replace(array_keys($replace), $replace, $sql));

    $debug = safeget('debug', '') != '';

    if ($debug) {
        printdebug($query);
    }

    try {
        $result = db_execute($query, array(), true);
    } catch (PDOException $e) {
        error_log("Exception running PostfixAdmin query: $query " . $e);
        if ($debug) {
            echo_out("<div style='color:#f00'>" . $e->getMessage() . "</div>");
        }

        throw new \Exception("Postfixadmin DB update failed. Please check your PHP error_log");
    }
}
/**
 * @param string $table
 * @param string $index
 * @return string
 */
function _drop_index($table, $index) {
    global $CONF;
    $table = table_by_key($table);

    if ($CONF['database_type'] == 'mysql' || $CONF['database_type'] == 'mysqli') {
        return "ALTER TABLE $table DROP INDEX $index";
    } elseif ($CONF['database_type'] == 'pgsql' || db_sqlite()) {
        return "DROP INDEX $index"; # Index names are unique with a DB for PostgreSQL
    } else {
        echo_out("Sorry, unsupported database type " . $CONF['database_type']);
        exit;
    }
}

/**
 * @return string
 * @param string $table
 * @param string $indexname
 * @param string $fieldlist
 */
function _add_index($table, $indexname, $fieldlist) {
    global $CONF;
    $table = table_by_key($table);

    if ($CONF['database_type'] == 'mysql' || $CONF['database_type'] == 'mysqli') {
        $fieldlist = str_replace(',', '`,`', $fieldlist); # fix quoting if index contains multiple fields
        return "ALTER TABLE $table ADD INDEX `$indexname` ( `$fieldlist` )";
    } elseif ($CONF['database_type'] == 'pgsql') {
        $pgindexname = $table . "_" . $indexname . '_idx';
        return "CREATE INDEX $pgindexname ON $table($fieldlist);"; # Index names are unique with a DB for PostgreSQL
    } else {
        echo_out("Sorry, unsupported database type " . $CONF['database_type']);
        exit;
    }
}

/**
 * @return void
 */
function upgrade_1_mysql() {
    #
    # creating the tables in this very old layout (pre 2.1) causes trouble if the MySQL charset is not latin1 (multibyte vs. index length)
    # therefore:

    return; # <-- skip running this function at all.

    # (remove the above "return" if you really want to start with a pre-2.1 database layout)

    // CREATE MYSQL DATABASE TABLES.
    $admin = table_by_key('admin');
    $alias = table_by_key('alias');
    $domain = table_by_key('domain');
    $domain_admins = table_by_key('domain_admins');
    $log = table_by_key('log');
    $mailbox = table_by_key('mailbox');
    $vacation = table_by_key('vacation');

    $sql = array();
    $sql[] = "
    CREATE TABLE {IF_NOT_EXISTS} $admin (
      `username` varchar(255) NOT NULL default '',
      `password` varchar(255) NOT NULL default '',
      `created` {DATETIME},
      `modified` {DATETIME},
      `active` tinyint(1) NOT NULL default '1',
      PRIMARY KEY  (`username`)
  ) {COLLATE} COMMENT='Postfix Admin - Virtual Admins';";

    $sql[] = "
    CREATE TABLE {IF_NOT_EXISTS} $alias (
      `address` varchar(255) NOT NULL default '',
      `goto` text NOT NULL,
      `domain` varchar(255) NOT NULL default '',
      `created` {DATETIME},
      `modified` {DATETIME},
      `active` tinyint(1) NOT NULL default '1',
      PRIMARY KEY  (`address`)
    ) {COLLATE} COMMENT='Postfix Admin - Virtual Aliases'; ";

    $sql[] = "
    CREATE TABLE {IF_NOT_EXISTS} $domain (
      `domain` varchar(255) NOT NULL default '',
      `description` varchar(255) NOT NULL default '',
      `aliases` int(10) NOT NULL default '0',
      `mailboxes` int(10) NOT NULL default '0',
      `maxquota` bigint(20) NOT NULL default '0',
      `quota` bigint(20) NOT NULL default '0',
      `transport` varchar(255) default NULL,
      `backupmx` tinyint(1) NOT NULL default '0',
      `created` {DATETIME},
      `modified` {DATETIME},
      `active` tinyint(1) NOT NULL default '1',
      PRIMARY KEY  (`domain`)
    ) {COLLATE} COMMENT='Postfix Admin - Virtual Domains'; ";

    $sql[] = "
    CREATE TABLE {IF_NOT_EXISTS} $domain_admins (
      `username` varchar(255) NOT NULL default '',
      `domain` varchar(255) NOT NULL default '',
      `created` {DATETIME},
      `active` tinyint(1) NOT NULL default '1',
      KEY username (`username`)
    ) {COLLATE} COMMENT='Postfix Admin - Domain Admins';";

    $sql[] = "
    CREATE TABLE {IF_NOT_EXISTS} $log (
      `timestamp` {DATETIME},
      `username` varchar(255) NOT NULL default '',
      `domain` varchar(255) NOT NULL default '',
      `action` varchar(255) NOT NULL default '',
      `data` varchar(255) NOT NULL default '',
      KEY timestamp (`timestamp`)
    ) {COLLATE} COMMENT='Postfix Admin - Log';";

    $sql[] = "
    CREATE TABLE {IF_NOT_EXISTS} $mailbox (
      `username` varchar(255) NOT NULL default '',
      `password` varchar(255) NOT NULL default '',
      `name` varchar(255) NOT NULL default '',
      `maildir` varchar(255) NOT NULL default '',
      `quota` bigint(20) NOT NULL default '0',
      `domain` varchar(255) NOT NULL default '',
      `created` {DATETIME},
      `modified` {DATETIME},
      `active` tinyint(1) NOT NULL default '1',
      PRIMARY KEY  (`username`)
    ) {COLLATE} COMMENT='Postfix Admin - Virtual Mailboxes';";

    $sql[] = "
    CREATE TABLE {IF_NOT_EXISTS} $vacation ( 
        email varchar(255) NOT NULL , 
        subject varchar(255) NOT NULL, 
        body text NOT NULL, 
        cache text NOT NULL, 
        domain varchar(255) NOT NULL , 
        created {DATETIME},
        active tinyint(4) NOT NULL default '1', 
        PRIMARY KEY (email), 
        KEY email (email) 
    ) {INNODB} {COLLATE} COMMENT='Postfix Admin - Virtual Vacation' ;";

    foreach ($sql as $query) {
        db_query_parsed($query);
    }
}

/**
 * @return void
 */
function upgrade_2_mysql() {
    #
    # updating the tables in this very old layout (pre 2.1) causes trouble if the MySQL charset is not latin1 (multibyte vs. index length)
    # therefore:

    return; # <-- skip running this function at all.

    # (remove the above "return" if you really want to update a pre-2.1 database)

    # upgrade pre-2.1 database
    # from TABLE_BACKUP_MX.TXT
    $table_domain = table_by_key('domain');
    if (!_mysql_field_exists($table_domain, 'transport')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN transport VARCHAR(255) AFTER maxquota;", true);
    }
    if (!_mysql_field_exists($table_domain, 'backupmx')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN backupmx {BOOLEAN} AFTER transport;", true);
    }
}

/**
 * @return void
 */
function upgrade_2_pgsql() {
    if (!_pgsql_object_exists(table_by_key('domain'))) {
        db_query_parsed("
            CREATE TABLE " . table_by_key('domain') . " (
                domain character varying(255) NOT NULL,
                description character varying(255) NOT NULL default '',
                aliases integer NOT NULL default 0,
                mailboxes integer NOT NULL default 0,
                maxquota integer NOT NULL default 0,
                quota integer NOT NULL default 0,
                transport character varying(255) default NULL,
                backupmx boolean NOT NULL default false,
                created timestamp with time zone default now(),
                modified timestamp with time zone default now(),
                active boolean NOT NULL default true,
                Constraint \"domain_key\" Primary Key (\"domain\")
            ); ");
        db_query_parsed("CREATE INDEX domain_domain_active ON " . table_by_key('domain') . "(domain,active);");
        db_query_parsed("COMMENT ON TABLE " . table_by_key('domain') . " IS 'Postfix Admin - Virtual Domains'");
    }
    if (!_pgsql_object_exists(table_by_key('admin'))) {
        db_query_parsed('
            CREATE TABLE ' . table_by_key("admin") . ' (
              "username" character varying(255) NOT NULL,
              "password" character varying(255) NOT NULL default \'\',
              "created" timestamp with time zone default now(),
              "modified" timestamp with time zone default now(),
              "active" boolean NOT NULL default true,
            Constraint "admin_key" Primary Key ("username")
        )');
        db_query_parsed("COMMENT ON TABLE " . table_by_key('admin') . " IS 'Postfix Admin - Virtual Admins'");
    }

    if (!_pgsql_object_exists(table_by_key('alias'))) {
        db_query_parsed('
            CREATE TABLE ' . table_by_key("alias") . ' (
             address character varying(255) NOT NULL,
             goto text NOT NULL,
             domain character varying(255) NOT NULL REFERENCES "' . table_by_key("domain") . '",
             created timestamp with time zone default now(),
             modified timestamp with time zone default now(),
             active boolean NOT NULL default true,
             Constraint "alias_key" Primary Key ("address")
            );');
        db_query_parsed('CREATE INDEX alias_address_active ON ' . table_by_key("alias") . '(address,active)');
        db_query_parsed('COMMENT ON TABLE ' . table_by_key("alias") . ' IS \'Postfix Admin - Virtual Aliases\'');
    }

    if (!_pgsql_object_exists(table_by_key('domain_admins'))) {
        db_query_parsed('
        CREATE TABLE ' . table_by_key('domain_admins') . ' (
             username character varying(255) NOT NULL,
             domain character varying(255) NOT NULL REFERENCES "' . table_by_key('domain') . '",
             created timestamp with time zone default now(),
             active boolean NOT NULL default true
            );');
        db_query_parsed('COMMENT ON TABLE ' . table_by_key('domain_admins') . ' IS \'Postfix Admin - Domain Admins\'');
    }

    if (!_pgsql_object_exists(table_by_key('log'))) {
        db_query_parsed('
            CREATE TABLE ' . table_by_key('log') . ' (
             timestamp timestamp with time zone default now(),
             username character varying(255) NOT NULL default \'\',
             domain character varying(255) NOT NULL default \'\',
             action character varying(255) NOT NULL default \'\',
             data text NOT NULL default \'\'
            );');
        db_query_parsed('COMMENT ON TABLE ' . table_by_key('log') . ' IS \'Postfix Admin - Log\'');
    }
    if (!_pgsql_object_exists(table_by_key('mailbox'))) {
        db_query_parsed('
            CREATE TABLE ' . table_by_key('mailbox') . ' (
                 username character varying(255) NOT NULL,
                 password character varying(255) NOT NULL default \'\',
                 name character varying(255) NOT NULL default \'\',
                 maildir character varying(255) NOT NULL default \'\',
                 quota integer NOT NULL default 0,
                 domain character varying(255) NOT NULL REFERENCES "' . table_by_key('domain') . '",
                 created timestamp with time zone default now(),
                 modified timestamp with time zone default now(),
                 active boolean NOT NULL default true,
                 Constraint "mailbox_key" Primary Key ("username")
                );');
        db_query_parsed('CREATE INDEX mailbox_username_active ON ' . table_by_key('mailbox') . '(username,active);');
        db_query_parsed('COMMENT ON TABLE ' . table_by_key('mailbox') . ' IS \'Postfix Admin - Virtual Mailboxes\'');
    }

    if (!_pgsql_object_exists(table_by_key('vacation'))) {
        db_query_parsed('
            CREATE TABLE ' . table_by_key('vacation') . ' (
                email character varying(255) PRIMARY KEY,
                subject character varying(255) NOT NULL,
                body text NOT NULL ,
                cache text NOT NULL ,
                "domain" character varying(255) NOT NULL REFERENCES "' . table_by_key('domain') . '",
                created timestamp with time zone DEFAULT now(),
                active boolean DEFAULT true NOT NULL
            );');
        db_query_parsed('CREATE INDEX vacation_email_active ON ' . table_by_key('vacation') . '(email,active);');
    }

    if (!_pgsql_object_exists(table_by_key('vacation_notification'))) {
        db_query_parsed('
            CREATE TABLE ' . table_by_key('vacation_notification') . ' (
                on_vacation character varying(255) NOT NULL REFERENCES ' . table_by_key('vacation') . '(email) ON DELETE CASCADE,
                notified character varying(255) NOT NULL,
                notified_at timestamp with time zone NOT NULL DEFAULT now(),
                CONSTRAINT vacation_notification_pkey primary key(on_vacation,notified)
            );
        ');
    }
}

/**
 * @return void
 */
function upgrade_3_mysql() {
    #
    # updating the tables in this very old layout (pre 2.1) causes trouble if the MySQL charset is not latin1 (multibyte vs. index length)
    # therefore:

    return; # <-- skip running this function at all.

    # (remove the above "return" if you really want to update a pre-2.1 database)

    # upgrade pre-2.1 database
    # from TABLE_CHANGES.TXT
    $table_admin = table_by_key('admin');
    $table_alias = table_by_key('alias');
    $table_domain = table_by_key('domain');
    $table_mailbox = table_by_key('mailbox');
    $table_vacation = table_by_key('vacation');

    if (!_mysql_field_exists($table_admin, 'created')) {
        db_query_parsed("ALTER TABLE $table_admin {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if (!_mysql_field_exists($table_admin, 'modified')) {
        db_query_parsed("ALTER TABLE $table_admin {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if (!_mysql_field_exists($table_alias, 'created')) {
        db_query_parsed("ALTER TABLE $table_alias {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if (!_mysql_field_exists($table_alias, 'modified')) {
        db_query_parsed("ALTER TABLE $table_alias {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if (!_mysql_field_exists($table_domain, 'created')) {
        db_query_parsed("ALTER TABLE $table_domain {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if (!_mysql_field_exists($table_domain, 'modified')) {
        db_query_parsed("ALTER TABLE $table_domain {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if (!_mysql_field_exists($table_domain, 'aliases')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN aliases INT(10) DEFAULT '-1' NOT NULL AFTER description;");
    }
    if (!_mysql_field_exists($table_domain, 'mailboxes')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN mailboxes INT(10) DEFAULT '-1' NOT NULL AFTER aliases;");
    }
    if (!_mysql_field_exists($table_domain, 'maxquota')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN maxquota INT(10) DEFAULT '-1' NOT NULL AFTER mailboxes;");
    }
    if (!_mysql_field_exists($table_domain, 'transport')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN transport VARCHAR(255) AFTER maxquota;");
    }
    if (!_mysql_field_exists($table_domain, 'backupmx')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN backupmx TINYINT(1) DEFAULT '0' NOT NULL AFTER transport;");
    }
    if (!_mysql_field_exists($table_mailbox, 'created')) {
        db_query_parsed("ALTER TABLE $table_mailbox {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if (!_mysql_field_exists($table_mailbox, 'modified')) {
        db_query_parsed("ALTER TABLE $table_mailbox {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if (!_mysql_field_exists($table_mailbox, 'quota')) {
        db_query_parsed("ALTER TABLE $table_mailbox ADD COLUMN quota INT(10) DEFAULT '-1' NOT NULL AFTER maildir;");
    }
    if (!_mysql_field_exists($table_vacation, 'domain')) {
        db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN domain VARCHAR(255) DEFAULT '' NOT NULL AFTER cache;");
    }
    if (!_mysql_field_exists($table_vacation, 'created')) {
        db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN created {DATETIME} AFTER domain;");
    }
    if (!_mysql_field_exists($table_vacation, 'active')) {
        db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN active TINYINT(1) DEFAULT '1' NOT NULL AFTER created;");
    }
    db_query_parsed("ALTER TABLE $table_vacation DROP PRIMARY KEY");
    db_query_parsed("ALTER TABLE $table_vacation ADD PRIMARY KEY(email)");
    db_query_parsed("UPDATE $table_vacation SET domain=SUBSTRING_INDEX(email, '@', -1) WHERE email=email;");
}

/**
 * @return void
 */
function upgrade_4_mysql() { # MySQL only
    # changes between 2.1 and moving to sourceforge

    return; // as the above _mysql functions are disabled; this one will just error for a new db.
    $table_domain = table_by_key('domain');

    db_query_parsed("ALTER TABLE $table_domain ADD COLUMN quota int(10) NOT NULL default '0' AFTER maxquota", true);
    # Possible errors that can be ignored:
    # - Invalid query: Table 'postfix.domain' doesn't exist
}

/**
 * Changes between 2.1 and moving to sf.net
 * @return void
 */
function upgrade_4_pgsql() {
    $table_domain = table_by_key('domain');
    $table_admin = table_by_key('admin');
    $table_alias = table_by_key('alias');
    $table_domain_admins = table_by_key('domain_admins');
    $table_log = table_by_key('log');
    $table_mailbox = table_by_key('mailbox');
    $table_vacation = table_by_key('vacation');
    $table_vacation_notification = table_by_key('vacation_notification');

    if (!_pgsql_field_exists($table_domain, 'quota')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN quota int NOT NULL default '0'");
    }

    db_query_parsed("ALTER TABLE $table_domain ALTER COLUMN domain DROP DEFAULT");
    if (!_pgsql_object_exists('domain_domain_active')) {
        db_query_parsed("CREATE INDEX domain_domain_active ON $table_domain(domain,active)");
    }

    db_query_parsed("ALTER TABLE $table_domain_admins ALTER COLUMN domain DROP DEFAULT");
    db_query_parsed("ALTER TABLE $table_alias ALTER COLUMN address DROP DEFAULT");
    db_query_parsed("ALTER TABLE $table_alias ALTER COLUMN domain DROP DEFAULT");
    if (!_pgsql_object_exists('alias_address_active')) {
        db_query_parsed("CREATE INDEX alias_address_active ON $table_alias(address,active)");
    }

    db_query_parsed("ALTER TABLE $table_domain_admins ALTER COLUMN username DROP DEFAULT");
    db_query_parsed("ALTER TABLE $table_domain_admins ALTER COLUMN domain DROP DEFAULT");

    db_query_parsed("BEGIN ");
    db_query_parsed("ALTER TABLE $table_log RENAME COLUMN data TO data_old;");
    db_query_parsed("ALTER TABLE $table_log ADD COLUMN data text NOT NULL default '';");
    db_query_parsed("UPDATE $table_log SET data = CAST(data_old AS text);");
    db_query_parsed("ALTER TABLE $table_log DROP COLUMN data_old;");
    db_query_parsed("COMMIT");

    db_query_parsed("ALTER TABLE $table_mailbox ALTER COLUMN username DROP DEFAULT");
    db_query_parsed("ALTER TABLE $table_mailbox ALTER COLUMN domain DROP DEFAULT");

    db_query_parsed("BEGIN;");
    db_query_parsed("ALTER TABLE $table_mailbox RENAME COLUMN domain TO domain_old;");
    db_query_parsed("ALTER TABLE $table_mailbox ADD COLUMN domain varchar(255) REFERENCES $table_domain (domain);");
    db_query_parsed("UPDATE $table_mailbox SET domain = domain_old;");
    db_query_parsed("ALTER TABLE $table_mailbox DROP COLUMN domain_old;");
    db_query_parsed("COMMIT;");

    if (!_pgsql_object_exists('mailbox_username_active')) {
        db_query_parsed("CREATE INDEX mailbox_username_active ON $table_mailbox(username,active)");
    }


    db_query_parsed("ALTER TABLE $table_vacation ALTER COLUMN body SET DEFAULT ''");
    if (_pgsql_field_exists($table_vacation, 'cache')) {
        db_query_parsed("ALTER TABLE $table_vacation DROP COLUMN cache");
    }

    db_query_parsed(" BEGIN; ");
    db_query_parsed("ALTER TABLE $table_vacation RENAME COLUMN domain to domain_old;");
    db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN domain varchar(255) REFERENCES $table_domain;");
    db_query_parsed("UPDATE $table_vacation SET domain = domain_old;");
    db_query_parsed("ALTER TABLE $table_vacation DROP COLUMN domain_old;");
    db_query_parsed("COMMIT;");

    if (!_pgsql_object_exists('vacation_email_active')) {
        db_query_parsed("CREATE INDEX vacation_email_active ON $table_vacation(email,active)");
    }

    if (!_pgsql_object_exists($table_vacation_notification)) {
        db_query_parsed("
            CREATE TABLE $table_vacation_notification (
                on_vacation character varying(255) NOT NULL REFERENCES $table_vacation(email) ON DELETE CASCADE,
                notified character varying(255) NOT NULL,
                notified_at timestamp with time zone NOT NULL DEFAULT now(),
        CONSTRAINT vacation_notification_pkey primary key(on_vacation,notified));");
    }
}


# Possible errors that can be ignored:
#
# NO MySQL errors should be ignored below this line!



/**
 * create tables
 * version: Sourceforge SVN r1 of DATABASE_MYSQL.txt
 * changes compared to DATABASE_MYSQL.txt:
 * - removed MySQL user and database creation
 * - removed creation of default superadmin
 * @return void
 */
function upgrade_5_mysql() {
    db_query_parsed("
        CREATE TABLE {IF_NOT_EXISTS} " . table_by_key('admin') . " (
            `username` varchar(255) NOT NULL default '',
            `password` varchar(255) NOT NULL default '',
            `created` {DATETIME},
            `modified` {DATETIME},
            `active` tinyint(1) NOT NULL default '1',
            PRIMARY KEY  (`username`),
    KEY username (`username`)
) {COLLATE} COMMENT='Postfix Admin - Virtual Admins'; ");

    db_query_parsed("
        CREATE TABLE {IF_NOT_EXISTS} " . table_by_key('alias') . " (
            `address` varchar(255) NOT NULL default '',
            `goto` text NOT NULL,
            `domain` varchar(255) NOT NULL default '',
            `created` {DATETIME},
            `modified` {DATETIME},
            `active` tinyint(1) NOT NULL default '1',
            PRIMARY KEY  (`address`),
    KEY address (`address`)
            ) {COLLATE} COMMENT='Postfix Admin - Virtual Aliases';
    ");

    db_query_parsed("
        CREATE TABLE {IF_NOT_EXISTS} " . table_by_key('domain') . " (
            `domain` varchar(255) NOT NULL default '',
            `description` varchar(255) NOT NULL default '',
            `aliases` int(10) NOT NULL default '0',
            `mailboxes` int(10) NOT NULL default '0',
            `maxquota` int(10) NOT NULL default '0',
            `quota` int(10) NOT NULL default '0',
            `transport` varchar(255) default NULL,
            `backupmx` tinyint(1) NOT NULL default '0',
            `created` {DATETIME},
            `modified` {DATETIME},
            `active` tinyint(1) NOT NULL default '1',
            PRIMARY KEY  (`domain`),
    KEY domain (`domain`)
            ) {COLLATE} COMMENT='Postfix Admin - Virtual Domains';
    ");

    db_query_parsed("
        CREATE TABLE {IF_NOT_EXISTS} " . table_by_key('domain_admins') . " (
            `username` varchar(255) NOT NULL default '',
            `domain` varchar(255) NOT NULL default '',
            `created` {DATETIME},
            `active` tinyint(1) NOT NULL default '1',
            KEY username (`username`)
        ) {COLLATE} COMMENT='Postfix Admin - Domain Admins';
    ");

    db_query_parsed("
        CREATE TABLE {IF_NOT_EXISTS} " . table_by_key('log') . " (
            `timestamp` {DATETIME},
            `username` varchar(255) NOT NULL default '',
            `domain` varchar(255) NOT NULL default '',
            `action` varchar(255) NOT NULL default '',
            `data` varchar(255) NOT NULL default '',
            KEY timestamp (`timestamp`)
        ) {COLLATE} COMMENT='Postfix Admin - Log';
    ");

    db_query_parsed("
        CREATE TABLE {IF_NOT_EXISTS} " . table_by_key('mailbox') . " (
            `username` varchar(255) NOT NULL default '',
            `password` varchar(255) NOT NULL default '',
            `name` varchar(255) NOT NULL default '',
            `maildir` varchar(255) NOT NULL default '',
            `quota` int(10) NOT NULL default '0',
            `domain` varchar(255) NOT NULL default '',
            `created` {DATETIME},
            `modified` {DATETIME},
            `active` tinyint(1) NOT NULL default '1',
            PRIMARY KEY  (`username`),
            KEY username (`username`)
            ) {COLLATE} COMMENT='Postfix Admin - Virtual Mailboxes';
    ");

    db_query_parsed("
        CREATE TABLE {IF_NOT_EXISTS} " . table_by_key('vacation') . " (
            `email` varchar(255) NOT NULL ,
            `subject` varchar(255) NOT NULL,
            `body` text NOT NULL,
            `cache` text NOT NULL,
            `domain` varchar(255) NOT NULL,
            `created` {DATETIME},
            `active` tinyint(1) NOT NULL default '1',
            PRIMARY KEY  (`email`),
    KEY email (`email`)
            ) {COLLATE} COMMENT='Postfix Admin - Virtual Vacation';
    ");
}

/**
 * drop useless indicies (already available as primary key)
 * @return void
 */
function upgrade_79_mysql() { # MySQL only
    db_query_parsed(_drop_index('admin', 'username'), true);
    db_query_parsed(_drop_index('alias', 'address'), true);
    db_query_parsed(_drop_index('domain', 'domain'), true);
    db_query_parsed(_drop_index('mailbox', 'username'), true);
}

/**
 * @return void
 */
function upgrade_81_mysql() { # MySQL only
    $table_vacation = table_by_key('vacation');
    $table_vacation_notification = table_by_key('vacation_notification');

    $all_sql = explode("\n", trim("
        ALTER TABLE $table_vacation CHANGE `email`    `email`   VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_vacation CHANGE `subject`  `subject` VARCHAR( 255 ) {UTF-8}  NOT NULL
        ALTER TABLE $table_vacation CHANGE `body`     `body`    TEXT           {UTF-8}  NOT NULL
        ALTER TABLE $table_vacation CHANGE `cache`    `cache`   TEXT           {LATIN1} NOT NULL
        ALTER TABLE $table_vacation CHANGE `domain`   `domain`  VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_vacation CHANGE `active`   `active`  TINYINT( 1 )            NOT NULL DEFAULT '1'
        ALTER TABLE $table_vacation DEFAULT  {COLLATE}
        ALTER TABLE $table_vacation {INNODB}
    "));

    foreach ($all_sql as $sql) {
        db_query_parsed($sql, true);
    }
}

/**
 * Make logging translatable - i.e. create alias => create_alias
 * @return void
 */
function upgrade_90_mysql_pgsql() {
    db_query_parsed("UPDATE " . table_by_key('log') . " SET action = REPLACE(action,' ','_')", true);
    # change edit_alias_state to edit_alias_active
    db_query_parsed("UPDATE " . table_by_key('log') . " SET action = 'edit_alias_state' WHERE action = 'edit_alias_active'", true);
}

/**
 * MySQL only allow quota > 2 GB
 * @return void
 */
function upgrade_169_mysql() {
    $table_domain = table_by_key('domain');
    $table_mailbox = table_by_key('mailbox');
    db_query_parsed("ALTER TABLE $table_domain MODIFY COLUMN `quota` bigint(20) NOT NULL default '0'", true);
    db_query_parsed("ALTER TABLE $table_domain MODIFY COLUMN `maxquota` bigint(20) NOT NULL default '0'", true);
    db_query_parsed("ALTER TABLE $table_mailbox MODIFY COLUMN `quota` bigint(20) NOT NULL default '0'", true);
}


/**
 * Create / modify vacation_notification table.
 * Note: This might not work if users used workarounds to create the table before.
 * In this case, dropping the table is the easiest solution.
 * @return void
 */
function upgrade_318_mysql() {
    $table_vacation_notification = table_by_key('vacation_notification');
    $table_vacation = table_by_key('vacation');

    db_query_parsed("
        CREATE TABLE {IF_NOT_EXISTS} $table_vacation_notification (
            on_vacation varchar(255) NOT NULL,
            notified varchar(255) NOT NULL,
            notified_at timestamp NOT NULL default CURRENT_TIMESTAMP,
            PRIMARY KEY on_vacation (`on_vacation`, `notified`),
        CONSTRAINT `vacation_notification_pkey` 
        FOREIGN KEY (`on_vacation`) REFERENCES $table_vacation(`email`) ON DELETE CASCADE
    ) {INNODB} {COLLATE} COMMENT='Postfix Admin - Virtual Vacation Notifications'
    ");

    # in case someone has manually created the table with utf8 fields before:
    $all_sql = explode("\n", trim("
        ALTER TABLE $table_vacation_notification CHANGE `notified`    `notified`    VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_vacation_notification DEFAULT CHARACTER SET utf8
    "));
    # Possible errors that can be ignored:
    # None.
    # If something goes wrong, the user should drop the vacation_notification table
    # (not a great loss) and re-create it using this function.

    foreach ($all_sql as $sql) {
        db_query_parsed($sql);
    }
}


/**
 * Create fetchmail table
 * @return void
 */
function upgrade_344_mysql() {
    $table_fetchmail = table_by_key('fetchmail');

    db_query_parsed("
        CREATE TABLE IF NOT EXISTS $table_fetchmail(
         id int(11) unsigned not null auto_increment,
         mailbox varchar(255) not null default '',
         src_server varchar(255) not null default '',
         src_auth enum('password','kerberos_v5','kerberos','kerberos_v4','gssapi','cram-md5','otp','ntlm','msn','ssh','any'),
         src_user varchar(255) not null default '',
         src_password varchar(255) not null default '',
         src_folder varchar(255) not null default '',
         poll_time int(11) unsigned not null default 10,
         fetchall tinyint(1) unsigned not null default 0,
         keep tinyint(1) unsigned not null default 0,
         protocol enum('POP3','IMAP','POP2','ETRN','AUTO'),
         extra_options text,
         returned_text text,
         mda varchar(255) not null default '',
         date timestamp,
         primary key(id)
        );
    ");
}

/**
 * @return void
 */
function upgrade_344_pgsql() {
    $fetchmail = table_by_key('fetchmail');
    // a field name called 'date' is probably a bad idea.
    if (!_pgsql_object_exists('fetchmail')) {
        db_query_parsed("
            create table $fetchmail(
             id serial,
             mailbox varchar(255) not null default '',
             src_server varchar(255) not null default '',
             src_auth varchar(15) NOT NULL,
             src_user varchar(255) not null default '',
             src_password varchar(255) not null default '',
             src_folder varchar(255) not null default '',
             poll_time integer not null default 10,
             fetchall boolean not null default false,
             keep boolean not null default false,
             protocol varchar(15) NOT NULL,
             extra_options text,
             returned_text text,
             mda varchar(255) not null default '',
             date timestamp with time zone default now(),
            primary key(id),
            CHECK (src_auth IN ('password','kerberos_v5','kerberos','kerberos_v4','gssapi','cram-md5','otp','ntlm','msn','ssh','any')),
            CHECK (protocol IN ('POP3', 'IMAP', 'POP2', 'ETRN', 'AUTO'))
        );
        ");
    }
    // MySQL expects sequences to start at 1. Stupid database.
    // fetchmail.php requires id parameters to be > 0, as it does if($id) like logic... hence if we don't
    // fudge the sequence starting point, you cannot delete/edit the first entry if using PostgreSQL.
    // I'm sure there's a more elegant way of fixing it properly.... but this should work for now.
    if (_pgsql_object_exists('fetchmail_id_seq')) {
        db_query_parsed("SELECT nextval('{$fetchmail}_id_seq')"); // I don't care about number waste.
    }
}

/**
 * Create alias_domain table - MySQL
 * function upgrade_362_mysql() # renamed to _438 to make sure it runs after an upgrade from 2.2.x
 *
 * @return void
 */
function upgrade_438_mysql() {
    # Table structure for table alias_domain
    #
    $table_alias_domain = table_by_key('alias_domain');
    db_query_parsed("
        CREATE TABLE IF NOT EXISTS $table_alias_domain (
            `alias_domain` varchar(255) {LATIN1} NOT NULL default '',
            `target_domain` varchar(255) {LATIN1} NOT NULL default '',
            `created` {DATETIME},
            `modified` {DATETIME},
            `active` tinyint(1) NOT NULL default '1',
            PRIMARY KEY  (`alias_domain`),
            KEY `active` (`active`),
            KEY `target_domain` (`target_domain`)
        ) {COLLATE} COMMENT='Postfix Admin - Domain Aliases'
    ");
}

/**
 * Create alias_domain table - PgSQL
 * function upgrade_362_pgsql()  # renamed to _438 to make sure it runs after an upgrade from 2.2.x
 * @return void
 */
function upgrade_438_pgsql() {
    # Table structure for table alias_domain
    $table_alias_domain = table_by_key('alias_domain');
    $table_domain = table_by_key('domain');
    if (_pgsql_object_exists($table_alias_domain)) {
        return;
    }
    db_query_parsed("
        CREATE TABLE $table_alias_domain (
            alias_domain character varying(255) NOT NULL REFERENCES $table_domain(domain) ON DELETE CASCADE,
            target_domain character varying(255) NOT NULL REFERENCES $table_domain(domain) ON DELETE CASCADE,
            created timestamp with time zone default now(),
            modified timestamp with time zone default now(),
            active boolean NOT NULL default true, 
            PRIMARY KEY(alias_domain))");
    db_query_parsed("CREATE INDEX alias_domain_active ON $table_alias_domain(alias_domain,active)");
    db_query_parsed("COMMENT ON TABLE $table_alias_domain IS 'Postfix Admin - Domain Aliases'");
}

/**
 * Change description fields to UTF-8
 * @return void
 */
function upgrade_373_mysql() { # MySQL only
    $table_domain = table_by_key('domain');
    $table_mailbox = table_by_key('mailbox');

    $all_sql = explode("\n", trim("
        ALTER TABLE $table_domain  CHANGE `description`  `description` VARCHAR( 255 ) {UTF-8}  NOT NULL
        ALTER TABLE $table_mailbox CHANGE `name`         `name`        VARCHAR( 255 ) {UTF-8}  NOT NULL
    "));

    foreach ($all_sql as $sql) {
        db_query_parsed($sql);
    }
}


/**
 * add ssl option for fetchmail
 * @return void
 */
function upgrade_439_mysql() {
    $table_fetchmail = table_by_key('fetchmail');
    if (!_mysql_field_exists($table_fetchmail, 'ssl')) {
        db_query_parsed("ALTER TABLE $table_fetchmail ADD `ssl` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT '0' AFTER `protocol` ; ");
    }
}

/**
 * @return void
 */
function upgrade_439_pgsql() {
    $table_fetchmail = table_by_key('fetchmail');
    if (!_pgsql_field_exists($table_fetchmail, 'ssl')) {
        db_query_parsed("ALTER TABLE $table_fetchmail ADD COLUMN ssl BOOLEAN NOT NULL DEFAULT false");
    }
}

/**
 * @return  void
 */
function upgrade_473_mysql() {
    $table_admin   = table_by_key('admin');
    $table_alias   = table_by_key('alias');
    $table_al_dom  = table_by_key('alias_domain');
    $table_domain  = table_by_key('domain');
    $table_dom_adm = table_by_key('domain_admins');
    $table_fmail   = table_by_key('fetchmail');
    $table_mailbox = table_by_key('mailbox');
    $table_log     = table_by_key('log');

    # tables were created without explicit charset before :-(
    $all_sql = explode("\n", trim("
        ALTER TABLE $table_admin   CHANGE `username`      `username`      VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_admin   CHANGE `password`      `password`      VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_admin   DEFAULT                                               {LATIN1}
        ALTER TABLE $table_alias   CHANGE `address`       `address`       VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_alias   CHANGE `goto`          `goto`             TEXT        {LATIN1} NOT NULL
        ALTER TABLE $table_alias   CHANGE `domain`        `domain`        VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_alias   DEFAULT                                               {LATIN1}
        ALTER TABLE $table_al_dom  CHANGE `alias_domain`  `alias_domain`  VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_al_dom  CHANGE `target_domain` `target_domain` VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_al_dom  DEFAULT                                               {LATIN1}
        ALTER TABLE $table_domain  CHANGE `domain`         `domain`       VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_domain  CHANGE `transport`      `transport`    VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_domain  DEFAULT                                               {LATIN1}
        ALTER TABLE $table_dom_adm CHANGE `username`       `username`     VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_dom_adm CHANGE `domain`         `domain`       VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_dom_adm DEFAULT                                               {LATIN1}
        ALTER TABLE $table_log     CHANGE `username`       `username`     VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_log     CHANGE `domain`         `domain`       VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_log     CHANGE `action`         `action`       VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_log     CHANGE `data`           `data`         VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_log     DEFAULT                                               {LATIN1}
        ALTER TABLE $table_mailbox CHANGE `username`       `username`     VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_mailbox CHANGE `password`       `password`     VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_mailbox CHANGE `maildir`        `maildir`      VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_mailbox CHANGE `domain`         `domain`       VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_mailbox DEFAULT                                               {LATIN1}
        ALTER TABLE $table_fmail   CHANGE `mailbox`        `mailbox`      VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_fmail   CHANGE `src_server`     `src_server`   VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_fmail   CHANGE `src_user`       `src_user`     VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_fmail   CHANGE `src_password`   `src_password` VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_fmail   CHANGE `src_folder`     `src_folder`   VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_fmail   CHANGE `mda`            `mda`          VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_fmail   CHANGE `mailbox`        `mailbox`      VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE $table_fmail   CHANGE `extra_options`  `extra_options`   TEXT        {LATIN1} NULL DEFAULT NULL
        ALTER TABLE $table_fmail   CHANGE `returned_text`  `returned_text`   TEXT        {LATIN1} NULL DEFAULT NULL
        ALTER TABLE $table_fmail   DEFAULT                                               {LATIN1}
        "));

    foreach ($all_sql as $sql) {
        db_query_parsed($sql);
    }
}

/**
 * @return  void
 */
function upgrade_479_mysql() {
    # ssl is a reserved word in MySQL and causes several problems. Renaming the field...
    $table_fmail   = table_by_key('fetchmail');
    if (!_mysql_field_exists($table_fmail, 'usessl')) {
        db_query_parsed("ALTER TABLE $table_fmail CHANGE `ssl` `usessl` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT '0'");
    }
}


/**
 * @return  void
 */
function upgrade_479_pgsql() {
    $table_fmail   = table_by_key('fetchmail');
    if (!_pgsql_field_exists($table_fmail, 'usessl')) {
        db_query_parsed("alter table $table_fmail rename column ssl to usessl");
    }
}

/**
 * @return  void
 */
function upgrade_483_mysql() {
    $table_log   = table_by_key('log');
    db_query_parsed("ALTER TABLE $table_log CHANGE `data` `data` TEXT {LATIN1} NOT NULL");
}

/**
 * Add a local_part field to the mailbox table, and populate it with the local part of the user's address.
 * This is to make it easier (hopefully) to change the filesystem location of a mailbox in the future
 * See https://sourceforge.net/forum/message.php?msg_id=5394663
 * @return  void
 */
function upgrade_495_pgsql() {
    $table_mailbox = table_by_key('mailbox');
    if (!_pgsql_field_exists($table_mailbox, 'local_part')) {
        db_query_parsed("ALTER TABLE $table_mailbox add column local_part varchar(255) ");
        db_query_parsed("UPDATE $table_mailbox SET local_part = substring(username from '^(.*)@')");
        db_query_parsed("ALTER TABLE $table_mailbox alter column local_part SET NOT NULL");
    }
}
/**
 * See https://sourceforge.net/forum/message.php?msg_id=5394663
 * @return void
 */
function upgrade_495_mysql() {
    $table_mailbox = table_by_key('mailbox');
    if (!_mysql_field_exists($table_mailbox, 'local_part')) {
        db_query_parsed("ALTER TABLE $table_mailbox add local_part varchar(255) AFTER quota"); // allow to be null
        db_query_parsed("UPDATE $table_mailbox SET local_part = substring_index(username, '@', 1)");
        db_query_parsed("ALTER TABLE $table_mailbox change local_part local_part varchar(255) NOT NULL"); // remove null-ness...
    }
}

/**
 * @return void
 */
function upgrade_504_mysql() {
    $table_mailbox = table_by_key('mailbox');
    db_query_parsed("ALTER TABLE $table_mailbox CHANGE `local_part` `local_part` VARCHAR( 255 ) {LATIN1} NOT NULL");
}

/**
 * @return void
 */
function upgrade_655_mysql_pgsql() {
    db_query_parsed(_add_index('mailbox', 'domain', 'domain'));
    db_query_parsed(_add_index('alias', 'domain', 'domain'));
}

/*
   function number too small for upgrades from 2.3.x
   -> adding activefrom and activeuntil to vacation table is now upgrade_964
   -> the tables client_access, from_access, helo_access, rcpt_access, user_whitelist
      are not used by PostfixAdmin - no replacement function needed
   Note: Please never remove this function, even if it is disabled - it might be needed in case we have to debug a broken database upgrade etc.
    Note: there never was a function upgrade_727_pgsql()

function upgrade_727_mysql() {
    $table_vacation = table_by_key('vacation');
    if(!_mysql_field_exists($table_vacation, 'activefrom')) {
       db_query_parsed("ALTER TABLE $table_vacation add activefrom datetime default NULL");
    }
    if(!_mysql_field_exists($table_vacation, 'activeuntil')) {
       db_query_parsed("ALTER TABLE $table_vacation add activeuntil datetime default NULL");
    }

    # the following tables are not used by postfixadmin

    $table_client_access = table_by_key('client_access');
     db_query_parsed("
         CREATE TABLE IF NOT EXISTS $table_client_access (
             `client` char(50) NOT NULL,
             `action` char(50) NOT NULL default 'REJECT',
             UNIQUE KEY `client` (`client`)
         ) COMMENT='Postfix Admin - Client Access'
     ");
    $table_from_access = table_by_key('from_access');
     db_query_parsed("
         CREATE TABLE IF NOT EXISTS $table_from_access (
             `from_access` char(50) NOT NULL,
             `action` char(50) NOT NULL default 'REJECT',
             UNIQUE KEY `from_access` (`from_access`)
         ) COMMENT='Postfix Admin - From Access'
     ");
     $table_helo_access = table_by_key('helo_access');
     db_query_parsed("
         CREATE TABLE IF NOT EXISTS $table_helo_access (
             `helo` char(50) NOT NULL,
             `action` char(50) NOT NULL default 'REJECT',
             UNIQUE KEY `helo` (`helo`)
         ) COMMENT='Postfix Admin - Helo Access'
     ");
     $table_rcpt_access = table_by_key('rcpt_access');
     db_query_parsed("
         CREATE TABLE IF NOT EXISTS $table_rcpt_access (
             `rcpt` char(50) NOT NULL,
             `action` char(50) NOT NULL default 'REJECT',
             UNIQUE KEY `rcpt` (`rcpt`)
         ) COMMENT='Postfix Admin - Recipient Access'
     ");
     $table_user_whitelist = table_by_key('user_whitelist');
     db_query_parsed("
         CREATE TABLE IF NOT EXISTS $table_user_whitelist (
             `recipient` char(50) NOT NULL,
             `action` char(50) NOT NULL default 'REJECT',
             UNIQUE KEY `recipient` (`recipient`)
         ) COMMENT='Postfix Admin - User whitelist'
     ");
}
*/

/**
 * @return void
 */
function upgrade_729_mysql_pgsql() {
    $table_quota = table_by_key('quota');
    $table_quota2 = table_by_key('quota2');

    # table for dovecot v1.0 & 1.1
    # note: quota table created with old versions of upgrade.php (before r1605)
    # will not have explicit "NOT NULL DEFAULT 0" for the "current" field
    # (shouldn't hurt)
    db_query_parsed("
    CREATE TABLE {IF_NOT_EXISTS} $table_quota (
        username VARCHAR(255) {LATIN1} NOT NULL,
        path     VARCHAR(100) {LATIN1} NOT NULL,
        current  {BIGINT},
        PRIMARY KEY (username, path)
    ) {COLLATE}  ; 
    ");

    # table for dovecot >= 1.2
    db_query_parsed("
        CREATE TABLE {IF_NOT_EXISTS} $table_quota2 (
            username VARCHAR(100) {LATIN1} NOT NULL,
            bytes {BIGINT},
            messages integer NOT NULL DEFAULT 0,
            PRIMARY KEY (username)
        ) ;
    ");
}

/**
 * @return void
 */
function upgrade_730_pgsql() {
    $table_quota = table_by_key('quota');
    $table_quota2 = table_by_key('quota2');

    try {
        db_query_parsed('CREATE LANGUAGE plpgsql', 1); /* will error if plpgsql is already installed */
    } catch (\Exception $e) {
        error_log("ignoring exception that's probably : plpgsql is probably already installed; " . $e);
    }

    # trigger for dovecot v1.0 & 1.1 quota table
    # taken from http://wiki.dovecot.org/Quota/Dict
    db_query_parsed("
        CREATE OR REPLACE FUNCTION merge_quota() RETURNS TRIGGER AS \$merge_quota\$
        BEGIN
            UPDATE $table_quota SET current = NEW.current + current WHERE username = NEW.username AND path = NEW.path;
            IF found THEN
                RETURN NULL;
            ELSE
                RETURN NEW;
            END IF;
      END;
      \$merge_quota\$ LANGUAGE plpgsql;
    ");
    db_query_parsed("
        CREATE TRIGGER mergequota BEFORE INSERT ON $table_quota FOR EACH ROW EXECUTE PROCEDURE merge_quota();
    ");

    # trigger for dovecot >= 1.2 quota table
    # taken from http://wiki.dovecot.org/Quota/Dict, table/trigger name changed to quota2 naming
    db_query_parsed("
        CREATE OR REPLACE FUNCTION merge_quota2() RETURNS TRIGGER AS \$\$
        BEGIN
            IF NEW.messages < 0 OR NEW.messages IS NULL THEN
                -- ugly kludge: we came here from this function, really do try to insert
                IF NEW.messages IS NULL THEN
                    NEW.messages = 0;
                ELSE
                    NEW.messages = -NEW.messages;
                END IF;
                return NEW;
            END IF;

            LOOP
                UPDATE $table_quota2 SET bytes = bytes + NEW.bytes,
                    messages = messages + NEW.messages
                    WHERE username = NEW.username;
                IF found THEN
                    RETURN NULL;
                END IF;

                BEGIN
                    IF NEW.messages = 0 THEN
                    INSERT INTO $table_quota2 (bytes, messages, username) VALUES (NEW.bytes, NULL, NEW.username);
                    ELSE
                        INSERT INTO $table_quota2 (bytes, messages, username) VALUES (NEW.bytes, -NEW.messages, NEW.username);
                    END IF;
                    return NULL;
                    EXCEPTION WHEN unique_violation THEN
                    -- someone just inserted the record, update it
                END;
            END LOOP;
        END;
        \$\$ LANGUAGE plpgsql;
");

    db_query_parsed("
        CREATE TRIGGER mergequota2 BEFORE INSERT ON $table_quota2
            FOR EACH ROW EXECUTE PROCEDURE merge_quota2();
    ");
}

/**
 * @return void
 */
function upgrade_945_mysql_pgsql() {
    _db_add_field('vacation', 'modified', '{DATECURRENT}', 'created');
}

/**
 * @return void
 */
function upgrade_946_mysql_pgsql() {
    # taken from upgrade_727_mysql, needs to be done for all databases
    _db_add_field('vacation', 'activefrom', '{DATE}', 'body');
    _db_add_field('vacation', 'activeuntil', '{DATEFUTURE}', 'activefrom');
}

/**
 * @return void
 */
function upgrade_968_pgsql() {
    # pgsql counterpart for upgrade_169_mysql() - allow really big quota
    $table_domain = table_by_key('domain');
    $table_mailbox = table_by_key('mailbox');
    db_query_parsed("ALTER TABLE $table_domain  ALTER COLUMN quota    type bigint");
    db_query_parsed("ALTER TABLE $table_domain  ALTER COLUMN maxquota type bigint");
    db_query_parsed("ALTER TABLE $table_mailbox ALTER COLUMN quota    type bigint");
}

/**
 * @return void
 */
function upgrade_1050_mysql_pgsql() {
    db_query_parsed(_add_index('log', 'domain_timestamp', 'domain,timestamp'));
}


/**
 * @return void
 */
function upgrade_1283_mysql_pgsql() {
    _db_add_field('admin', 'superadmin', '{BOOLEAN}', 'password');
}

/**
 * @return void
 */
function upgrade_1284_mysql_pgsql() {
    # migrate the ALL domain to the superadmin column
    # Note: The ALL domain is not (yet) deleted to stay backwards-compatible for now (will be done in a later upgrade function)

    $result = db_query_all("SELECT username FROM " . table_by_key('domain_admins') . " where domain='ALL'");

    foreach ($result as $row) {
        printdebug("Setting superadmin flag for " . $row['username']);
        db_update('admin', 'username', $row['username'], array('superadmin' => db_get_boolean(true)));
    }
}

/**
 * @return void
 */
function upgrade_1345_mysql() {
    # $table_vacation = table_by_key('vacation');
    # adding and usage of reply_type field removed in r1610
    # db_query_parsed("ALTER TABLE `$table_vacation` ADD `reply_type` VARCHAR( 20 ) NOT NULL AFTER `domain`  ");
    # obsoleted by upgrade_1610()
    # db_query_parsed("ALTER TABLE `$table_vacation` ADD `interval_time` INT NOT NULL DEFAULT '0' AFTER `reply_type` ");
}

/**
 * @return void
 */
function upgrade_1519_mysql_pgsql() {
    _db_add_field('fetchmail', 'sslcertck', '{BOOLEAN}', 'usessl');
    _db_add_field('fetchmail', 'sslcertpath', "VARCHAR(255) {UTF-8}  DEFAULT ''", 'sslcertck');
    _db_add_field('fetchmail', 'sslfingerprint', "VARCHAR(255) {LATIN1} DEFAULT ''", 'sslcertpath');
}

/**
 * @return void
 */
function upgrade_1610_mysql_pgsql() {
    # obsoletes upgrade_1345_mysql() - which means debug mode could print "field already exists"
    _db_add_field('vacation', 'interval_time', '{INT}', 'domain');
}

/**
 * @return void
 */
function upgrade_1685_mysql() {
    # Fix existing log entries broken by https://sourceforge.net/p/postfixadmin/bugs/317/
    $table = table_by_key('log');
    db_query_parsed("UPDATE $table SET data = domain WHERE data = '' AND domain LIKE '%@%'");
    db_query_parsed("UPDATE $table SET domain=SUBSTRING_INDEX(domain, '@', -1) WHERE domain=data;");
}

/**
 * @return void
 */
function upgrade_1685_pgsql() {
    $table = table_by_key('log');
    db_query_parsed("UPDATE $table SET data = domain WHERE data = '' AND domain LIKE '%@%'");
    db_query_parsed("UPDATE $table SET domain=SPLIT_PART(domain, '@', 2) WHERE domain=data;");
}

/**
 * @return void
 */
function upgrade_1761_mysql() {
    # upgrade_1762 adds the 'modified' column as {DATECURRENT}, therefore we first need to change
    # 'date' to {DATE} (mysql only allows one {DATECURRENT} column per table)
    $table_fetchmail = table_by_key('fetchmail');
    db_query_parsed("ALTER TABLE $table_fetchmail  CHANGE `date`  `date` {DATE}");
}

/**
 * @return void
 */
function upgrade_1762_mysql_pgsql() {
    _db_add_field('fetchmail', 'domain', "VARCHAR(255) {LATIN1} DEFAULT ''", 'id');
    _db_add_field('fetchmail', 'active', '{BOOLEAN}', 'date');
    _db_add_field('fetchmail', 'created', '{DATE}', 'date');

    # If you followed SVN and got upgrade failures here, you might need to
    #    UPDATE config SET value=1760 WHERE name='version';
    # and run setup.php again (upgrade_1761_mysql was added later).
    _db_add_field('fetchmail', 'modified', '{DATECURRENT}', 'created');
}

/**
 * @return void
 */
function upgrade_1763_mysql() {
    $table = table_by_key('fetchmail');
    db_query_parsed("UPDATE $table SET domain=SUBSTRING_INDEX(mailbox, '@', -1) WHERE domain='';");
}


/**
 * @return void
 */
function upgrade_1763_pgsql() {
    $table = table_by_key('fetchmail');
    db_query_parsed("UPDATE $table SET domain=SPLIT_PART(mailbox, '@', 2) WHERE domain='';");
}

/**
 * @return void
 */
function upgrade_1767_mysql_pgsql() {
    # 'active' was just added, so make sure all existing jobs stay active
    $table = table_by_key('fetchmail');
    db_query_parsed("UPDATE $table SET active='{BOOL_TRUE}'");
}

/**
 * @return void
 */
function upgrade_1795_mysql() {
    # upgrade_1761_mysql() was added later (in r1795) - make sure it runs for everybody
    # (running it twice doesn't hurt)
    upgrade_1761_mysql();
}

/**
 * @return void
 */
function upgrade_1824_sqlite() {
    $admin_table = table_by_key('admin');
    $alias_table = table_by_key('alias');
    $alias_domain_table = table_by_key('alias_domain');
    $domain_table = table_by_key('domain');
    $domain_admins_table = table_by_key('domain_admins');
    $fetchmail_table = table_by_key('fetchmail');
    $log_table = table_by_key('log');
    $mailbox_table = table_by_key('mailbox');
    $quota_table = table_by_key('quota');
    $quota2_table = table_by_key('quota2');
    $vacation_table = table_by_key('vacation');
    $vacation_notification_table = table_by_key('vacation_notification');

    db_query_parsed("
      CREATE TABLE {IF_NOT_EXISTS} $admin_table (
          `username` varchar(255) NOT NULL,
          `password` varchar(255) NOT NULL,
          `superadmin` {BOOLEAN},
          `created` {DATE},
          `modified` {DATE},
          `active` {BOOLEAN_TRUE},
          {PRIMARY} (`username`));
    ");

    db_query_parsed("
      CREATE TABLE {IF_NOT_EXISTS} $alias_table (
          `address` varchar(255) NOT NULL,
          `goto` {FULLTEXT} NOT NULL,
          `domain` varchar(255) NOT NULL,
          `created` {DATE},
          `modified` {DATE},
          `active` {BOOLEAN_TRUE},
          {PRIMARY} (`address`));
    ");

    db_query_parsed("
      CREATE TABLE {IF_NOT_EXISTS} $alias_domain_table (
          `alias_domain` varchar(255) NOT NULL,
          `target_domain` varchar(255) NOT NULL,
          `created` {DATE},
          `modified` {DATE},
          `active` {BOOLEAN_TRUE},
          {PRIMARY} (`alias_domain`));
    ");

    db_query_parsed("
      CREATE TABLE {IF_NOT_EXISTS} $domain_table (
          `domain` varchar(255) NOT NULL,
          `description` varchar(255) NOT NULL,
          `aliases` {INT},
          `mailboxes` {INT},
          `maxquota` {BIGINT},
          `quota` {BIGINT},
          `transport` varchar(255) NOT NULL,
          `backupmx` {BOOLEAN},
          `created` {DATE},
          `modified` {DATE},
          `active` {BOOLEAN_TRUE},
          {PRIMARY} (`domain`));
    ");

    db_query_parsed("
      CREATE TABLE {IF_NOT_EXISTS}  $domain_admins_table (
          `username` varchar(255) NOT NULL,
          `domain` varchar(255) NOT NULL,
          `created` {DATE},
          `active` {BOOLEAN_TRUE});
    ");

    db_query_parsed("
      CREATE TABLE {IF_NOT_EXISTS} $fetchmail_table (
          `id` {AUTOINCREMENT},
          `domain` varchar(255) DEFAULT '',
          `mailbox` varchar(255) NOT NULL,
          `src_server` varchar(255) NOT NULL,
          `src_auth` varchar(255) DEFAULT NULL,
          `src_user` varchar(255) NOT NULL,
          `src_password` varchar(255) NOT NULL,
          `src_folder` varchar(255) NOT NULL,
          `poll_time` int(11)  NOT NULL DEFAULT '10',
          `fetchall` {BOOLEAN},
          `keep` {BOOLEAN},
          `protocol` {FULLTEXT} DEFAULT NULL,
          `usessl` {BOOLEAN},
          `sslcertck` {BOOLEAN},
          `sslcertpath` varchar(255) DEFAULT '',
          `sslfingerprint` varchar(255) DEFAULT '',
          `extra_options` {FULLTEXT},
          `returned_text` {FULLTEXT},
          `mda` varchar(255) NOT NULL,
          `date` {DATE},
          `created` {DATE},
          `modified` {DATECURRENT},
          `active` {BOOLEAN});
    ");

    db_query_parsed("
      CREATE TABLE {IF_NOT_EXISTS} $log_table (
          `timestamp` {DATE},
          `username` varchar(255) NOT NULL,
          `domain` varchar(255) NOT NULL,
          `action` varchar(255) NOT NULL,
          `data` {FULLTEXT} NOT NULL);
    ");

    db_query_parsed("
      CREATE TABLE {IF_NOT_EXISTS} $mailbox_table (
          `username` varchar(255) NOT NULL,
          `password` varchar(255) NOT NULL,
          `name` varchar(255) NOT NULL,
          `maildir` varchar(255) NOT NULL,
          `quota` {BIGINT},
          `local_part` varchar(255) NOT NULL,
          `domain` varchar(255) NOT NULL,
          `created` {DATE},
          `modified` {DATE},
          `active` {BOOLEAN_TRUE},
          {PRIMARY} (`username`));
    ");

    db_query_parsed("
      CREATE TABLE {IF_NOT_EXISTS} $quota_table (
          `username` varchar(255) NOT NULL,
          `path` varchar(100) NOT NULL,
          `current` {BIGINT},
          {PRIMARY} (`username`,`path`));
    ");

    db_query_parsed("
      CREATE TABLE {IF_NOT_EXISTS} $quota2_table (
          `username` varchar(255) NOT NULL,
          `bytes` {BIGINT},
          `messages` {INT},
          {PRIMARY} (`username`));
    ");

    db_query_parsed("
      CREATE TABLE {IF_NOT_EXISTS} $vacation_table (
          `email` varchar(255) NOT NULL,
          `subject` varchar(255) NOT NULL,
          `body` {FULLTEXT} NOT NULL,
          `activefrom` {DATE},
          `activeuntil` {DATEFUTURE},
          `cache` {FULLTEXT} NOT NULL DEFAULT '',
          `domain` varchar(255) NOT NULL,
          `interval_time` {INT},
          `created` {DATE},
          `modified` {DATECURRENT},
          `active` {BOOLEAN_TRUE},
          {PRIMARY} (`email`));
    ");

    db_query_parsed("
      CREATE TABLE {IF_NOT_EXISTS} $vacation_notification_table (
          `on_vacation` varchar(255) NOT NULL,
          `notified` varchar(255) NOT NULL,
          `notified_at` {DATECURRENT},
          {PRIMARY} (`on_vacation`,`notified`),
          CONSTRAINT `vacation_notification_pkey` FOREIGN KEY (`on_vacation`) REFERENCES `vacation` (`email`) ON DELETE CASCADE);
    ");
}


/**
 * @return void
 */
function upgrade_1835_mysql() {
    # change default values for existing datetime fields with a 0000-00-00 default to {DATETIME}

    foreach (array('domain_admins', 'vacation') as $table_to_change) {
        $table = table_by_key($table_to_change);
        db_query_parsed("ALTER TABLE $table CHANGE `created` `created` {DATETIME}");
    }

    foreach (array('admin', 'alias', 'alias_domain', 'domain', 'mailbox') as $table_to_change) {
        $table = table_by_key($table_to_change);
        db_query_parsed("ALTER TABLE $table CHANGE `created` `created` {DATETIME}, CHANGE `modified` `modified` {DATETIME}");
    }

    $table = table_by_key('log');
    db_query_parsed("ALTER TABLE $table CHANGE `timestamp` `timestamp` {DATETIME}");
}

/**
 * @return void
 */
function upgrade_1836_mysql() {
    $table_alias_domain = table_by_key('alias_domain');
    $table_vacation_notification = table_by_key('vacation_notification');

    $all_sql = explode("\n", trim("
        ALTER TABLE $table_alias_domain          CHANGE `alias_domain`    `alias_domain`  VARCHAR(255) {LATIN1} NOT NULL default ''
        ALTER TABLE $table_alias_domain          CHANGE `target_domain`   `target_domain` VARCHAR(255) {LATIN1} NOT NULL default ''
        ALTER TABLE $table_vacation_notification CHANGE `notified`        `notified`      VARCHAR(255) {LATIN1} NOT NULL default ''
    "));

    foreach ($all_sql as $sql) {
        db_query_parsed($sql, true);
    }
}

/**
 * @return void
 */
function upgrade_1837() {
    if (db_sqlite()) {
        return;
    }
    # alternative contact means to reset a forgotten password
    foreach (array('admin', 'mailbox') as $table) {
        _db_add_field($table, 'phone', "varchar(30) {UTF-8} NOT NULL DEFAULT ''", 'active');
        _db_add_field($table, 'email_other', "varchar(255) {UTF-8} NOT NULL DEFAULT ''", 'phone');
        _db_add_field($table, 'token', "varchar(255) {UTF-8} NOT NULL DEFAULT ''", 'email_other');
        _db_add_field($table, 'token_validity', '{DATETIME}', 'token');
    }
}
# TODO MySQL:
# - various varchar fields do not have a default value
#   https://sourceforge.net/projects/postfixadmin/forums/forum/676076/topic/3419725
# - change default of all timestamp fields to {DATECURRENT} (CURRENT_TIMESTAMP} or {DATE}
#   including vacation.activefrom/activeuntil (might have a different default as leftover from upgrade_727_mysql)
#   including vacation.modified - should be {DATE}, not {DATECURRENT}
#   https://sourceforge.net/tracker/?func=detail&aid=1699218&group_id=191583&atid=937964
# @todo vacation.email has 2 indizes


# Upgrading to v1835 & v1836 in sqlite have a couple of peculiarities:
# - DATE and DATETIME are the same type internally (NUMERIC)
# - SQLite does not support ALTER COLUMN. At all.
# TODO: Rename/create anew/migrate/drop tables for v1836... If it matters?

/**
 * @return void
 */
function upgrade_1837_sqlite() {
    # Add columns for the alternative contact to reset a forgotten password.
    foreach (array('admin', 'mailbox') as $table_to_change) {
        $table = table_by_key($table_to_change);
        if (!_sqlite_field_exists($table, 'phone')) {
            db_query_parsed("ALTER TABLE `$table` ADD COLUMN `phone` varchar(30) NOT NULL DEFAULT ''");
        }
        if (!_sqlite_field_exists($table, 'email_other')) {
            db_query_parsed("ALTER TABLE `$table` ADD COLUMN `email_other` varchar(255) NOT NULL DEFAULT ''");
        }
    }
}

/**
 * https://github.com/postfixadmin/postfixadmin/issues/89
 * upgrade_1838_mysql() renamed to upgrade_1839() to keep all databases in sync
 * @return void
 */
function upgrade_1839() {
    if (!db_sqlite()) {
        _db_add_field('log', 'id', '{AUTOINCREMENT} {PRIMARY}', 'data');
        return;
    }

    /* ONLY FOR Sqlite */
    // probably didn't have a working log table, so drop it and recreate with an id field.
    $log_table = table_by_key('log');
    db_query_parsed("DROP TABLE IF EXISTS $log_table");

    db_query_parsed("
         CREATE TABLE $log_table (
          `id` {AUTOINCREMENT},
          `timestamp` {DATE},
          `username` varchar(255) NOT NULL,
          `domain` varchar(255) NOT NULL,
          `action` varchar(255) NOT NULL,
          `data` {FULLTEXT} NOT NULL);
");
}

/**
 * @return void
 */
function upgrade_1840_mysql_pgsql() {
    # sqlite doesn't support changing the default value
    $vacation = table_by_key('vacation');
    db_query_parsed("ALTER TABLE $vacation ALTER COLUMN activeuntil SET DEFAULT '2038-01-18'");
}

/**
 * try and fix: https://github.com/postfixadmin/postfixadmin/issues/177 - sqlite missing columns
 * @return void
 */
function upgrade_1841_sqlite() {
    foreach (array('admin', 'mailbox') as $table) {
        _db_add_field($table, 'phone', "varchar(30) {UTF-8} NOT NULL DEFAULT ''", 'active');
        _db_add_field($table, 'email_other', "varchar(255) {UTF-8} NOT NULL DEFAULT ''", 'phone');
        _db_add_field($table, 'token', "varchar(255) {UTF-8} NOT NULL DEFAULT ''", 'email_other');
        _db_add_field($table, 'token_validity', '{DATETIME}', 'token');
    }
}

/**
 * @return void
 */
function upgrade_1842() {
    $domain = table_by_key('domain');

    // See: https://github.com/postfixadmin/postfixadmin/issues/489
    // Avoid : ERROR 1292 (22007): Incorrect datetime value: '0000-00-00 00:00:00' for column 'created' at row 1
    if (db_mysql()) {
        db_execute("UPDATE $domain SET created='2000-01-01 00:00:00', modified='2000-01-01 00:00:00' WHERE domain='ALL'", [], true);
    }

    _db_add_field('mailbox', 'password_expiry', "{DATETIME}"); // when a specific mailbox password expires
    _db_add_field('domain',  'password_expiry', 'int DEFAULT 0'); // expiry applied to mailboxes within that domain
}

/**
 * @return void
 */
function upgrade_1843() {
    # Additional field for fetchmail to allow server with non-standard port number
    _db_add_field('fetchmail', 'src_port', "{INT}", 'src_server');
}


/**
 * @return void
 */
function upgrade_1844() {
    # See:https://github.com/postfixadmin/postfixadmin/issues/475 - add pkey to domain_admins.

    if (db_sqlite()) {
        return;
    }

    _db_add_field('domain_admins', 'id', '{AUTOINCREMENT} {PRIMARY}');
}

function upgrade_1845() {
    # See: https://github.com/postfixadmin/postfixadmin/pull/484
    #
    if (!db_mysql()) {
        return;
    }

    $vacation = table_by_key('vacation');
    $mailbox = table_by_key('mailbox');
    db_query("alter table $vacation change body body text charset utf8mb4 not null");
    db_query("alter table $vacation change subject subject varchar(255) charset utf8mb4 not null");
    db_query("alter table $mailbox change name name varchar(255) charset utf8mb4 not null");
}

function upgrade_1846_mysql() {
    # See https://github.com/postfixadmin/postfixadmin/issues/327

    $alias = table_by_key('alias');
    $domain = table_by_key('domain');
    $mailbox = table_by_key('mailbox');
    $vacation = table_by_key('vacation');
    $vacation_notification = table_by_key('vacation_notification');
    $alias_domain = table_by_key('alias_domain');
    $domain_admins = table_by_key('domain_admins');

    db_query("ALTER TABLE $alias MODIFY address varchar(255)  COLLATE latin1_general_ci NOT NULL");
    db_query("ALTER TABLE $alias MODIFY goto text  COLLATE latin1_general_ci NOT NULL");
    db_query("ALTER TABLE $alias MODIFY domain varchar(255)  COLLATE latin1_general_ci NOT NULL");
    db_query("ALTER TABLE $domain MODIFY domain varchar(255)  COLLATE latin1_general_ci NOT NULL");
    db_query("ALTER TABLE $domain MODIFY transport varchar(255)  COLLATE latin1_general_ci NOT NULL");
    db_query("ALTER TABLE $mailbox MODIFY username varchar(255)  COLLATE latin1_general_ci NOT NULL");
    db_query("ALTER TABLE $mailbox MODIFY password varchar(255)  COLLATE latin1_general_ci NOT NULL");
    db_query("ALTER TABLE $mailbox MODIFY maildir varchar(255)  COLLATE latin1_general_ci NOT NULL");
    db_query("ALTER TABLE $mailbox MODIFY local_part varchar(255)  COLLATE latin1_general_ci NOT NULL");
    db_query("ALTER TABLE $mailbox MODIFY domain varchar(255)  COLLATE latin1_general_ci NOT NULL");
    db_query("ALTER TABLE $vacation_notification DROP FOREIGN KEY vacation_notification_pkey");
    db_query("ALTER TABLE $vacation MODIFY domain varchar(255)  COLLATE latin1_general_ci NOT NULL");
    db_query("ALTER TABLE $vacation MODIFY email varchar(255)  COLLATE latin1_general_ci NOT NULL");
    db_query("ALTER TABLE $alias_domain MODIFY alias_domain varchar(255)  COLLATE latin1_general_ci NOT NULL DEFAULT ''");
    db_query("ALTER TABLE $alias_domain MODIFY target_domain varchar(255)  COLLATE latin1_general_ci NOT NULL DEFAULT ''");

    db_query("ALTER TABLE $vacation_notification MODIFY on_vacation VARCHAR(255) COLLATE latin1_general_ci NOT NULL");
    db_query("ALTER TABLE $vacation_notification ADD CONSTRAINT vacation_notification_pkey FOREIGN KEY (`on_vacation`) REFERENCES $vacation(email) ON DELETE CASCADE");

    db_query("ALTER TABLE $domain_admins MODIFY `domain` varchar(255) COLLATE latin1_general_ci NOT NULL");
}

function upgrade_1847_mysql() {
    # See https://github.com/postfixadmin/postfixadmin/issues/327
    # See https://github.com/postfixadmin/postfixadmin/issues/690
    #
    #

    foreach (['quota','quota2'] as $table) {
        $table = table_by_key($table);
        db_query("ALTER TABLE $table MODIFY username varchar(255)  COLLATE latin1_general_ci NOT NULL");
    }
}
