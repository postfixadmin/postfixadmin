<?php
if(!defined('POSTFIXADMIN')) {
    require_once('common.php');
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */

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
    $sql = "SHOW COLUMNS FROM $table LIKE '$field'";
    $r = db_query($sql);
    $row = db_row($r['result']);
    if($row) {
        return true;
    }
    return false;
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
}
else {
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

$sql = "SELECT * FROM $table WHERE name = 'version'";

// insert into config('version', '01');

$r = db_query($sql);

if($r['rows'] == 1) {
    $rs = $r['result'];
    $row = db_array($rs);
    $version = $row['value'];
} else {
    db_query_parsed("INSERT INTO $table (name, value) VALUES ('version', '0')", 0, '');
    $version = 0;
}

_do_upgrade($version);


function _do_upgrade($current_version) {
    global $CONF;
    $target_version = preg_replace('/[^0-9]/', '', '$Revision$');

    if ($current_version >= $target_version) {
# already up to date
        echo "Database is up to date";
        return true;
    }

    echo "<p>Updating database:</p><p>- old version: $current_version; target version: $target_version</p>";

    for ($i = $current_version +1; $i <= $target_version; $i++) {
        $function = "upgrade_$i";
        $function_mysql = $function . "_mysql";
        $function_pgsql = $function . "_pgsql";
        if (function_exists($function)) {
            echo "<p>updating to version $i (all databases)...";
            $function();
            echo " &nbsp; done";
        }
        if ($CONF['database_type'] == 'mysql' || $CONF['database_type'] == 'mysqli' ) {
            if (function_exists($function_mysql)) {
                echo "<p>updating to version $i (MySQL)...";
                $function_mysql();
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
                '{BOOLEAN}'         => 'tinyint(1) NOT NULL',
                '{UTF-8}'           => '/*!40100 CHARACTER SET utf8 */',
                '{LATIN1}'          => '/*!40100 CHARACTER SET latin1 */',
                '{IF_NOT_EXISTS}'   => 'IF NOT EXISTS',
                '{RENAME_COLUMN}'   => 'CHANGE COLUMN',
                );
        $sql = "$sql $attach_mysql";

    } elseif($CONF['database_type'] == 'pgsql') {
        $replace = array(
                '{AUTOINCREMENT}'   => 'SERIAL', 
                '{PRIMARY}'         => 'primary key', 
                '{UNSIGNED}'        => '', 
                '{FULLTEXT}'        => '', 
                '{BOOLEAN}'         => 'BOOLEAN NOT NULL', 
                '{UTF-8}'           => '', # TODO: UTF-8 is simply ignored.
                '{LATIN1}'          => '', # TODO: same for latin1 
                '{IF_NOT_EXISTS}'   => '', # TODO: does this work with PgSQL? NO
                '{RENAME_COLUMN}'   => 'ALTER COLUMN', # PgSQL : ALTER TABLE x RENAME x TO y
                'int(1)'            => 'int',
                'int(10)'           => 'int', 
                'int(11)'           => 'int', 
                'int(4)'            => 'int', 
                );

    } else {
        echo "Sorry, unsupported database type " . $conf['database_type'];
        exit;
    }

    $replace['{BOOL_TRUE}'] = db_get_boolean(True);
    $replace['{BOOL_FALSE}'] = db_get_boolean(False);

    $query = trim(str_replace(array_keys($replace), $replace, $sql));
    if (safeget('debug') != "") {
        print "<p style='color:#999'>$query";
    }
    $result = db_query($query, $ignore_errors);
    if (safeget('debug') != "") {
        print "<div style='color:#f00'>" . $result['error'] . "</div>";
    }
    return $result;
}

function _drop_index ($table, $index) {
    global $CONF;
    $tabe = table_by_key ($table);

    if ($CONF['database_type'] == 'mysql' || $CONF['database_type'] == 'mysqli' ) {
        return "ALTER TABLE $table DROP INDEX $index";
    } elseif($CONF['database_type'] == 'pgsql') {
        return "DROP INDEX $index"; # Index names are unique with a DB for PostgreSQL
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
      `created` datetime NOT NULL default '0000-00-00 00:00:00',
      `modified` datetime NOT NULL default '0000-00-00 00:00:00',
      `active` tinyint(1) NOT NULL default '1',
      PRIMARY KEY  (`username`)
  ) TYPE=MyISAM COMMENT='Postfix Admin - Virtual Admins';";

    $sql[] = "
    CREATE TABLE {IF_NOT_EXISTS} $alias (
      `address` varchar(255) NOT NULL default '',
      `goto` text NOT NULL,
      `domain` varchar(255) NOT NULL default '',
      `created` datetime NOT NULL default '0000-00-00 00:00:00',
      `modified` datetime NOT NULL default '0000-00-00 00:00:00',
      `active` tinyint(1) NOT NULL default '1',
      PRIMARY KEY  (`address`)
    ) TYPE=MyISAM COMMENT='Postfix Admin - Virtual Aliases'; ";

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
      `created` datetime NOT NULL default '0000-00-00 00:00:00',
      `modified` datetime NOT NULL default '0000-00-00 00:00:00',
      `active` tinyint(1) NOT NULL default '1',
      PRIMARY KEY  (`domain`)
    ) TYPE=MyISAM COMMENT='Postfix Admin - Virtual Domains'; ";

    $sql[] = "
    CREATE TABLE {IF_NOT_EXISTS} $domain_admins (
      `username` varchar(255) NOT NULL default '',
      `domain` varchar(255) NOT NULL default '',
      `created` datetime NOT NULL default '0000-00-00 00:00:00',
      `active` tinyint(1) NOT NULL default '1',
      KEY username (`username`)
    ) TYPE=MyISAM COMMENT='Postfix Admin - Domain Admins';";

    $sql[] = "
    CREATE TABLE {IF_NOT_EXISTS} $log (
      `timestamp` datetime NOT NULL default '0000-00-00 00:00:00',
      `username` varchar(255) NOT NULL default '',
      `domain` varchar(255) NOT NULL default '',
      `action` varchar(255) NOT NULL default '',
      `data` varchar(255) NOT NULL default '',
      KEY timestamp (`timestamp`)
    ) TYPE=MyISAM COMMENT='Postfix Admin - Log';";

    $sql[] = "
    CREATE TABLE {IF_NOT_EXISTS} $mailbox (
      `username` varchar(255) NOT NULL default '',
      `password` varchar(255) NOT NULL default '',
      `name` varchar(255) NOT NULL default '',
      `maildir` varchar(255) NOT NULL default '',
      `quota` bigint(20) NOT NULL default '0',
      `domain` varchar(255) NOT NULL default '',
      `created` datetime NOT NULL default '0000-00-00 00:00:00',
      `modified` datetime NOT NULL default '0000-00-00 00:00:00',
      `active` tinyint(1) NOT NULL default '1',
      PRIMARY KEY  (`username`)
    ) TYPE=MyISAM COMMENT='Postfix Admin - Virtual Mailboxes';";

    $sql[] = "
    CREATE TABLE {IF_NOT_EXISTS} $vacation ( 
        email varchar(255) NOT NULL , 
        subject varchar(255) NOT NULL, 
        body text NOT NULL, 
        cache text NOT NULL, 
        domain varchar(255) NOT NULL , 
        created datetime NOT NULL default '0000-00-00 00:00:00', 
        active tinyint(4) NOT NULL default '1', 
        PRIMARY KEY (email), 
        KEY email (email) 
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 TYPE=InnoDB COMMENT='Postfix Admin - Virtual Vacation' ;";

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
        $result = db_query_parsed("ALTER TABLE $table_domain ADD COLUMN backupmx {BOOLEAN} DEFAULT {BOOL_FALSE} AFTER transport;", TRUE);
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
        db_query_parsed("ALTER TABLE $table_admin {RENAME_COLUMN} create_date created DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL;");
    }
    if(!_mysql_field_exists($table_admin, 'modified')) {
        db_query_parsed("ALTER TABLE $table_admin {RENAME_COLUMN} change_date modified DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL;");
    }
    if(!_mysql_field_exists($table_alias, 'created')) {
        db_query_parsed("ALTER TABLE $table_alias {RENAME_COLUMN} create_date created DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL;");
    }
    if(!_mysql_field_exists($table_alias, 'modified')) {
        db_query_parsed("ALTER TABLE $table_alias {RENAME_COLUMN} change_date modified DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL;");
    }
    if(!_mysql_field_exists($table_domain, 'created')) {
        db_query_parsed("ALTER TABLE $table_domain {RENAME_COLUMN} create_date created DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL;");
    }
    if(!_mysql_field_exists($table_domain, 'modified')) {
        db_query_parsed("ALTER TABLE $table_domain {RENAME_COLUMN} change_date modified DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL;");
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
        db_query_parsed("ALTER TABLE $table_mailbox {RENAME_COLUMN} create_date created DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL;");
    }
    if(!_mysql_field_exists($table_mailbox, 'modified')) {
        db_query_parsed("ALTER TABLE $table_mailbox {RENAME_COLUMN} change_date modified DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL;");
    }
    if(!_mysql_field_exists($table_mailbox, 'quota')) {
        db_query_parsed("ALTER TABLE $table_mailbox ADD COLUMN quota INT(10) DEFAULT '-1' NOT NULL AFTER maildir;");
    }
    if(!_mysql_field_exists($table_vacation, 'domain')) {
        db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN domain VARCHAR(255) DEFAULT '' NOT NULL AFTER cache;");
    }
    if(!_mysql_field_exists($table_vacation, 'created')) {
        db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN created DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL AFTER domain;");
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
        db_query_parsed('CREATE INDEX mailbox_username_active ON $table_mailbox(username,active)');
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
            `created` datetime NOT NULL default '0000-00-00 00:00:00',
            `modified` datetime NOT NULL default '0000-00-00 00:00:00',
            `active` tinyint(1) NOT NULL default '1',
            PRIMARY KEY  (`username`),
    KEY username (`username`)
) TYPE=MyISAM DEFAULT {LATIN1} COMMENT='Postfix Admin - Virtual Admins'; ");

    $result = db_query_parsed("
        CREATE TABLE {IF_NOT_EXISTS} `" . table_by_key('alias') . "` (
            `address` varchar(255) NOT NULL default '',
            `goto` text NOT NULL,
            `domain` varchar(255) NOT NULL default '',
            `created` datetime NOT NULL default '0000-00-00 00:00:00',
            `modified` datetime NOT NULL default '0000-00-00 00:00:00',
            `active` tinyint(1) NOT NULL default '1',
            PRIMARY KEY  (`address`),
    KEY address (`address`)
            ) TYPE=MyISAM DEFAULT {LATIN1} COMMENT='Postfix Admin - Virtual Aliases';
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
            `created` datetime NOT NULL default '0000-00-00 00:00:00',
            `modified` datetime NOT NULL default '0000-00-00 00:00:00',
            `active` tinyint(1) NOT NULL default '1',
            PRIMARY KEY  (`domain`),
    KEY domain (`domain`)
            ) TYPE=MyISAM DEFAULT {LATIN1} COMMENT='Postfix Admin - Virtual Domains';
    ");

    $result = db_query_parsed("
        CREATE TABLE {IF_NOT_EXISTS} `" . table_by_key('domain_admins') . "` (
            `username` varchar(255) NOT NULL default '',
            `domain` varchar(255) NOT NULL default '',
            `created` datetime NOT NULL default '0000-00-00 00:00:00',
            `active` tinyint(1) NOT NULL default '1',
            KEY username (`username`)
        ) TYPE=MyISAM DEFAULT {LATIN1} COMMENT='Postfix Admin - Domain Admins';
    ");

    $result = db_query_parsed("
        CREATE TABLE {IF_NOT_EXISTS} `" . table_by_key('log') . "` (
            `timestamp` datetime NOT NULL default '0000-00-00 00:00:00',
            `username` varchar(255) NOT NULL default '',
            `domain` varchar(255) NOT NULL default '',
            `action` varchar(255) NOT NULL default '',
            `data` varchar(255) NOT NULL default '',
            KEY timestamp (`timestamp`)
        ) TYPE=MyISAM DEFAULT {LATIN1} COMMENT='Postfix Admin - Log';
    ");

    $result = db_query_parsed("
        CREATE TABLE {IF_NOT_EXISTS} `" . table_by_key('mailbox') . "` (
            `username` varchar(255) NOT NULL default '',
            `password` varchar(255) NOT NULL default '',
            `name` varchar(255) NOT NULL default '',
            `maildir` varchar(255) NOT NULL default '',
            `quota` int(10) NOT NULL default '0',
            `domain` varchar(255) NOT NULL default '',
            `created` datetime NOT NULL default '0000-00-00 00:00:00',
            `modified` datetime NOT NULL default '0000-00-00 00:00:00',
            `active` tinyint(1) NOT NULL default '1',
            PRIMARY KEY  (`username`),
    KEY username (`username`)
            ) TYPE=MyISAM DEFAULT {LATIN1} COMMENT='Postfix Admin - Virtual Mailboxes';
    ");

    $result = db_query_parsed("
        CREATE TABLE {IF_NOT_EXISTS} `" . table_by_key('vacation') . "` (
            `email` varchar(255) NOT NULL ,
            `subject` varchar(255) NOT NULL,
            `body` text NOT NULL,
            `cache` text NOT NULL,
            `domain` varchar(255) NOT NULL,
            `created` datetime NOT NULL default '0000-00-00 00:00:00',
            `active` tinyint(1) NOT NULL default '1',
            PRIMARY KEY  (`email`),
    KEY email (`email`)
            ) TYPE=MyISAM DEFAULT {LATIN1} COMMENT='Postfix Admin - Virtual Vacation';
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

    $all_sql = split("\n", trim("
        ALTER TABLE `$table_vacation` CHANGE `email`    `email`   VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_vacation` CHANGE `subject`  `subject` VARCHAR( 255 ) {UTF-8}  NOT NULL
        ALTER TABLE `$table_vacation` CHANGE `body`     `body`    TEXT           {UTF-8}  NOT NULL
        ALTER TABLE `$table_vacation` CHANGE `cache`    `cache`   TEXT           {LATIN1} NOT NULL
        ALTER TABLE `$table_vacation` CHANGE `domain`   `domain`  VARCHAR( 255 ) {LATIN1} NOT NULL
        ALTER TABLE `$table_vacation` CHANGE `active`   `active`  TINYINT( 1 )            NOT NULL DEFAULT '1'
        ALTER TABLE `$table_vacation` DEFAULT  {LATIN1}
        ALTER TABLE `$table_vacation` ENGINE = INNODB
    "));

    foreach ($all_sql as $sql) {
        $result = db_query_parsed($sql, TRUE);
    }

}

/**
 * Make logging translatable - i.e. create alias => create_alias
 */
function upgrade_90() {
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
    ENGINE=InnoDB TYPE=InnoDB 
    COMMENT='Postfix Admin - Virtual Vacation Notifications'
    ");

    # in case someone has manually created the table with utf8 fields before:
    $all_sql = split("\n", trim("
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
         date timestamp(14),
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
# function upgrade_362_mysql() { # renamed to _438 to make sure it runs after an upgrade from 2.2.x
function upgrade_438_mysql() {
    # Table structure for table alias_domain
    #
    $table_alias_domain = table_by_key('alias_domain');
    db_query_parsed("
        CREATE TABLE IF NOT EXISTS $table_alias_domain (
            `alias_domain` varchar(255) NOT NULL default '',
            `target_domain` varchar(255) NOT NULL default '',
            `created` datetime NOT NULL default '0000-00-00 00:00:00',
            `modified` datetime NOT NULL default '0000-00-00 00:00:00',
            `active` tinyint(1) NOT NULL default '1',
            PRIMARY KEY  (`alias_domain`),
            KEY `active` (`active`),
            KEY `target_domain` (`target_domain`)
        ) TYPE=MyISAM COMMENT='Postfix Admin - Domain Aliases'
    ");
}

/** 
 * Create alias_domain table - PgSQL
 */
# function upgrade_362_pgsql() { # renamed to _438 to make sure it runs after an upgrade from 2.2.x
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

    $all_sql = split("\n", trim("
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
    $all_sql = split("\n", trim("
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

