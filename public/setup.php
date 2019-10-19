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
 * File: setup.php
 * Used to help ensure a server is setup appropriately during installation/setup.
 */

define('POSTFIXADMIN', 1); # by defining it here, common.php will not start a session.

require_once(dirname(__FILE__) . '/common.php'); # make sure correct common.php is used.

$CONF['show_header_text'] = 'NO';
$CONF['theme_favicon'] = 'images/favicon.ico';
$CONF['theme_logo'] = 'images/logo-default.png';
$CONF['theme_css'] = 'css/default.css';
require(dirname(__FILE__) . '/../templates/header.php');
?>

<div class='setup'>
    <h2>Postfix Admin Setup Checker</h2>

    <p>Running software:
        <ul>
            <?php
            //
            // Check for availability functions
            //
            $f_phpversion = function_exists("phpversion");
            $f_apache_get_version = function_exists("apache_get_version");
            $f_get_magic_quotes_gpc = function_exists("get_magic_quotes_gpc");
            $f_mysql_connect = function_exists("mysql_connect");
            $f_mysqli_connect = function_exists("mysqli_connect");
            $f_pg_connect = function_exists("pg_connect");
            $f_sqlite_open = class_exists("SQLite3");
            $f_pdo = class_exists('PDO');
            $f_session_start = function_exists("session_start");
            $f_preg_match = function_exists("preg_match");
            $f_mb_encode_mimeheader = function_exists("mb_encode_mimeheader");
            $f_imap_open = function_exists("imap_open");

            $file_config = file_exists(realpath("./../config.inc.php"));
            $file_local_config = file_exists(realpath("./../config.local.php"));

            // Fall back to looking in /etc/postfixadmin for config.local.php (Debian etc)
            if (!$file_local_config && is_dir('/etc/postfixadmin')) {
                $file_local_config = file_exists('/etc/postfixadmin/config.local.php');
            }

            $error = 0;

            $errormsg = array();

            //
            // Check for PHP version
            //
            $phpversion = 'unknown-version';

            if ($f_phpversion == 1) {
                if (version_compare(phpversion(), '5', '<')) {
                    print "<li><b>Error: Depends on: PHP v5+</b><br /></li>\n";
                    $error += 1;
                } elseif (version_compare(phpversion(), '7.0') < 0) {
                    $phpversion = 5;
                    print "<li><b>Recommended PHP version: >= 7.0, you have " . phpversion() . "; you should upgrade.</b></li>\n";
                } else {
                    print "<li>PHP version " . phpversion() . " - Good</li>\n";
                }
            } else {
                print "<li><b style='color: red'>DANGER</b> Unable to check for PHP version. (missing function: phpversion())</b></li>\n";
                $error++;
            }

            //
            // Check for Apache version
            //
            if ($f_apache_get_version == 1) {
                print "<li>" . apache_get_version() . "</li>\n";
            } else {
                # not running on Apache.
                # However postfixadmin _is_ running, so obviously we are on a supported webserver ;-))
                # No need to confuse the user with a warning.
            }

            print "</ul>";
            print "<p>Checking environment:\n";
            print "<ul>\n";

            //
            // Check for Magic Quotes
            //
            if ($f_get_magic_quotes_gpc == 1) {
                if (get_magic_quotes_gpc() == 0) {
                    print "<li>Magic Quotes: Disabled - OK</li>\n";
                } else {
                    print "<li><b>Warning: Magic Quotes: ON (internal work around to disable is in place)</b></li>\n";
                }
            }


            //
            // Check for config.local.php
            //
            if ($file_local_config == 1) {
                print "<li>Depends on: presence config.local.php - Found</li>\n";
            } else {
                print "<li><b>Warning: config.local.php - NOT FOUND</b><br /></li>\n";
                print "It's Recommended to store your own settings in config.local.php instead of editing config.inc.php<br />";
                print "Create the file, and edit as appropriate (e.g. select database type etc)<br />";
            }

            //
            // Check if there is support for at least 1 database
            //
            if (($f_mysql_connect == 0) and ($f_mysqli_connect == 0) and ($f_pg_connect == 0) and ($f_sqlite_open == 0)) {
                print "<li><b>Error: There is no database support in your PHP setup</b><br />\n";
                print "To install MySQL 3.23 or 4.0 support on FreeBSD:<br />\n";
                print "<pre>% cd /usr/ports/databases/php{$phpversion}-mysql/\n";
                print "% make clean install\n";
                print " - or with portupgrade -\n";
                print "% portinstall php{$phpversion}-mysql</pre>\n";
                if ($phpversion >= 5) {
                    print "To install MySQL 4.1 support on FreeBSD:<br />\n";
                    print "<pre>% cd /usr/ports/databases/php5-mysqli/\n";
                    print "% make clean install\n";
                    print " - or with portupgrade -\n";
                    print "% portinstall php5-mysqli</pre>\n";
                }
                print "To install PostgreSQL support on FreeBSD:<br />\n";
                print "<pre>% cd /usr/ports/databases/php{$phpversion}-pgsql/\n";
                print "% make clean install\n";
                print " - or with portupgrade -\n";
                print "% portinstall php{$phpversion}-pgsql</pre></li>\n";
                $error += 1;
            }

            if ($f_mysqli_connect == 1) {
                print "<li>Database - MySQL (mysqli_ functions) - Found\n";
                if (Config::read_string('database_type') != 'mysqli') {
                    print "<br>(change the database_type to 'mysqli' in config.local.php if you want to use MySQL)\n";
                }
                print "</li>";
            } else {
                print "<li>Database - MySQL (mysqli_ functions) - Not found</li>";
            }


            if (Config::read_string('database_type') == 'mysql') {
                print "<li><strong><span style='color: red'>Warning:</span> your configured database_type 'mysql' is deprecated; you must move to use 'mysqli'</strong> in your config.local.php.</li>\n";
                $error++;
            }

            //
            // PostgreSQL functions
            //
            if ($f_pg_connect == 1) {
                print "<li>Database : PostgreSQL support (pg_ functions) - Found\n";
                if (Config::read_string('database_type') != 'pgsql') {
                    print "<br>(change the database_type to 'pgsql' in config.local.php if you want to use PostgreSQL)\n";
                }
                print "</li>";
            } else {
                print "<li>Database - PostgreSQL (pg_ functions) - Not found</li>";
            }

            if ($f_sqlite_open == 1) {
                print "<li>Database : SQLite support (SQLite3) - Found \n";
                if (Config::read_string('database_type') != 'sqlite') {
                    print "<br>(change the database_type to 'sqlite' in config.local.php if you want to use SQLite)\n";
                }
                print "</li>";
            } else {
                print "<li>Database - SQLite (SQLite3) - Not found</li>";
            }

            //
            // Database connection
            //
            $link = null;
            $error_text = null;

            try {
                $link = db_connect();
            } catch (Exception $e) {
                $error_text = $e->getMessage();
            }

            if (!empty($link) && $error_text == "") {
                print "<li>Testing database connection (using {$CONF['database_type']}) - Success</li>";
            } else {
                print "<li><b style='color: red'>Error: Can't connect to database</b><br />\n";
                print "Please check the \$CONF['database_*'] parameters in config.local.php.<br />\n";
                print "$error_text</li>\n";
                $error++;
            }

            //
            // Session functions
            //
            if ($f_session_start == 1) {
                print "<li>Depends on: session - OK</li>\n";
            } else {
                print "<li><b>Error: Depends on: session - NOT FOUND</b><br />\n";
                print "To install session support on FreeBSD:<br />\n";
                print "<pre>% cd /usr/ports/www/php$phpversion-session/\n";
                print "% make clean install\n";
                print " - or with portupgrade -\n";
                print "% portinstall php$phpversion-session</pre></li>\n";
                $error += 1;
            }

            //
            // PCRE functions
            //
            if ($f_preg_match == 1) {
                print "<li>Depends on: pcre - Found</li>\n";
            } else {
                print "<li><b>Error: Depends on: pcre - NOT FOUND</b><br />\n";
                print "To install pcre support on FreeBSD:<br />\n";
                print "<pre>% cd /usr/ports/devel/php$phpversion-pcre/\n";
                print "% make clean install\n";
                print " - or with portupgrade -\n";
                print "% portinstall php$phpversion-pcre</pre></li>\n";
                $error += 1;
            }

            //
            // Multibyte functions
            //
            if ($f_mb_encode_mimeheader == 1) {
                print "<li>Depends on: multibyte string - Found</li>\n";
            } else {
                print "<li><b>Error: Depends on: multibyte string - NOT FOUND</b><br />\n";
                print "To install multibyte string support, install php$phpversion-mbstring</li>\n";
                $error += 1;
            }


            //
            // Imap functions
            //
            if ($f_imap_open == 1) {
                print "<li>IMAP functions - Found</li>\n";
            } else {
                print "<li><b>Warning: May depend on: IMAP functions - Not Found</b><br />\n";
                print "To install IMAP support, install php$phpversion-imap<br />\n";
                print "Without IMAP support, you won't be able to create subfolders when creating mailboxes.</li>\n";
            }


            //
            // If PHP <7.0, require random_compat works. Currently we bundle it via the Phar extension.
            //

            if (version_compare(phpversion(), "7.0", '<')
                && !extension_loaded('Phar')
                && $CONF['configured']
                && $CONF['encrypt'] == 'php_crypt') {
                print "<li>PHP before 7.0 requires 'Phar' extension support for <strong>secure</strong> random_int() function fallback";
                print "<br/>Either enable the 'Phar' extension, or install the random_compat library files from <a href='https://github.com/paragonie/random_compat'>https://github.com/paragonie/random_compat</a> and include/require them from functions.inc.php";
                print "<br/>PostfixAdmin has bundled lib/random_compat.phar but it's not usable on your installation due to the missing Phar extension.</li>";
                $error += 1;
            }


            print "</ul>";

            if ($error != 0) {
                print "<p><b>Please fix the errors listed above.</b></p>";
            } else {
                print "<p>Everything seems fine... attempting to create/update database structure</p>\n";
                require_once(dirname(__FILE__) . '/upgrade.php');

                $tUsername = '';
                $setupMessage = '';
                $lostpw_error = 0;

                $setuppw = "";
                if (isset($CONF['setup_password'])) {
                    $setuppw = $CONF['setup_password'];
                }

                if (safepost("form") == "setuppw") {
                    # "setup password" form submitted
                    if (safepost('setup_password') != safepost('setup_password2')) {
                        $setupMessage = "The two passwords differ!";
                        $lostpw_error = 1;
                    } else {
                        list($lostpw_error, $lostpw_result) = check_setup_password(safepost('setup_password'), 1);
                        $setupMessage = $lostpw_result;
                        $setuppw = "changed";
                    }
                } elseif (safepost("form") == "createadmin") {
                    # "create admin" form submitted
                    list($pw_check_error, $pw_check_result) = check_setup_password(safepost('setup_password'));
                    if ($pw_check_result != 'pass_OK') {
                        $error += 1;
                        $setupMessage = $pw_check_result;
                    }

                    if ($error == 0 && $pw_check_result == 'pass_OK') {
                        // XXX need to ensure domains table includes an 'ALL' entry.
                        $table_domain = table_by_key('domain');
                        $rows = db_query_all("SELECT * FROM $table_domain WHERE domain = 'ALL'");
                        if (empty($rows)) {
                            db_insert('domain', array('domain' => 'ALL', 'description' => '', 'transport' => '')); // all other fields should default through the schema.
                        }

                        $values = array(
                            'username' => safepost('username'),
                            'password' => safepost('password'),
                            'password2' => safepost('password2'),
                            'superadmin' => 1,
                            'domains' => array(),
                            'active' => 1,
                        );

                        list($error, $setupMessage, $errormsg) = create_admin($values);

                        if ($error != 0) {
                            $tUsername = htmlentities($values['username']);
                        } else {
                            $setupMessage .= "<p>You are done with your basic setup. ";
                            $setupMessage .= "<p><b>You can now <a href='login.php'>login to PostfixAdmin</a> using the account you just created.</b>";
                        }
                    }
                }


                if (!isset($_SERVER['HTTPS'])) {
                    echo "<h2>Warning: connection not secure, switch to https if possible</h2>";
                } ?>

                <div class="standout"><?php print $setupMessage; ?></div>

                <?php
                $change = "Change";

                if (Config::read_string('setup_password') == '' || Config::read_string('setup_password') == 'changeme') {
                    echo <<<EOF
                    <p><strong>For a new installation, you need to generate a 'setup_password' to go into your config.local.php file.</strong></p>
                    <p>You can use the form below, or run something like <pre>php -r 'echo "somesalt:" . sha1("somesalt:" . "password");'</pre> in a shell, after changing the salt.<p>
EOF;
                    $change = "Generate";
                } ?>

                <h2><?= $change ?> $CONF['setup_password']</h2>

                <div id="edit_form">
                    <form name="setuppw" method="post" action="setup.php">
                        <input type="hidden" name="form" value="setuppw"/>
                        <table>
                            <tr>
                                <td><label for="setup_password">Setup password</label></td>
                                <td><input class="flat" type="password" name="setup_password" minlength=5 id="setup_password" value=""/></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td><label for="setup_password2">Setup password (again)</label></td>
                                <td><input class="flat" type="password" name="setup_password2" minlength=5 id="setup_password2" value=""/></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="hlp_center"><input class="button" type="submit" name="submit" value="Generate password hash"/></td>
                            </tr>
                        </table>
                    </form>
                </div>

                <?php
                if ($change != 'Generate') { ?>

                    <h2>Add a SuperAdmin Account</h2>

                    <div id="edit_form">
                        <form name="create_admin" method="post">
                            <input type="hidden" name="form" value="createadmin"/>
                            <table>
                                <tr>
                                    <td><label for="setup_password">Setup password</label></td>
                                    <td><input id=setup_password class="flat" type="password" name="setup_password" value=""/></td>
                                    <td><?= _error_field($errormsg, 'setup_password'); ?><?php print $PALANG['setup_password'] ?></td>
                                </tr>
                                <tr>
                                    <td><label for="username"><?php print $PALANG['admin'] . ":"; ?></label></td>
                                    <td><input id="username" class="flat" type="text" name="username" value="<?php print $tUsername; ?>"/></td>
                                    <td><?= _error_field($errormsg, 'username'); ?><?php print $PALANG['email_address'] ?></td>
                                </tr>
                                <tr>
                                    <td><label for="password"><?php print $PALANG['password'] . ":"; ?></label></td>
                                    <td><input id="password" class="flat" type="password" name="password"/></td>
                                    <td><?= _error_field($errormsg, 'password'); ?></td>
                                </tr>
                                <tr>
                                    <td><label for="password2"><?php print $PALANG['password_again'] . ":"; ?></label></td>
                                    <td><input id="password2" class="flat" type="password" name="password2"/></td>
                                    <td><?= _error_field($errormsg, 'password2'); ?></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="hlp_center"><input class="button" type="submit" name="submit" value="<?php print $PALANG['pAdminCreate_admin_button']; ?>"/></td>
                                </tr>
                            </table>
                        </form>
                    </div>

                    <?php
                }
            } ?>
    <p>Since version 2.3 there is no requirement to delete setup.php</p>
    <p>Check the config.inc.php file for any other settings that you may need to change.</p>

</div>
</body>
</html>
<?php

function _error_field($errors, $key) {
    if (!isset($errors[$key])) {
        return '';
    }
    return "<span style='color: red'>{$errors[$key]}</span>";
}

function generate_setup_password_salt() {
    $salt = time() . '*' . $_SERVER['REMOTE_ADDR'] . '*' . mt_rand(0, 60000);
    $salt = md5($salt);
    return $salt;
}

function encrypt_setup_password($password, $salt) {
    return $salt . ':' . sha1($salt . ':' . $password);
}


/*
    returns: array(
        'error' => 0 (or 1),
        'message => text
    )
*/
function check_setup_password($password, $lostpw_mode = 0) {
    global $CONF;
    $error = 1; # be pessimistic

    $setuppw = "";
    if (isset($CONF['setup_password'])) {
        $setuppw = $CONF['setup_password'];
    }

    list($confsalt, $confpass, $trash) = explode(':', $setuppw . '::');
    $pass = encrypt_setup_password($password, $confsalt);

    $validpass = validate_password($password);

    if ($password == "") { # no password specified?
        $result = "Setup password must be specified<br />If you didn't set up a setup password yet, enter the password you want to use.";
    } elseif (count($validpass) > 0) {
        $result = $validpass[0]; # TODO: honor all error messages, not only the first one
    } elseif ($pass == $setuppw && $lostpw_mode == 0) { # correct passsword (and not asking for a new password)
        $result = "pass_OK";
        $error = 0;
    } else {
        $pass = encrypt_setup_password($password, generate_setup_password_salt());
        $result = "";
        if ($lostpw_mode == 1) {
            $error = 0; # non-matching password is expected when the user asks for a new password
        } else {
            $result = '<p><b>Setup password not specified correctly</b></p>';
        }
        $result .= '<p>If you want to use the password you entered as setup password, edit config.inc.php or config.local.php and set</p>';
        $result .= "<pre>\$CONF['setup_password'] = '$pass';</pre>";
    }
    return array($error, $result);
}

function create_admin($values) {
    DEFINE('POSTFIXADMIN_SETUP', 1); # avoids instant redirect to login.php after creating the admin

    $handler = new AdminHandler(1, 'setup.php');
    $formconf = $handler->webformConfig();

    if (!$handler->init($values['username'])) {
        return array(1, "", $handler->errormsg);
    }

    if (!$handler->set($values)) {
        return array(1, "", $handler->errormsg);
    }

    if (!$handler->store()) {
        return array(1, "", $handler->errormsg);
    }

    return array(
        0,
        $handler->infomsg['success'],
        array(),
    );
}


/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
