<?php
if(!defined('POSTFIXADMIN')) {
    require_once('common.php');
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
# @version $Id$ 

# Note: run with upgrade.php?debug=1 to see all SQL error messages


/** 
 * Use this to check whether an object (Table, index etc) exists within a 
 * PostgreSQL database.
 * @param String the object name
 * @return boolean true if it exists
 */
function _pgsql_object_exists($name) {
    $sql = "select relname from pg_class where relname = '$name'";
    $r = db_query($sql);
    if($r['rows'] == 1) {
        return true;
    }
    return false;
}

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
    $r = db_query($sql);
    $row = db_row($r['result']);
    if($row) {
        return true;
    }
    return false;
}

function _mysql_field_exists($table, $field) {
    # $table = table_by_key($table); # _mysql_field_exists is always called with the expanded table name - don't expand it twice
    $sql = "SHOW COLUMNS FROM $table LIKE '$field'";
    $r = db_query($sql);
    $row = db_row($r['result']);

    if($row) {
        return true;
    }
    return false;
}

function _db_field_exists($table, $field) {
    global $CONF;
    if($CONF['database_type'] == 'pgsql') {
        return _pgsql_field_exists($table, $field); 
    } else {
        return _mysql_field_exists($table, $field);
    }
}
function _upgrade_filter_function($name) {
    return preg_match('/upgrade_[\d]+(_mysql|_pgsql|_sqlite|_mysql_pgsql)?$/', $name) == 1; 
}

function _db_add_field($table, $field, $fieldtype, $after) {
    global $CONF;

    $query = "ALTER TABLE " . table_by_key($table) . " ADD COLUMN $field $fieldtype";
    if($CONF['database_type'] != 'pgsql') {
        $query .= " AFTER $after "; # PgSQL does not support to specify where to add the column, MySQL does
    }

    if(! _db_field_exists(table_by_key($table), $field)) {
        $result = db_query_parsed($query);
    } else { 
        printdebug ("field already exists: $table.$field");
    }
}

function printdebug($text) {
    if (safeget('debug') != "") print "<p style='color:#999'>$text</p>";
}

$table = table_by_key('config');
if($CONF['database_type'] == 'pgsql') {
    // check if table already exists, if so, don't recreate it
    $r = db_query("SELECT relname FROM pg_class WHERE relname = '$table'");
    if($r['rows'] == 0) {
        $pgsql = "
            CREATE TABLE  $table ( 
                    id SERIAL,
                    name VARCHAR(20) NOT NULL UNIQUE,
                    value VARCHAR(20) NOT NULL,
                    PRIMARY KEY(id)
                    )";
        db_query_parsed($pgsql);
    }
} elseif(db_sqlite()) {
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
    db_query_parsed($mysql, 0, " ENGINE = MYISAM COMMENT = 'PostfixAdmin settings'");
}

$version = check_db_version(False);
_do_upgrade($version);

function _do_upgrade($current_version) {
    global $CONF;

    $target_version = 0;
    // Rather than being bound to an svn revision number, just look for the largest function name that matches upgrade_\d+...
    // $target_version = preg_replace('/[^0-9]/', '', '$Revision$');
    $funclist = get_defined_functions();
    $our_upgrade_functions = array_filter($funclist['user'], '_upgrade_filter_function');
    foreach($our_upgrade_functions as $function_name) {
        $bits = explode("_", $function_name);
        $function_number = $bits[1];
        if($function_number > $current_version && $function_number > $target_version) {
            $target_version = $function_number;
        }
    }

    if ($current_version >= $target_version) {
        # already up to date
        echo "<p>Database is up to date</p>";
        return true;
    }

    echo "<p>Updating database:</p><p>- old version: $current_version; target version: $target_version</p>\n";
    echo "<div style='color:#999'>&nbsp;&nbsp;(If the update doesn't work, run setup.php?debug=1 to see the detailed error messages and SQL queries.)</div>";

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
            echo "<p>updating to version $i (all databases)...";
            $function();
            echo " &nbsp; done";
        }
        if ($CONF['database_type'] == 'mysql' || $CONF['database_type'] == 'mysqli' || $CONF['database_type'] == 'pgsql') {
            if (function_exists($function_mysql_pgsql)) {
                echo "<p>updating to version $i (MySQL and PgSQL)...";
                $function_mysql_pgsql();
                echo " &nbsp; done";
            }
        }
        if ($CONF['database_type'] == 'mysql' || $CONF['database_type'] == 'mysqli' ) {
            if (function_exists($function_mysql)) {
                echo "<p>updating to version $i (MySQL)...";
                $function_mysql();
                echo " &nbsp; done";
            }
        } elseif(db_sqlite()) {
            if (function_exists($function_sqlite)) {
                echo "<p>updating to version $i (SQLite)...";
                $function_sqlite();
                echo " &nbsp; done";
            }
        } elseif($CONF['database_type'] == 'pgsql') {
            if (function_exists($function_pgsql)) {
                echo "<p>updating to version $i (PgSQL)...";
                $function_pgsql();
                echo " &nbsp; done";
            }
        } 
        // Update config table so we don't run the same query twice in the future.
        $i = (int) $i;
        $table = table_by_key('config');
        $sql = "UPDATE $table SET value = $i WHERE name = 'version'";
        db_query($sql);
    };
}

/**
 * Replaces database specific parts in a query
 * @param String sql query with placeholders
 * @param int (optional) whether errors should be ignored (0=false)
 * @param String (optional) MySQL specific code to attach, useful for COMMENT= on CREATE TABLE
 * @return String sql query
 */ 

function db_query_parsed($sql, $ignore_errors = 0, $attach_mysql = "") {
    global $CONF;

    if ($CONF['database_type'] == 'mysql' || $CONF['database_type'] == 'mysqli' ) {

        $replace = array(
                '{AUTOINCREMENT}'   => 'int(11) not null auto_increment', 
                '{PRIMARY}'         => 'primary key',
                '{UNSIGNED}'        => 'unsigned'  , 
                '{FULLTEXT}'        => 'FULLTEXT', 
                '{BOOLEAN}'         => "tinyint(1) NOT NULL DEFAULT '" . db_get_boolean(False) . "'",
                '{UTF-8}'           => '/*!40100 CHARACTER SET utf8 */',
                '{LATIN1}'          => '/*!40100 CHARACTER SET latin1 */',
                '{IF_NOT_EXISTS}'   => 'IF NOT EXISTS',
                '{RENAME_COLUMN}'   => 'CHANGE COLUMN',
                '{MYISAM}'          => 'ENGINE=MyISAM',
                '{INNODB}'          => 'ENGINE=InnoDB',
                '{INT}'             => 'integer NOT NULL DEFAULT 0',
                '{BIGINT}'          => 'bigint NOT NULL DEFAULT 0',
                '{DATETIME}'        => "datetime NOT NULL default '2000-01-01 00:00:00'", # different from {DATE} only for MySQL
                '{DATE}'            => "timestamp NOT NULL default '2000-01-01'", # MySQL needs a sane default (no default is interpreted as CURRENT_TIMESTAMP, which is ...
                '{DATECURRENT}'     => 'timestamp NOT NULL default CURRENT_TIMESTAMP', # only allowed once per table in MySQL
        );
        $sql = "$sql $attach_mysql";

    } elseif(db_sqlite()) {
        $replace = array(
                '{AUTOINCREMENT}'   => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL',
                '{PRIMARY}'         => 'PRIMARY KEY',
                '{UNSIGNED}'        => 'unsigned',
                '{FULLTEXT}'        => 'text',
                '{BOOLEAN}'         => "tinyint(1) NOT NULL DEFAULT '" . db_get_boolean(False) . "'",
                '{BOOLEAN_TRUE}'    => "tinyint(1) NOT NULL DEFAULT '" . db_get_boolean(True) . "'",
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
                '{DATECURRENT}'     => 'datetime NOT NULL default CURRENT_TIMESTAMP',
        );
    } elseif($CONF['database_type'] == 'pgsql') {
        $replace = array(
                '{AUTOINCREMENT}'   => 'SERIAL', 
                '{PRIMARY}'         => 'primary key', 
                '{UNSIGNED}'        => '', 
                '{FULLTEXT}'        => '', 
                '{BOOLEAN}'         => "BOOLEAN NOT NULL DEFAULT '" . db_get_boolean(False) . "'",
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
                '{DATECURRENT}'     => 'timestamp with time zone default now()',
        );

    } else {
        echo "Sorry, unsupported database type " . $conf['database_type'];
        exit;
    }

    $replace['{BOOL_TRUE}'] = db_get_boolean(True);
    $replace['{BOOL_FALSE}'] = db_get_boolean(False);

    $query = trim(str_replace(array_keys($replace), $replace, $sql));

    if (safeget('debug') != "") {
        printdebug ($query);
    }
    $result = db_query($query, $ignore_errors);
    if (safeget('debug') != "") {
        print "<div style='color:#f00'>" . $result['error'] . "</div>";
    }
    return $result;
}

function _drop_index ($table, $index) {
    global $CONF;
    $table = table_by_key ($table);

    if ($CONF['database_type'] == 'mysql' || $CONF['database_type'] == 'mysqli' ) {
        return "ALTER TABLE $table DROP INDEX $index";
    } elseif($CONF['database_type'] == 'pgsql' || db_sqlite()) {
        return "DROP INDEX $index"; # Index names are unique with a DB for PostgreSQL
    } else {
        echo "Sorry, unsupported database type " . $conf['database_type'];
        exit;
    }
}

function _add_index($table, $indexname, $fieldlist) {
    global $CONF;
    $table = table_by_key ($table);

    if ($CONF['database_type'] == 'mysql' || $CONF['database_type'] == 'mysqli' ) {
        $fieldlist = str_replace(',', '`,`', $fieldlist); # fix quoting if index contains multiple fields
        return "ALTER TABLE $table ADD INDEX `$indexname` ( `$fieldlist` )";
    } elseif($CONF['database_type'] == 'pgsql') {
        $pgindexname = $table . "_" . $indexname . '_idx';
        return "CREATE INDEX $pgindexname ON $table($fieldlist);"; # Index names are unique with a DB for PostgreSQL
    } else {
        echo "Sorry, unsupported database type " . $conf['database_type'];
        exit;
    }

}

function upgrade_1_mysql() {
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
  ) {MYISAM} COMMENT='Postfix Admin - Virtual Admins';";

    $sql[] = "
    CREATE TABLE {IF_NOT_EXISTS} $alias (
      `address` varchar(255) NOT NULL default '',
      `goto` text NOT NULL,
      `domain` varchar(255) NOT NULL default '',
      `created` {DATETIME},
      `modified` {DATETIME},
      `active` tinyint(1) NOT NULL default '1',
      PRIMARY KEY  (`address`)
    ) {MYISAM} COMMENT='Postfix Admin - Virtual Aliases'; ";

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
    ) {MYISAM} COMMENT='Postfix Admin - Virtual Domains'; ";

    $sql[] = "
    CREATE TABLE {IF_NOT_EXISTS} $domain_admins (
      `username` varchar(255) NOT NULL default '',
      `domain` varchar(255) NOT NULL default '',
      `created` {DATETIME},
      `active` tinyint(1) NOT NULL default '1',
      KEY username (`username`)
    ) {MYISAM} COMMENT='Postfix Admin - Domain Admins';";

    $sql[] = "
    CREATE TABLE {IF_NOT_EXISTS} $log (
      `timestamp` {DATETIME},
      `username` varchar(255) NOT NULL default '',
      `domain` varchar(255) NOT NULL default '',
      `action` varchar(255) NOT NULL default '',
      `data` varchar(255) NOT NULL default '',
      KEY timestamp (`timestamp`)
    ) {MYISAM} COMMENT='Postfix Admin - Log';";

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
    ) {MYISAM} COMMENT='Postfix Admin - Virtual Mailboxes';";

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
    ) {INNODB} DEFAULT CHARSET=latin1 COMMENT='Postfix Admin - Virtual Vacation' ;";

    foreach($sql as $query) {
        db_query_parsed($query);
    }
}

function upgrade_2_mysql() {
    # upgrade pre-2.1 database
    # from TABLE_BACKUP_MX.TXT
    $table_domain = table_by_key ('domain');
    if(!_mysql_field_exists($table_domain, 'transport')) {
        $result = db_query_parsed("ALTER TABLE $table_domain ADD COLUMN transport VARCHAR(255) AFTER maxquota;", TRUE);
    }
    if(!_mysql_field_exists($table_domain, 'backupmx')) {
        $result = db_query_parsed("ALTER TABLE $table_domain ADD COLUMN backupmx {BOOLEAN} AFTER transport;", TRUE);
    }
}

function upgrade_2_pgsql() {

    if(!_pgsql_object_exists(table_by_key('domain'))) {
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
            ); 
            CREATE INDEX domain_domain_active ON " . table_by_key('domain') . "(domain,active);
            COMMENT ON TABLE " . table_by_key('domain') . " IS 'Postfix Admin - Virtual Domains';
        ");
    }
    if(!_pgsql_object_exists(table_by_key('admin'))) {
        db_query_parsed('
            CREATE TABLE ' . table_by_key("admin") . ' (
              "username" character varying(255) NOT NULL,
              "password" character varying(255) NOT NULL default \'\',
              "created" timestamp with time zone default now(),
              "modified" timestamp with time zone default now(),
              "active" boolean NOT NULL default true,
            Constraint "admin_key" Primary Key ("username")
        );' . "
        COMMENT ON TABLE " . table_by_key('admin') . " IS 'Postfix Admin - Virtual Admins';
        ");
    }

    if(!_pgsql_object_exists(table_by_key('alias'))) {
        db_query_parsed('
            CREATE TABLE ' . table_by_key("alias") . ' (
             address character varying(255) NOT NULL,
             goto text NOT NULL,
             domain character varying(255) NOT NULL REFERENCES "' . table_by_key("domain") . '",
             created timestamp with time zone default now(),
             modified timestamp with time zone default now(),
             active boolean NOT NULL default true,
             Constraint "alias_key" Primary Key ("address")
            );
            CREATE INDEX alias_address_active ON ' . table_by_key("alias") . '(address,active);
            COMMENT ON TABLE ' . table_by_key("alias") . ' IS \'Postfix Admin - Virtual Aliases\';
        ');
    }

    if(!_pgsql_object_exists(table_by_key('domain_admins'))) {
        db_query_parsed('
        CREATE TABLE ' . table_by_key('domain_admins') . ' (
             username character varying(255) NOT NULL,
             domain character varying(255) NOT NULL REFERENCES "' . table_by_key('domain') . '",
             created timestamp with time zone default now(),
             active boolean NOT NULL default true
            );
        COMMENT ON TABLE ' . table_by_key('domain_admins') . ' IS \'Postfix Admin - Domain Admins\';
        '); 
    }
    
    if(!_pgsql_object_exists(table_by_key('log'))) {
        db_query_parsed('
            CREATE TABLE ' . table_by_key('log') . ' (
             timestamp timestamp with time zone default now(),
             username character varying(255) NOT NULL default \'\',
             domain character varying(255) NOT NULL default \'\',
             action character varying(255) NOT NULL default \'\',
             data text NOT NULL default \'\'
            );
            COMMENT ON TABLE ' . table_by_key('log') . ' IS \'Postfix Admin - Log\';
        ');
    }
    if(!_pgsql_object_exists(table_by_key('mailbox'))) {
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
                );
                CREATE INDEX mailbox_username_active ON ' . table_by_key('mailbox') . '(username,active);
                COMMENT ON TABLE ' . table_by_key('mailbox') . ' IS \'Postfix Admin - Virtual Mailboxes\';
        ');
    }

    if(!_pgsql_object_exists(table_by_key('vacation'))) {
        db_query_parsed('
            CREATE TABLE ' . table_by_key('vacation') . ' (
                email character varying(255) PRIMARY KEY,
                subject character varying(255) NOT NULL,
                body text NOT NULL ,
                cache text NOT NULL ,
                "domain" character varying(255) NOT NULL REFERENCES "' . table_by_key('domain') . '",
                created timestamp with time zone DEFAULT now(),
                active boolean DEFAULT true NOT NULL
            );
            CREATE INDEX vacation_email_active ON ' . table_by_key('vacation') . '(email,active);');
    }

    if(!_pgsql_object_exists(table_by_key('vacation_notification'))) {
        db_query_parsed('
            CREATE TABLE ' . table_by_key('vacation_notification') . ' (
                on_vacation character varying(255) NOT NULL REFERENCES ' . table_by_key('vacation') . '(email) ON DELETE CASCADE,
                notified character varying(255) NOT NULL,
                notified_at timestamp with time zone NOT NULL DEFAULT now(),
                CONSTRAINT vacation_notification_pkey primary key(on_vacation,notified)
            );
        ');
    }

    // this handles anyone who is upgrading... (and should have no impact on new installees)
    $table_domain = table_by_key ('domain');
    $result = db_query_parsed("ALTER TABLE $table_domain ADD COLUMN transport VARCHAR(255)", TRUE);
    $result = db_query_parsed("ALTER TABLE $table_domain ADD COLUMN backupmx BOOLEAN DEFAULT false", TRUE);
}

function upgrade_3_mysql() {
    # upgrade pre-2.1 database
    # from TABLE_CHANGES.TXT
    $table_admin = table_by_key ('admin');
    $table_alias = table_by_key ('alias');
    $table_domain = table_by_key ('domain');
    $table_mailbox = table_by_key ('mailbox');
    $table_vacation = table_by_key ('vacation');

    if(!_mysql_field_exists($table_admin, 'created')) {
        db_query_parsed("ALTER TABLE $table_admin {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if(!_mysql_field_exists($table_admin, 'modified')) {
        db_query_parsed("ALTER TABLE $table_admin {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if(!_mysql_field_exists($table_alias, 'created')) {
        db_query_parsed("ALTER TABLE $table_alias {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if(!_mysql_field_exists($table_alias, 'modified')) {
        db_query_parsed("ALTER TABLE $table_alias {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if(!_mysql_field_exists($table_domain, 'created')) {
        db_query_parsed("ALTER TABLE $table_domain {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if(!_mysql_field_exists($table_domain, 'modified')) {
        db_query_parsed("ALTER TABLE $table_domain {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if(!_mysql_field_exists($table_domain, 'aliases')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN aliases INT(10) DEFAULT '-1' NOT NULL AFTER description;");
    }
    if(!_mysql_field_exists($table_domain, 'mailboxes')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN mailboxes INT(10) DEFAULT '-1' NOT NULL AFTER aliases;");
    }
    if(!_mysql_field_exists($table_domain, 'maxquota')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN maxquota INT(10) DEFAULT '-1' NOT NULL AFTER mailboxes;");
    }
    if(!_mysql_field_exists($table_domain, 'transport')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN transport VARCHAR(255) AFTER maxquota;");
    }
    if(!_mysql_field_exists($table_domain, 'backupmx')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN backupmx TINYINT(1) DEFAULT '0' NOT NULL AFTER transport;");
    }
    if(!_mysql_field_exists($table_mailbox, 'created')) {
        db_query_parsed("ALTER TABLE $table_mailbox {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if(!_mysql_field_exists($table_mailbox, 'modified')) {
        db_query_parsed("ALTER TABLE $table_mailbox {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if(!_mysql_field_exists($table_mailbox, 'quota')) {
        db_query_parsed("ALTER TABLE $table_mailbox ADD COLUMN quota INT(10) DEFAULT '-1' NOT NULL AFTER maildir;");
    }
    if(!_mysql_field_exists($table_vacation, 'domain')) {
        db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN domain VARCHAR(255) DEFAULT '' NOT NULL AFTER cache;");
    }
    if(!_mysql_field_exists($table_vacation, 'created')) {
        db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN created {DATETIME} AFTER domain;");
    }
    if(!_mysql_field_exists($table_vacation, 'active')) {
        db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN active TINYINT(1) DEFAULT '1' NOT NULL AFTER created;");
    }
    db_query_parsed("ALTER TABLE $table_vacation DROP PRIMARY KEY");
    db_query_parsed("ALTER TABLE $table_vacation ADD PRIMARY KEY(email)");
    db_query_parsed("UPDATE $table_vacation SET domain=SUBSTRING_INDEX(email, '@', -1) WHERE email=email;");
}

function upgrade_4_mysql() { # MySQL only
    # changes between 2.1 and moving to sourceforge
    $table_domain = table_by_key ('domain');
    $result = db_query_parsed("ALTER TABLE $table_domain ADD COLUMN quota int(10) NOT NULL default '0' AFTER maxquota", TRUE);
    # Possible errors that can be ignored:
    # - Invalid query: Table 'postfix.domain' doesn't exist
}

/**
 * Changes between 2.1 and moving to sf.net
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

    if(!_pgsql_field_exists($table_domain, 'quota')) {
        $result = db_query_parsed("ALTER TABLE $table_domain ADD COLUMN quota int NOT NULL default '0'");
    }

    $result = db_query_parsed("ALTER TABLE $table_domain ALTER COLUMN domain DROP DEFAULT");
    if(!_pgsql_object_exists('domain_domain_active')) {
        $result = db_query_parsed("CREATE INDEX domain_domain_active ON $table_domain(domain,active)");
    }

    $result = db_query_parsed("ALTER TABLE $table_domain_admins ALTER COLUMN domain DROP DEFAULT");
    $result = db_query_parsed("ALTER TABLE $table_alias ALTER COLUMN address DROP DEFAULT");
    $result = db_query_parsed("ALTER TABLE $table_alias ALTER COLUMN domain DROP DEFAULT");
    if(!_pgsql_object_exists('alias_address_active')) {
        $result = db_query_parsed("CREATE INDEX alias_address_active ON $table_alias(address,active)");
    }

    $result = db_query_parsed("ALTER TABLE $table_domain_admins ALTER COLUMN username DROP DEFAULT");
    $result = db_query_parsed("ALTER TABLE $table_domain_admins ALTER COLUMN domain DROP DEFAULT");

    $result = db_query_parsed("
        BEGIN;
            ALTER TABLE $table_log RENAME COLUMN data TO data_old;
            ALTER TABLE $table_log ADD COLUMN data text NOT NULL default '';
            UPDATE $table_log SET data = CAST(data_old AS text);
            ALTER TABLE $table_log DROP COLUMN data_old;
        COMMIT;");

    $result = db_query_parsed("ALTER TABLE $table_mailbox ALTER COLUMN username DROP DEFAULT");
    $result = db_query_parsed("ALTER TABLE $table_mailbox ALTER COLUMN domain DROP DEFAULT");

    $result = db_query_parsed("
        BEGIN;
            ALTER TABLE $table_mailbox RENAME COLUMN domain TO domain_old;
            ALTER TABLE $table_mailbox ADD COLUMN domain varchar(255) REFERENCES $table_domain (domain);
            UPDATE $table_mailbox SET domain = domain_old;
            ALTER TABLE $table_mailbox DROP COLUMN domain_old;
        COMMIT;"
    );
    if(!_pgsql_object_exists('mailbox_username_active')) {
        db_query_parsed("CREATE INDEX mailbox_username_active ON $table_mailbox(username,active)");
    }


    $result = db_query_parsed("ALTER TABLE $table_vacation ALTER COLUMN body SET DEFAULT ''");
    if(_pgsql_field_exists($table_vacation, 'cache')) {
        $result = db_query_parsed("ALTER TABLE $table_vacation DROP COLUMN cache");
    }

    $result = db_query_parsed("
        BEGIN;
            ALTER TABLE $table_vacation RENAME COLUMN domain to domain_old;
            ALTER TABLE $table_vacation ADD COLUMN domain varchar(255) REFERENCES $table_domain;
            UPDATE $table_vacation SET domain = domain_old;
            ALTER TABLE $table_vacation DROP COLUMN domain_old;
        COMMIT;
    ");

    if(!_pgsql_object_exists('vacation_email_active')) {
        $result = db_query_parsed("CREATE INDEX vacation_email_active ON $table_vacation(email,active)");
    }

    if(!_pgsql_object_exists($table_vacation_notification)) {
        $result = db_query_parsed("
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
 */
function upgrade_5_mysql() {

    $result = db_query_parsed("
        CREATE TABLE {IF_NOT_EXISTS} `" . table_by_key('admin') . "` (
            `username` varchar(255) NOT NULL default '',
            `password` varchar(255) NOT NULL default '',
            `created` {DATETIME},
            `modified` {DATETIME},
            `active` tinyint(1) NOT NULL default '1',
            PRIMARY KEY  (`username`),
    KEY username (`username`)
) {MYISAM} DEFAULT {LATIN1} COMMENT='Postfix Admin - Virtual Admins'; ");

    $result = db_query_parsed("
        CREATE TABLE {IF_NOT_EXISTS} `" . table_by_key('alias') . "` (
            `address` varchar(255) NOT NULL default '',
            `goto` text NOT NULL,
            `domain` varchar(255) NOT NULL default '',
            `created` {DATETIME},
            `modified` {DATETIME},
            `active` tinyint(1) NOT NULL default '1',
            PRIMARY KEY  (`address`),
    KEY address (`address`)
            ) {MYISAM} DEFAULT {LATIN1} COMMENT='Postfix Admin - Virtual Aliases';
    ");

    $result = db_query_parsed("
        CREATE TABLE {IF_NOT_EXISTS} `" . table_by_key('domain') . "` (
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
            ) {MYISAM} DEFAULT {LATIN1} COMMENT='Postfix Admin - Virtual Domains';
    ");

    $result = db_query_parsed("
        CREATE TABLE {IF_NOT_EXISTS} `" . table_by_key('domain_admins') . "` (
            `username` varchar(255) NOT NULL default '',
            `domain` varchar(255) NOT NULL default '',
            `created` {DATETIME},
            `active` tinyint(1) NOT NULL default '1',
            KEY username (`username`)
        ) {MYISAM} DEFAULT {LATIN1} COMMENT='Postfix Admin - Domain Admins';
    ");

    $result = db_query_parsed("
        CREATE TABLE {IF_NOT_EXISTS} `" . table_by_key('log') . "` (
            `timestamp` {DATETIME},
            `username` varchar(255) NOT NULL default '',
            `domain` varchar(255) NOT NULL default '',
            `action` varchar(255) NOT NULL default '',
            `data` varchar(255) NOT NULL default '',
            KEY timestamp (`timestamp`)
        ) {MYISAM} DEFAULT {LATIN1} COMMENT='Postfix Admin - Log';
    ");

    $result = db_query_parsed("
        CREATE TABLE {IF_NOT_EXISTS} `" . table_by_key('mailbox') . "` (
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
            ) {MYISAM} DEFAULT {LATIN1} COMMENT='Postfix Admin - Virtual Mailboxes';
    ");

    $result = db_query_parsed("
        CREATE TABLE {IF_NOT_EXISTS} `" . table_by_key('vacation') . "` (
            `email` varchar(255) NOT NULL ,
            `subject` varchar(255) NOT NULL,
            `body` text NOT NULL,
            `cache` text NOT NULL,
            `domain` varchar(255) NOT NULL,
            `created` {DATETIME},
            `active` tinyint(1) NOT NULL default '1',
            PRIMARY KEY  (`email`),
    KEY email (`email`)
            ) {MYISAM} DEFAULT {LATIN1} COMMENT='Postfix Admin - Virtual Vacation';
    ");
}

/**
 * drop useless indicies (already available as primary key)
 */
function upgrade_79_mysql() { # MySQL only
    $result = db_query_parsed(_drop_index('admin', 'username'), True);
    $result = db_query_parsed(_drop_index('alias', 'address'), True);
    $result = db_query_parsed(_drop_index('domain', 'domain'), True);
    $result = db_query_parsed(_drop_index('mailbox', 'username'), True);
}

function upgrade_81_mysql() { # MySQL only
    $table_vacation = table_by_key ('vacation');
    $table_vacation_notification = table_by_key('vacation_notification');

    $all_sql = explode("\n", trim("
        ALTER TABLE `$table_vacation` CHANGE `email`    `email`   VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_vacation` CHANGE `subject`  `subject` VARCHAR( 255 ) {UTF-8}  NOT NULL
        ALTER TABLE `$table_vacation` CHANGE `body`     `body`    TEXT           {UTF-8}  NOT NULL
        ALTER TABLE `$table_vacation` CHANGE `cache`    `cache`   TEXT           {LATIN1} NOT NULL
        ALTER TABLE `$table_vacation` CHANGE `domain`   `domain`  VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_vacation` CHANGE `active`   `active`  TINYINT( 1 )            NOT NULL DEFAULT '1'
        ALTER TABLE `$table_vacation` DEFAULT  {LATIN1}
        ALTER TABLE `$table_vacation` {INNODB}
    "));

    foreach ($all_sql as $sql) {
        $result = db_query_parsed($sql, TRUE);
    }

}

/**
 * Make logging translatable - i.e. create alias => create_alias
 */
function upgrade_90_mysql_pgsql() {
    $result = db_query_parsed("UPDATE " . table_by_key ('log') . " SET action = REPLACE(action,' ','_')", TRUE);
    # change edit_alias_state to edit_alias_active
    $result = db_query_parsed("UPDATE " . table_by_key ('log') . " SET action = 'edit_alias_state' WHERE action = 'edit_alias_active'", TRUE);
}

/**
 * MySQL only allow quota > 2 GB
 */
function upgrade_169_mysql() { 

    $table_domain = table_by_key ('domain');
    $table_mailbox = table_by_key ('mailbox');
    $result = db_query_parsed("ALTER TABLE $table_domain MODIFY COLUMN `quota` bigint(20) NOT NULL default '0'", TRUE);
    $result = db_query_parsed("ALTER TABLE $table_domain MODIFY COLUMN `maxquota` bigint(20) NOT NULL default '0'", TRUE);
    $result = db_query_parsed("ALTER TABLE $table_mailbox MODIFY COLUMN `quota` bigint(20) NOT NULL default '0'", TRUE);
}


/**
 * Create / modify vacation_notification table.
 * Note: This might not work if users used workarounds to create the table before.
 * In this case, dropping the table is the easiest solution.
 */
function upgrade_318_mysql() {
    $table_vacation_notification = table_by_key('vacation_notification');
    $table_vacation = table_by_key('vacation');

    db_query_parsed( "
        CREATE TABLE {IF_NOT_EXISTS} $table_vacation_notification (
            on_vacation varchar(255) {LATIN1} NOT NULL,
            notified varchar(255) NOT NULL,
            notified_at timestamp NOT NULL default CURRENT_TIMESTAMP,
            PRIMARY KEY on_vacation (`on_vacation`, `notified`),
        CONSTRAINT `vacation_notification_pkey` 
        FOREIGN KEY (`on_vacation`) REFERENCES $table_vacation(`email`) ON DELETE CASCADE
    )
    {INNODB}
    COMMENT='Postfix Admin - Virtual Vacation Notifications'
    ");

    # in case someone has manually created the table with utf8 fields before:
    $all_sql = explode("\n", trim("
        ALTER TABLE `$table_vacation_notification` CHANGE `notified`    `notified`    VARCHAR( 255 ) NOT NULL
        ALTER TABLE `$table_vacation_notification` DEFAULT CHARACTER SET utf8
    "));
    # Possible errors that can be ignored:
    # None.
    # If something goes wrong, the user should drop the vacation_notification table 
    # (not a great loss) and re-create it using this function.

    foreach ($all_sql as $sql) {
        $result = db_query_parsed($sql);
    }

}


/**
 * Create fetchmail table
 */
function upgrade_344_mysql() {

    $table_fetchmail = table_by_key('fetchmail');

    db_query_parsed( "
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

function upgrade_344_pgsql() {
    $fetchmail = table_by_key('fetchmail');
     // a field name called 'date' is probably a bad idea.
    if(!_pgsql_object_exists('fetchmail')) {
        db_query_parsed( "
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
    if(_pgsql_object_exists('fetchmail_id_seq')) {
        db_query_parsed("SELECT nextval('{$fetchmail}_id_seq')"); // I don't care about number waste. 
    }
}

/** 
 * Create alias_domain table - MySQL
 */
# function upgrade_362_mysql() # renamed to _438 to make sure it runs after an upgrade from 2.2.x
function upgrade_438_mysql() {
    # Table structure for table alias_domain
    #
    $table_alias_domain = table_by_key('alias_domain');
    db_query_parsed("
        CREATE TABLE IF NOT EXISTS $table_alias_domain (
            `alias_domain` varchar(255) NOT NULL default '',
            `target_domain` varchar(255) NOT NULL default '',
            `created` {DATETIME},
            `modified` {DATETIME},
            `active` tinyint(1) NOT NULL default '1',
            PRIMARY KEY  (`alias_domain`),
            KEY `active` (`active`),
            KEY `target_domain` (`target_domain`)
        ) {MYISAM} COMMENT='Postfix Admin - Domain Aliases'
    ");
}

/** 
 * Create alias_domain table - PgSQL
 */
# function upgrade_362_pgsql()  # renamed to _438 to make sure it runs after an upgrade from 2.2.x
function upgrade_438_pgsql() {
    # Table structure for table alias_domain
    $table_alias_domain = table_by_key('alias_domain');
    $table_domain = table_by_key('domain');
    if(_pgsql_object_exists($table_alias_domain)) {
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
 */
function upgrade_373_mysql() { # MySQL only
    $table_domain = table_by_key ('domain');
    $table_mailbox = table_by_key('mailbox');

    $all_sql = explode("\n", trim("
        ALTER TABLE `$table_domain`  CHANGE `description`  `description` VARCHAR( 255 ) {UTF-8}  NOT NULL
        ALTER TABLE `$table_mailbox` CHANGE `name`         `name`        VARCHAR( 255 ) {UTF-8}  NOT NULL
    "));

    foreach ($all_sql as $sql) {
        $result = db_query_parsed($sql);
    }
}


/**
 * add ssl option for fetchmail
 */
function upgrade_439_mysql() {
    $table_fetchmail = table_by_key('fetchmail');
    if(!_mysql_field_exists($table_fetchmail, 'ssl')) {
        db_query_parsed("ALTER TABLE `$table_fetchmail` ADD `ssl` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT '0' AFTER `protocol` ; ");
    }
}
function upgrade_439_pgsql() {
    $table_fetchmail = table_by_key('fetchmail');
    if(!_pgsql_field_exists($table_fetchmail, 'ssl')) {
        db_query_parsed("ALTER TABLE $table_fetchmail ADD COLUMN ssl BOOLEAN NOT NULL DEFAULT false");
    }
}

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
        ALTER TABLE `$table_admin`   CHANGE `username`      `username`      VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_admin`   CHANGE `password`      `password`      VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_admin`   DEFAULT                                               {LATIN1}
        ALTER TABLE `$table_alias`   CHANGE `address`       `address`       VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_alias`   CHANGE `goto`          `goto`             TEXT        {LATIN1} NOT NULL
        ALTER TABLE `$table_alias`   CHANGE `domain`        `domain`        VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_alias`   DEFAULT                                               {LATIN1}
        ALTER TABLE `$table_al_dom`  CHANGE `alias_domain`  `alias_domain`  VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_al_dom`  CHANGE `target_domain` `target_domain` VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_al_dom`  DEFAULT                                               {LATIN1}
        ALTER TABLE `$table_domain`  CHANGE `domain`         `domain`       VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_domain`  CHANGE `transport`      `transport`    VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_domain`  DEFAULT                                               {LATIN1}
        ALTER TABLE `$table_dom_adm` CHANGE `username`       `username`     VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_dom_adm` CHANGE `domain`         `domain`       VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_dom_adm` DEFAULT                                               {LATIN1}
        ALTER TABLE `$table_log`     CHANGE `username`       `username`     VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_log`     CHANGE `domain`         `domain`       VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_log`     CHANGE `action`         `action`       VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_log`     CHANGE `data`           `data`         VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_log`     DEFAULT                                               {LATIN1}
        ALTER TABLE `$table_mailbox` CHANGE `username`       `username`     VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_mailbox` CHANGE `password`       `password`     VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_mailbox` CHANGE `maildir`        `maildir`      VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_mailbox` CHANGE `domain`         `domain`       VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_mailbox` DEFAULT                                               {LATIN1}
        ALTER TABLE `$table_fmail`   CHANGE `mailbox`        `mailbox`      VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_fmail`   CHANGE `src_server`     `src_server`   VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_fmail`   CHANGE `src_user`       `src_user`     VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_fmail`   CHANGE `src_password`   `src_password` VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_fmail`   CHANGE `src_folder`     `src_folder`   VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_fmail`   CHANGE `mda`            `mda`          VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_fmail`   CHANGE `mailbox`        `mailbox`      VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_fmail`   CHANGE `extra_options`  `extra_options`   TEXT        {LATIN1} NULL DEFAULT NULL
        ALTER TABLE `$table_fmail`   CHANGE `returned_text`  `returned_text`   TEXT        {LATIN1} NULL DEFAULT NULL
        ALTER TABLE `$table_fmail`   DEFAULT                                               {LATIN1}
        "));

    foreach ($all_sql as $sql) {
        $result = db_query_parsed($sql);
    }
}

function upgrade_479_mysql () {
    # ssl is a reserved word in MySQL and causes several problems. Renaming the field...
    $table_fmail   = table_by_key('fetchmail');
    if(!_mysql_field_exists($table_fmail, 'usessl')) {
        db_query_parsed("ALTER TABLE `$table_fmail` CHANGE `ssl` `usessl` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT '0'");
    }
}
function upgrade_479_pgsql () {
    $table_fmail   = table_by_key('fetchmail');
    if(!_pgsql_field_exists($table_fmail, 'usessl')) {
        db_query_parsed("alter table $table_fmail rename column ssl to usessl");
    }
}

function upgrade_483_mysql () {
    $table_log   = table_by_key('log');
    db_query_parsed("ALTER TABLE $table_log CHANGE `data` `data` TEXT {LATIN1} NOT NULL");
}

# Add a local_part field to the mailbox table, and populate it with the local part of the user's address.
# This is to make it easier (hopefully) to change the filesystem location of a mailbox in the future
# See https://sourceforge.net/forum/message.php?msg_id=5394663
function upgrade_495_pgsql() {
    $table_mailbox = table_by_key('mailbox');
    if(!_pgsql_field_exists($table_mailbox, 'local_part')) {
        db_query_parsed("ALTER TABLE $table_mailbox add column local_part varchar(255) ");
        db_query_parsed("UPDATE $table_mailbox SET local_part = substring(username from '^(.*)@')");
        db_query_parsed("ALTER TABLE $table_mailbox alter column local_part SET NOT NULL");
    }
}
# See https://sourceforge.net/forum/message.php?msg_id=5394663
function upgrade_495_mysql() {
    $table_mailbox = table_by_key('mailbox');
    if(!_mysql_field_exists($table_mailbox, 'local_part')) {
        db_query_parsed("ALTER TABLE $table_mailbox add local_part varchar(255) AFTER quota"); // allow to be null
        db_query_parsed("UPDATE $table_mailbox SET local_part = substring_index(username, '@', 1)");
        db_query_parsed("ALTER TABLE $table_mailbox change local_part local_part varchar(255) NOT NULL"); // remove null-ness...
    }
}

function upgrade_504_mysql() {
    $table_mailbox = table_by_key('mailbox');
    db_query_parsed("ALTER TABLE `$table_mailbox` CHANGE `local_part` `local_part` VARCHAR( 255 ) {LATIN1} NOT NULL");
}

function upgrade_655_mysql_pgsql() {
    db_query_parsed(_add_index('mailbox', 'domain', 'domain'));
    db_query_parsed(_add_index('alias',   'domain', 'domain'));
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
         ) {MYISAM} COMMENT='Postfix Admin - Client Access'
     ");
    $table_from_access = table_by_key('from_access');
     db_query_parsed("
         CREATE TABLE IF NOT EXISTS $table_from_access (
             `from_access` char(50) NOT NULL,
             `action` char(50) NOT NULL default 'REJECT',
             UNIQUE KEY `from_access` (`from_access`)
         ) {MYISAM} COMMENT='Postfix Admin - From Access'
     ");
     $table_helo_access = table_by_key('helo_access');
     db_query_parsed("
         CREATE TABLE IF NOT EXISTS $table_helo_access (
             `helo` char(50) NOT NULL,
             `action` char(50) NOT NULL default 'REJECT',
             UNIQUE KEY `helo` (`helo`)
         ) {MYISAM} COMMENT='Postfix Admin - Helo Access'
     ");
     $table_rcpt_access = table_by_key('rcpt_access');
     db_query_parsed("
         CREATE TABLE IF NOT EXISTS $table_rcpt_access (
             `rcpt` char(50) NOT NULL,
             `action` char(50) NOT NULL default 'REJECT',
             UNIQUE KEY `rcpt` (`rcpt`)
         ) {MYISAM} COMMENT='Postfix Admin - Recipient Access'
     ");
     $table_user_whitelist = table_by_key('user_whitelist');
     db_query_parsed("
         CREATE TABLE IF NOT EXISTS $table_user_whitelist (
             `recipient` char(50) NOT NULL,
             `action` char(50) NOT NULL default 'REJECT',
             UNIQUE KEY `recipient` (`recipient`)
         ) {MYISAM} COMMENT='Postfix Admin - User whitelist'
     ");
}
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
    ) {MYISAM} ; 
    ");

    # table for dovecot >= 1.2
    db_query_parsed("
        CREATE TABLE {IF_NOT_EXISTS} $table_quota2 (
            username VARCHAR(100) {LATIN1} NOT NULL,
            bytes {BIGINT},
            messages integer NOT NULL DEFAULT 0,
            PRIMARY KEY (username)
        ) {MYISAM} ;
    ");
}

function upgrade_730_pgsql() {
    $table_quota = table_by_key('quota');
    $table_quota2 = table_by_key('quota2');

    db_query_parsed('CREATE LANGUAGE plpgsql', 1); /* will error if plpgsql is already installed */

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

function upgrade_945_mysql_pgsql() {
    _db_add_field('vacation', 'modified', '{DATECURRENT}', 'created');
}

function upgrade_946_mysql_pgsql() {
    # taken from upgrade_727_mysql, needs to be done for all databases
    _db_add_field('vacation', 'activefrom',  '{DATE}', 'body');
    _db_add_field('vacation', 'activeuntil', '{DATE}', 'activefrom');
}
function upgrade_968_pgsql() {
    # pgsql counterpart for upgrade_169_mysql() - allow really big quota
    $table_domain = table_by_key ('domain');
    $table_mailbox = table_by_key('mailbox');
    db_query_parsed("ALTER TABLE $table_domain  ALTER COLUMN quota    type bigint");
    db_query_parsed("ALTER TABLE $table_domain  ALTER COLUMN maxquota type bigint");
    db_query_parsed("ALTER TABLE $table_mailbox ALTER COLUMN quota    type bigint");
}

function upgrade_1050_mysql_pgsql() {
    db_query_parsed(_add_index('log', 'domain_timestamp', 'domain,timestamp'));
}

function upgrade_1283_mysql_pgsql() {
    _db_add_field('admin', 'superadmin', '{BOOLEAN}', 'password');
}

function upgrade_1284_mysql_pgsql() {
    # migrate the ALL domain to the superadmin column
    # Note: The ALL domain is not (yet) deleted to stay backwards-compatible for now (will be done in a later upgrade function)

    $result = db_query("SELECT username FROM " . table_by_key('domain_admins') . " where domain='ALL'");

    if ($result['rows'] > 0) {
        while ($row = db_array ($result['result'])) {
            printdebug ("Setting superadmin flag for " . $row['username']);
            db_update('admin', 'username', $row['username'], array('superadmin' => db_get_boolean(true)) );
        }
    }
}

function upgrade_1345_mysql() {
    # $table_vacation = table_by_key('vacation');
    # adding and usage of reply_type field removed in r1610
    # db_query_parsed("ALTER TABLE `$table_vacation` ADD `reply_type` VARCHAR( 20 ) NOT NULL AFTER `domain`  ");
    # obsoleted by upgrade_1610()
    # db_query_parsed("ALTER TABLE `$table_vacation` ADD `interval_time` INT NOT NULL DEFAULT '0' AFTER `reply_type` ");
}

function upgrade_1519_mysql_pgsql() {
    _db_add_field('fetchmail', 'sslcertck',      '{BOOLEAN}',                        'usessl'     );
    _db_add_field('fetchmail', 'sslcertpath',    "VARCHAR(255) {UTF-8}  DEFAULT ''", 'sslcertck'  );
    _db_add_field('fetchmail', 'sslfingerprint', "VARCHAR(255) {LATIN1} DEFAULT ''", 'sslcertpath');
}

function upgrade_1610_mysql_pgsql() {
    # obsoletes upgrade_1345_mysql() - which means debug mode could print "field already exists"
    _db_add_field('vacation', 'interval_time', '{INT}', 'domain');
}

function upgrade_1685_mysql() {
    # Fix existing log entries broken by https://sourceforge.net/p/postfixadmin/bugs/317/
    $table = table_by_key('log');
    db_query_parsed("UPDATE $table SET data = domain WHERE data = '' AND domain LIKE '%@%'");
    db_query_parsed("UPDATE $table SET domain=SUBSTRING_INDEX(domain, '@', -1) WHERE domain=data;");
}
function upgrade_1685_pgsql() {
    $table = table_by_key('log');
    db_query_parsed("UPDATE $table SET data = domain WHERE data = '' AND domain LIKE '%@%'");
    db_query_parsed("UPDATE $table SET domain=SPLIT_PART(domain, '@', 2) WHERE domain=data;");
}

function upgrade_1761_mysql() {
    # upgrade_1762 adds the 'modified' column as {DATECURRENT}, therefore we first need to change
    # 'date' to {DATE} (mysql only allows one {DATECURRENT} column per table)
    $table_fetchmail = table_by_key('fetchmail');
    db_query_parsed("ALTER TABLE `$table_fetchmail`  CHANGE `date`  `date` {DATE}");
}

function upgrade_1762_mysql_pgsql() {
    _db_add_field('fetchmail', 'domain',   "VARCHAR(255) {LATIN1} DEFAULT ''", 'id');
    _db_add_field('fetchmail', 'active',   '{BOOLEAN}',                        'date');
    _db_add_field('fetchmail', 'created',  '{DATE}',                           'date');

    # If you followed SVN and got upgrade failures here, you might need to
    #    UPDATE config SET value=1760 WHERE name='version';
    # and run setup.php again (upgrade_1761_mysql was added later).
    _db_add_field('fetchmail', 'modified', '{DATECURRENT}',                    'created');
}

function upgrade_1763_mysql() {
    $table = table_by_key('fetchmail');
    db_query_parsed("UPDATE $table SET domain=SUBSTRING_INDEX(mailbox, '@', -1) WHERE domain='';");
}
function upgrade_1763_pgsql() {
    $table = table_by_key('fetchmail');
    db_query_parsed("UPDATE $table SET domain=SPLIT_PART(mailbox, '@', 2) WHERE domain='';");
}

function upgrade_1767_mysql_pgsql() {
    # 'active' was just added, so make sure all existing jobs stay active
    $table = table_by_key('fetchmail');
    db_query_parsed("UPDATE $table SET active='{BOOL_TRUE}'");
}

function upgrade_1795_mysql() {
    # upgrade_1761_mysql() was added later (in r1795) - make sure it runs for everybody
    # (running it twice doesn't hurt)
    upgrade_1761_mysql();
}

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
      CREATE TABLE $admin_table (
          `username` varchar(255) NOT NULL,
          `password` varchar(255) NOT NULL,
          `superadmin` {BOOLEAN},
          `created` {DATE},
          `modified` {DATE},
          `active` {BOOLEAN_TRUE},
          {PRIMARY} (`username`));
    ");

    db_query_parsed("
      CREATE TABLE $alias_table (
          `address` varchar(255) NOT NULL,
          `goto` {FULLTEXT} NOT NULL,
          `domain` varchar(255) NOT NULL,
          `created` {DATE},
          `modified` {DATE},
          `active` {BOOLEAN_TRUE},
          {PRIMARY} (`address`));
    ");

    db_query_parsed("
      CREATE TABLE $alias_domain_table (
          `alias_domain` varchar(255) NOT NULL,
          `target_domain` varchar(255) NOT NULL,
          `created` {DATE},
          `modified` {DATE},
          `active` {BOOLEAN_TRUE},
          {PRIMARY} (`alias_domain`));
    ");

    db_query_parsed("
      CREATE TABLE $domain_table (
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
      CREATE TABLE $domain_admins_table (
          `username` varchar(255) NOT NULL,
          `domain` varchar(255) NOT NULL,
          `created` {DATE},
          `active` {BOOLEAN_TRUE});
    ");

    db_query_parsed("
      CREATE TABLE $fetchmail_table (
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
      CREATE TABLE $log_table (
          `timestamp` {DATE},
          `username` varchar(255) NOT NULL,
          `domain` varchar(255) NOT NULL,
          `action` varchar(255) NOT NULL,
          `data` {FULLTEXT} NOT NULL);
    ");

    db_query_parsed("
      CREATE TABLE $mailbox_table (
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
      CREATE TABLE $quota_table (
          `username` varchar(255) NOT NULL,
          `path` varchar(100) NOT NULL,
          `current` {BIGINT},
          {PRIMARY} (`username`,`path`));
    ");

    db_query_parsed("
      CREATE TABLE $quota2_table (
          `username` varchar(255) NOT NULL,
          `bytes` {BIGINT},
          `messages` {INT},
          {PRIMARY} (`username`));
    ");

    db_query_parsed("
      CREATE TABLE $vacation_table (
          `email` varchar(255) NOT NULL,
          `subject` varchar(255) NOT NULL,
          `body` {FULLTEXT} NOT NULL,
          `activefrom` {DATE},
          `activeuntil` {DATE},
          `cache` {FULLTEXT} NOT NULL DEFAULT '',
          `domain` varchar(255) NOT NULL,
          `interval_time` {INT},
          `created` {DATE},
          `modified` {DATECURRENT},
          `active` {BOOLEAN_TRUE},
          {PRIMARY} (`email`));
    ");

    db_query_parsed("
      CREATE TABLE $vacation_notification_table (
          `on_vacation` varchar(255) NOT NULL,
          `notified` varchar(255) NOT NULL,
          `notified_at` {DATECURRENT},
          {PRIMARY} (`on_vacation`,`notified`),
          CONSTRAINT `vacation_notification_pkey` FOREIGN KEY (`on_vacation`) REFERENCES `vacation` (`email`) ON DELETE CASCADE);
    ");

}


function upgrade_1835_mysql() {
    # change default values for existing datetime fields with a 0000-00-00 default to {DATETIME}

    foreach (array('admin', 'alias', 'alias_domain', 'domain', 'mailbox', 'domain_admins', 'vacation') as $table_to_change) {
        $table = table_by_key($table_to_change);
        db_query_parsed("ALTER TABLE `$table` CHANGE `created` `created` {DATETIME}");
    }

    foreach (array('admin', 'alias', 'alias_domain', 'domain', 'mailbox') as $table_to_change) {
        $table = table_by_key($table_to_change);
        db_query_parsed("ALTER TABLE `$table` CHANGE `modified` `modified` {DATETIME}");
    }

    $table = table_by_key('log');
    db_query_parsed("ALTER TABLE `$table` CHANGE `timestamp` `timestamp` {DATETIME}");
}

# TODO MySQL:
# - various varchar fields do not have a default value
#   https://sourceforge.net/projects/postfixadmin/forums/forum/676076/topic/3419725
# - change default of all timestamp fields to {DATECURRENT} (CURRENT_TIMESTAMP} or {DATE}
#   including vacation.activefrom/activeuntil (might have a different default as leftover from upgrade_727_mysql)
#   including vacation.modified - should be {DATE}, not {DATECURRENT}
#   https://sourceforge.net/tracker/?func=detail&aid=1699218&group_id=191583&atid=937964
# @todo vacation.email has 2 indizes
