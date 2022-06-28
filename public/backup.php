<?php
/**
 * Postfix Admin
 *
 * LICENSE
 * This source file is subject to the GPL license that is bundled with
 * this package in the file LICENSE.TXT.
 *
 * Further details on the project are available at http://postfixadmin.sf.net
 *
 * @version $Id$
 * @license GNU GPL v2 or later.
 *
 * File: backup.php
 * Used to save all settings - but only works for MySQL databases.
 * Template File: -none-
 *
 * Template Variables: -none-
 *
 * Form POST \ GET Variables: -none-
 */

require_once('common.php');

authentication_require_role('global-admin');

$CONF = Config::getInstance()->getAll();
$smarty = PFASmarty::getInstance();

(($CONF['backup'] == 'NO') ? header("Location: main.php") && exit : '1');

$version = Config::read_string('version');

// TODO: make backup supported for postgres
if (db_pgsql()) {
    flash_error('Sorry: Backup is currently not supported for your DBMS ('.$CONF['database_type'].').');
    $smarty->assign('smarty_template', 'message');
    $smarty->display('index.tpl');
    die;
}

if (safeget('download') == "") {
    $smarty->assign('smarty_template', 'backupwarning');
    $smarty->display('index.tpl');
    die;
}

# Still here? Then let's create the database dump...

/*
    SELECT attnum,attname,typname,atttypmod-4,attnotnull,atthasdef,adsrc
    AS def FROM pg_attribute,pg_class,pg_type,pg_attrdef
    WHERE pg_class.oid=attrelid AND pg_type.oid=atttypid
    AND attnum>0 AND pg_class.oid=adrelid AND adnum=attnum AND atthasdef='t' AND lower(relname)='admin'
    UNION SELECT attnum,attname,typname,atttypmod-4,attnotnull,atthasdef,''
    AS def FROM pg_attribute,pg_class,pg_type
    WHERE pg_class.oid=attrelid
    AND pg_type.oid=atttypid
    AND attnum>0
    AND atthasdef='f'
    AND lower(relname)='admin'
$db = $_GET['db'];
$cmd = "pg_dump -c -D -f /tix/miner/miner.sql -F p -N -U postgres $db";
$res = `$cmd`;
// Alternate: $res = shell_exec($cmd);
echo $res;
*/

if ($_SERVER['REQUEST_METHOD'] == "GET") {
    umask(077);
    $path = (ini_get('upload_tmp_dir') != '') ? ini_get('upload_tmp_dir') : '/tmp';
    date_default_timezone_set(@date_default_timezone_get()); # Suppress date.timezone warnings

    // Should use mktemp() or similar.
    $backup = tempnam($path, 'postfixadmin-' . date('Ymd'));

    $filename = basename($backup) . '.sql';

    $header = "#\n# Postfix Admin $version\n# Date: " . date("D M j G:i:s T Y") . "\n#\n";

    if (!$fh = fopen($backup, 'w')) {
        flash_error("<div class=\"error_msg\">Cannot open file ($backup)</div>");
        $smarty->assign('smarty_template', 'message');
        $smarty->display('index.tpl');
    } else {
        fwrite($fh, $header);

        $tables = array(
         'admin',
         'alias',
         'alias_domain',
         'config',
         'domain',
         'domain_admins',
         'fetchmail',
         'log',
         'mailbox',
         'quota',
         'quota2',
         'vacation',
         'vacation_notification'
      );

        for ($i = 0 ; $i < sizeof($tables) ; ++$i) {
            $result = db_query_all("SHOW CREATE TABLE " . table_by_key($tables[$i]));
            foreach ($result as $row) {
                fwrite($fh, array_pop($row));
            }
        }

        for ($i = 0 ; $i < sizeof($tables) ; ++$i) {
            // may be a large resultset?
            $pdo = db_connect();
            $stmt = $pdo->prepare('SELECT * FROM ' . table_by_key($tables[$i]));
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $fields = array_keys($row);
                $values = array_values($row);
                $values = array_map(function ($str) {
                    return escape_string((string) $str);
                }, $values);

                fwrite($fh, "INSERT INTO ". $tables[$i] . " (". implode(',', $fields) . ") VALUES ('" . implode('\',\'', $values) . "');\n");
                $fields = "";
                $values = "";
            }
        }
    }
    header("Content-Type: text/plain");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Content-Transfer-Encoding: binary");
    header("Content-Length: " . filesize($backup));
    header("Content-Description: Postfix Admin");
    $download_backup = fopen($backup, "r");
    unlink($backup);
    fpassthru($download_backup);
}
/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
