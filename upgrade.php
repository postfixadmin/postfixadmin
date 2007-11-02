<?php
require_once('common.php');

# Note: run with upgrade.php?debug=1 to see all SQL error messages


# Not nice, but works:
# We don't know if the config table exists, so we simply try to create it ;-)
# Better solution (TODO): query the db to see if the 'config' table exists

$sql = "
CREATE TABLE {IF_NOT_EXISTS} " . table_by_key ('config') . "(
    `id` {AUTOINCREMENT} {PRIMARY},
    `name`  VARCHAR(20) {LATIN1} NOT NULL DEFAULT '',
    `value` VARCHAR(20) {LATIN1} NOT NULL DEFAULT '',
    UNIQUE name ( `name` )
)
";

db_query_parsed($sql, 0, " ENGINE = MYISAM COMMENT = 'PostfixAdmin settings'");

$sql = "SELECT * FROM config WHERE name = 'version'";

// insert into config('version', '01');

$r = db_query($sql);

if($r['rows'] == 1) {
    $rs = $r['result'];
    $row = db_array($rs);
    $version = $row['value'];
} else {
    $version = 0;
}
_do_upgrade($version);


function _do_upgrade($current_version) {
    global $CONF;
    $target_version = preg_replace('/[^0-9]/', '', '$Revision$');

    if ($current_version >= $target_version) {
        # already up to date
        echo "up to date";
        return true;
    }

    echo "<p>Updating database:<p>old version: $current_version; target version: $target_version";

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
        # TODO: update version in config table after each change
        # TODO: this avoids problems in case the script hits the max_execution_time,
        # TODO: simply rerunning it will continue where it was stopped
    };
}

/**
 * Replaces database specific parts in a query
 * @param String sql query with placeholders
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
            '{BOOLEAN}'         => '`active` tinyint(1) NOT NULL',
            '{UTF_8}'           => '/*!40100 CHARACTER SET utf8 COLLATE utf8_unicode_ci */',
            '{LATIN1}'          => '/*!40100 CHARACTER SET latin1 COLLATE latin1_swedish_ci */',
            '{IF_NOT_EXISTS}'   => 'IF NOT EXISTS',
            '{RENAME_COLUMN}'   => 'CHANGE COLUMN',
        );
        $sql = "$sql $attach_mysql";

    } elseif($CONF['database_type'] == 'pgsql') {
        static $replace = array(
            '{AUTOINCREMENT}'   => 'SERIAL', 
            '{PRIMARY}'         => 'primary key', 
            '{UNSIGNED}'        => '', 
            '{FULLTEXT}'        => '', 
            '{BOOLEAN}'         => 'BOOLEAN NOT NULL', 
            '{UTF_8}'           => '', # TODO: UTF_8 is simply ignored.
            '{LATIN1}'          => '', # TODO: same for latin1
            '{IF_NOT_EXISTS}'   => 'IF NOT EXISTS', # TODO: does this work with PgSQL?
            '{RENAME_COLUMN}'   => 'CHANGE COLUMN', # TODO: probably wrong
            'int(1)'            => 'int2',
            'int(10)'           => 'int4', 
            'int(11)'           => 'int4', 
            'int(4)'            => 'int4', 
         );

    } else {
        echo "Sorry, unsupported database type " . $conf['database_type'];
        exit;
    }

    $replace['{BOOL_TRUE}'] = db_get_boolean(True);
    $replace['{BOOL_FALSE}'] = db_get_boolean(False);

    $query = trim(str_replace(array_keys($replace), $replace, $sql));
    $result = db_query($query, $ignore_errors);
    if (safeget('debug') != "") {
        print $result['error'];
    }
    return $result;
}

function _drop_index ($table, $index) {
    global $CONF;
    $tabe = table_by_key ($table);

    if ($CONF['database_type'] == 'mysql' || $CONF['database_type'] == 'mysqli' ) {
        return "ALTER TABLE $table DROP INDEX $index";
    } elseif($CONF['database_type'] == 'pgsql') {
        return "DROP INDEX $index"; # TODO: on which table?!
    } else {
        echo "Sorry, unsupported database type " . $conf['database_type'];
        exit;
    }
}

function upgrade_1() {
    # inserting the version number is a good start ;-)
    db_insert(
        'config', 
        array(
            'name' => 'version',
            'value' => '1',
        )
    );
    echo "upgrade_1";
}

function upgrade_2() {
    # upgrade pre-2.1 database
    # from TABLE_BACKUP_MX.TXT
    $table_domain = table_by_key ('domain');
    $result = db_query_parsed("ALTER TABLE $table_domain ADD COLUMN transport VARCHAR(255) AFTER maxquota;", TRUE);
    $result = db_query_parsed("ALTER TABLE $table_domain ADD COLUMN backupmx {BOOLEAN} DEFAULT {BOOL_FALSE} AFTER transport;", TRUE);
}

function upgrade_3() {
    # upgrade pre-2.1 database
    # from TABLE_CHANGES.TXT
    $table_admin = table_by_key ('admin');
    $table_alias = table_by_key ('alias');
    $table_domain = table_by_key ('domain');
    $table_mailbox = table_by_key ('mailbox');
    $table_vacation = table_by_key ('vacation');

    $all_sql = split("\n", trim("
        ALTER TABLE $table_admin {RENAME_COLUMN} create_date created DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL;
        ALTER TABLE $table_admin {RENAME_COLUMN} change_date modified DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL;
        ALTER TABLE $table_alias {RENAME_COLUMN} create_date created DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL;
        ALTER TABLE $table_alias {RENAME_COLUMN} change_date modified DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL;
        ALTER TABLE $table_domain {RENAME_COLUMN} create_date created DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL;
        ALTER TABLE $table_domain {RENAME_COLUMN} change_date modified DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL;
        ALTER TABLE $table_domain ADD COLUMN aliases INT(10) DEFAULT '-1' NOT NULL AFTER description;
        ALTER TABLE $table_domain ADD COLUMN mailboxes INT(10) DEFAULT '-1' NOT NULL AFTER aliases;
        ALTER TABLE $table_domain ADD COLUMN maxquota INT(10) DEFAULT '-1' NOT NULL AFTER mailboxes;
        ALTER TABLE $table_domain ADD COLUMN transport VARCHAR(255) AFTER maxquota;
        ALTER TABLE $table_domain ADD COLUMN backupmx TINYINT(1) DEFAULT '0' NOT NULL AFTER transport;
        ALTER TABLE $table_mailbox {RENAME_COLUMN} create_date created DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL;
        ALTER TABLE $table_mailbox {RENAME_COLUMN} change_date modified DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL;
        ALTER TABLE $table_mailbox ADD COLUMN quota INT(10) DEFAULT '-1' NOT NULL AFTER maildir;
        ALTER TABLE $table_vacation ADD COLUMN domain VARCHAR(255) DEFAULT '' NOT NULL AFTER cache;
        ALTER TABLE $table_vacation ADD COLUMN created DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL AFTER domain;
        ALTER TABLE $table_vacation ADD COLUMN active TINYINT(1) DEFAULT '1' NOT NULL AFTER created;
        ALTER TABLE $table_vacation DROP PRIMARY KEY
        ALTER TABLE $table_vacation ADD COLUMN PRIMARY KEY(email);
        UPDATE $table_vacation SET domain=SUBSTRING_INDEX(email, '@', -1) WHERE email=email;
    "));

    foreach ($all_sql as $sql) {
        $result = db_query_parsed($sql, TRUE);
    }
}

function upgrade_4_mysql() { # MySQL only
    # changes between 2.1 and moving to sourceforge
    $table_domain = table_by_key ('domain');
    $result = db_query_parsed("ALTER TABLE $table_domain ADD COLUMN quota int(10) NOT NULL default '0' AFTER maxquota", TRUE);
}

function upgrade_4_pgsql() { # PgSQL only
    # changes between 2.1 and moving to sourceforge

/* TODO

Changes in DATABASE_PGSQL.TXT: (in diff format - "-" means removed, "+" means added)

TABLE domain
- domain character varying(255) NOT NULL default '',
+ domain character varying(255) NOT NULL,
+ quota integer NOT NULL default 0,

+CREATE INDEX domain_domain_active ON domain(domain,active);


TABLE "admin" 
-  "username" character varying(255) NOT NULL default '',
+  "username" character varying(255) NOT NULL,

	
TABLE alias
- address character varying(255) NOT NULL default '',
+ address character varying(255) NOT NULL,
- domain character varying(255) NOT NULL default '',
+ domain character varying(255) NOT NULL REFERENCES domain,

+CREATE INDEX alias_address_active ON alias(address,active);
 
TABLE domain_admins
- username character varying(255) NOT NULL default '',
+ username character varying(255) NOT NULL,
- domain character varying(255) NOT NULL default '',
+ domain character varying(255) NOT NULL REFERENCES domain,

TABLE log
- data character varying(255) NOT NULL default ''
+ data text NOT NULL default ''
 
TABLE mailbox
- username character varying(255) NOT NULL default '',
+ username character varying(255) NOT NULL,
- domain character varying(255) NOT NULL default '',
+ domain character varying(255) NOT NULL REFERENCES domain,

+CREATE INDEX mailbox_username_active ON mailbox(username,active);
 
TABLE vacation
- email character varying(255) NOT NULL default '',
+ email character varying(255) PRIMARY KEY,
- body text NOT NULL,
+ body text NOT NULL DEFAULT '',
- cache text NOT NULL,
+ cache text NOT NULL DEFAULT '',
- domain character varying(255) NOT NULL default '',
+ "domain" character varying(255) NOT NULL REFERENCES "domain",
- active boolean NOT NULL default true,
+ active boolean DEFAULT true NOT NULL
- Constraint "vacation_key" Primary Key ("email")

-COMMENT ON TABLE vacation IS 'Postfix Admin - Virtual Vacation';

+CREATE INDEX vacation_email_active ON vacation(email,active);

+CREATE TABLE vacation_notification (
+    on_vacation character varying(255) NOT NULL REFERENCES vacation(email) ON DELETE CASCADE,
+    notified character varying(255) NOT NULL,
+    notified_at timestamp with time zone NOT NULL DEFAULT now(),
+    CONSTRAINT vacation_notification_pkey primary key(on_vacation,notified)
+);


*/
}

function upgrade_79_mysql() { # MySQL only
    # drop useless indicies (already available as primary key)
    $result = db_query_parsed(_drop_index('admin', 'username'));
    $result = db_query_parsed(_drop_index('alias', 'address'));
    $result = db_query_parsed(_drop_index('domain', 'domain'));
    $result = db_query_parsed(_drop_index('mailbox', 'username'));
}

function upgrade_81_mysql() { # MySQL only
/* TODO

table vacation -
- all varchar + text fields changed to utf8
- active was tinyint (1), now (4)
- ENGINE changed
- DEFAULT CHARSET changed
- COLLATE changed

diff:
   -  `body` text NOT NULL default '',
   -  `cache` text NOT NULL default '',
   +    body text NOT NULL,
   +    cache text NOT NULL,
   -  `active` tinyint(1) NOT NULL default '1',
   +    active tinyint(4) NOT NULL default '1',  -> boolean are usually tinyint(1)
   -) TYPE=MyISAM COMMENT='Postfix Admin - Virtual Vacation';
   +) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci TYPE=InnoDB COMMENT='Postfix Admin - Virtual Vacation' ;

 
 
 */

    db_query_parsed(
        "   CREATE TABLE {IF_NOT_EXISTS} vacation_notification (
                on_vacation varchar(255) NOT NULL,
                notified varchar(255) NOT NULL,
                notified_at timestamp NOT NULL default now(),
                CONSTRAINT vacation_notification_pkey PRIMARY KEY(on_vacation, notified),
                FOREIGN KEY (on_vacation) REFERENCES vacation(email) ON DELETE CASCADE
            )
        ", 
        TRUE, 
        "   ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci TYPE=InnoDB 
            COMMENT='Postfix Admin - Virtual Vacation Notifications'
        "
    );
}

function upgrade_169_mysql() { # MySQL only
    # allow quota > 2 GB

    $table_domain = table_by_key ('domain');
    $table_mailbox = table_by_key ('mailbox');
    $result = db_query_parsed("ALTER TABLE $table_domain ALTER COLUMN `quota` bigint(20) NOT NULL default '0'", TRUE);
    $result = db_query_parsed("ALTER TABLE $table_mailbox ALTER COLUMN `maxquota` bigint(20) NOT NULL default '0'", TRUE);
    $result = db_query_parsed("ALTER TABLE $table_mailbox ALTER COLUMN `quota` bigint(20) NOT NULL default '0'", TRUE);
}


/*
TODO

   Database changes that should be done:
   ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

MySQL:
* vacation: DROP INDEX email
* vacation_notification:
  - DEFAULT CHARSET and COLLATE should be changed
  - change all varchar fields to latin1 (email addresses don't contain utf8 characters)
* remove all DROP TABLE statements from DATABASE_*

*/


/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
