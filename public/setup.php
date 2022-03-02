<?php
$PALANG = [];
require_once('common.php');
?>
<html lang="">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>

    <title>Postfix Admin - Setup</title>
    <link rel="shortcut icon" href="images/favicon.ico"/>
    <link rel="stylesheet" href="css/bootstrap-3.4.1-dist/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="css/bootstrap.css"/>

    <!-- https://www.srihash.org/ -->
    <script src="jquery-1.12.4.min.js"
            integrity="sha384-nvAa0+6Qg9clwYCGGPpDQLVpLNn0fRaROjHqs13t4Ggj3Ez50XnGQqc/r8MhnRDZ"
            crossorigin="anonymous"></script>

    <script src="css/bootstrap-3.4.1-dist/js/moment-with-locales.min.js"></script>
    <script src="css/bootstrap-3.4.1-dist/js/bootstrap.min.js"></script>
    <script src="css/bootstrap-3.4.1-dist/js/bootstrap-datetimepicker.min.js"></script>
</head>

<body>

<nav class="navbar navbar-default fixed-top">
    <div class="container-fluid">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar"
                    aria-expanded="false" aria-controls="navbar">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>

            <a class="navbar-brand" href='main.php'><img id="login_header_logo" src="images/postbox.png"
                                                         alt="Logo"/></a>

        </div>
    </div>
</nav>

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

$configSetupPassword = Config::read_string('setup_password');

$errors = [];

$configSetupDone = false;
$authenticated = false;
$old_setup_password = false;


if (strlen($configSetupPassword) == 73 && strpos($configSetupPassword, ':') == 32) {
    $old_setup_password = true;
} elseif ($configSetupPassword != 'changeme' && $configSetupPassword != '') {
    $configSetupDone = true;

    $pass = safepost('setup_password', 'invalid');

    if ($pass != 'invalid') {
        if (password_verify(safepost('setup_password', 'invalid'), $configSetupPassword)) {
            $authenticated = true;
        } else {
            $errors['setup_login_password'] = "Password verification failed.";
        }
    }
}

?>

<?php
$todo = '<span class="font-weight-bold text-warning">TODO</span>';
$tick = ' ✅ ';
?>


<div class="container">

    <div class="row">
        <h1 class="h1">Configure and Setup Postfixadmin</h1>

        <p>This page helps you setup PostfixAdmin. For further help see <a
                    href="https://github.com/postfixadmin/postfixadmin/tree/master/DOCUMENTS">the documentation</a>.</p>

        <?php

        if (!isset($_SERVER['HTTPS'])) {
            echo "<h2 class='h2 text-danger'>Warning: connection not secure, switch to https if possible</h2>";
        } ?>

        <div class="col-12">

            <ul>
                <li>
                    <?php

                    if ($configSetupDone) {
                        echo $tick . " setup_password configured";
                    } else {
                        echo $todo . " You need to have a setup_pasword hash configured in a <code>config.local.php</code> file";
                    }
                    ?>
                </li>
                <li>
                    <?php
                    if ($authenticated) {
                        echo $tick . " You are logged in with the setup_password, some environment and hosting checks are displayed below.";
                    } else {
                        echo $todo . " You need to authenticate using the setup_password before you can perform some environment and hosting checks.";
                    }
                    ?>
                </li>
            </ul>

            <?php if (!$authenticated) { ?>
                <p> One you have logged in with the setup_password, this page will ... </p>
                <ul>
                    <li> run some simple hosting/environment checks which may help identify problems with your
                        environment
                    </li>
                    <li> create/update your database of choice,</li>
                    <li> allow you to list / add super user accounts</li>

                </ul>
            <?php } ?>

        </div>

    </div>

    <?php
    if ($configSetupDone && !$authenticated) { ?>

        <div class="row">
            <div class="col-12">
                <h2 class="h2">Login with setup_password</h2>

                <form name="authenticate" class="col-2 form-horizontal" method="post">
                    <div class="form-group">
                        <label for="setup_password" class="col-sm-4 control-label">Setup password</label>
                        <div class="col-sm-4">
                            <input class="form-control" type="password" name="setup_password" minlength=5
                                   id="setup_password"
                                   value=""/>
                            <?= _error_field($errors, 'setup_login_password'); ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="col-sm-offset-4 col-sm-4">
                            <button class="btn btn-primary" type="submit" name="submit" value="setuppw">Login with
                                setup_password.
                            </button>
                        </div>
                    </div>
                </form>

                <p>If you've forgotten your super-admin password, you can generate a new one using the
                    <em>Generate</em>
                    form and update your <code>config.local.php</code></p>

            </div>
        </div>
        <?php
    } ?>

    <div class="row">
        <div class="col-12">
            <?php

            if (!$configSetupDone) {
                echo <<<EOF
                    <p><strong>For a new installation, you must generate a 'setup_password' to go into your config.local.php file.</strong></p>
                    <p>You can use the form below, or run something like the following in a shell - <code>php -r 'echo password_hash("password", PASSWORD_DEFAULT);'</code><p>
EOF;
            }

            if ($old_setup_password) {
                echo '<p class="text-danger"><strong>Your setup_password is in an obsolete format. As of PostfixAdmin 3.3 it needs regenerating.</strong>';
            }

            if (!$authenticated || !$configSetupDone) { ?>

                <h2>Generate setup_password</h2>

                <?php

                $form_error = '';
                $result = '';

                if (safepost('form') === "setuppw") {
                    $errors = [];

                    # "setup password" form submitted
                    if (safepost('setup_password', 'abc') != safepost('setup_password2')) {
                        $errors['setup_password'] = "The two passwords differ!";
                        $form_error = 'has-error';
                    } else {
                        $msgs = validate_password(safepost('setup_password'));

                        if (empty($msgs)) {
                            // form has been submitted; both fields filled in, so generate a new setup password.
                            $hash = password_hash(safepost('setup_password'), PASSWORD_DEFAULT);

                            $result = '<p>If you want to use the password you entered as setup password, edit config.inc.php or config.local.php and set</p>';
                            $result .= "<pre>\$CONF['setup_password'] = '$hash';</pre><p>After adding, refresh this page and log in using it.</p>";
                        } else {
                            $form_error = 'has-error';
                            $errors['setup_password'] = implode(', ', $msgs);
                        }
                    }
                }

                ?>

                <form name="setuppw" method="post" class="form-horizontal" action="setup.php">
                    <input type="hidden" name="form" value="setuppw"/>

                    <div class="form-group <?= $form_error ?>">

                        <label for="setup_password" class="col-sm-4 control-label">Setup password</label>
                        <div class="col-sm-4">
                            <input class="form-control" type="password" name="setup_password" minlength=5
                                   id="setup_password"
                                   autocomplete="new-password"
                                   value=""/>

                            <?= _error_field($errors, 'setup_password'); ?>

                        </div>

                    </div>

                    <div class="form-group <?= $form_error ?>">
                        <label for="setup_password2" class="col-sm-4 control-label">Setup password (again)</label>
                        <div class="col-sm-4">
                            <input class="form-control" type="password" name="setup_password2"
                                   minlength=5 id="setup_password2"
                                   autocomplete="new-password"
                                   value=""/>

                            <?= _error_field($errors, 'setup_password2'); ?>

                        </div>
                    </div>

                    <div class="form-group">
                        <div class="col-sm-offset-4 col-sm-4">
                            <button class="btn btn-primary" type="submit" name="submit" value="setuppw">Generate
                                setup_password
                                hash
                            </button>
                        </div>
                    </div>
                </form>

                <?= $result ?>


                <?php
            }  // end if(!$authenticated)?>
        </div>
    </div>

    <div class="row">
        <div clas="col-12">
            <h2 class="h2">Hosting Environment Check</h2>

            <?php
            $check = do_software_environment_check();

            if ($authenticated) {
                if (!empty($check['info'])) {
                    echo "<h3>Information</h3><ul>";
                    foreach ($check['info'] as $msg) {
                        echo "<li>{$tick} {$msg}</li>";
                    }
                    echo "</ul>";
                }

                if (!empty($check['warn'])) {
                    echo "<h3>Warnings</h3><ul>";
                    foreach ($check['warn'] as $msg) {
                        echo "<li class='text-warning'>⚠ {$msg}</li>";
                    }
                    echo "</ul>";
                }
                if (!empty($check['error'])) {
                    echo "<h3>Errors (MUST be fixed)</h3><ul>";
                    foreach ($check['error'] as $msg) {
                        echo "<li class='text-danger'>⛔{$msg}</li>";
                    }
                    echo "</ul>";
                }

                $php_error_log = ini_get('error_log');
            } else {
                if (!empty($check['error'])) {
                    echo '<h3 class="text-danger">Hosting Environment errors found. Login to see details.</h3>';
                }
                if (!empty($check['warn'])) {
                    echo '<h3 class="text-warning">Hosting Environment warnings found. Login to see details.</h3>';
                }
            }

            ?>

        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <h2 class="h2">Database Update</h2>

            <?php
                $db = false;
                try {
                    $db = db_connect();
                } catch (\Exception $e) {
                    echo "<p class='h3 text-danger'>Something went wrong while trying to connect to the database. A message should be logged - check PHP's error_log (" . ini_get('error_log') . ')</p>\n';
                    error_log("Couldn't perform PostfixAdmin database update - failed to connect to db? " . $e->getMessage() . " Trace: " . $e->getTraceAsString());
                }

                if ($db) {
                    echo "<p>Everything seems fine... attempting to create/update database structure</p>\n";
                    try {
                        require_once(dirname(__FILE__) . '/upgrade.php');
                    } catch (\Exception $e) {
                        if ($authenticated) {
                            echo "<p class='h3 text-danger'>Exception message: {$e->getMessage()} - check logs!</p>";
                        }
                        echo "<p class='h3 text-danger'>Something went wrong while trying to apply database updates, a message should be logged - check PHP's error_log (" . ini_get('error_log') . ')</p>\n';
                        error_log("Couldn't perform PostfixAdmin database update via upgrade.php - " . $e->getMessage() . " Trace: " . $e->getTraceAsString());
                    }
                } else {
                    echo "<h3 class='h3 text-danger'>Could not connect to database to perform updates; check PHP error log.</h3>";
                }
            ?>

        </div>
    </div>

    <?php
    if ($authenticated) {
        $setupMessage = '';

        if (safepost("submit") === "createadmin") {
            echo "<div class=row><div class='col-12'>";

            # "create admin" form submitted, make sure the correct setup password was specified.

            // XXX need to ensure domains table includes an 'ALL' entry.
            $table_domain = table_by_key('domain');
            $rows = db_query_all("SELECT * FROM $table_domain WHERE domain = 'ALL'");
            if (empty($rows)) {
                // all other fields should default through the schema.
                db_insert('domain', array('domain' => 'ALL', 'description' => '', 'transport' => ''));
            }

            $values = array(
                'username' => safepost('username'),
                'password' => safepost('password'),
                'password2' => safepost('password2'),
                'superadmin' => 1,
                'domains' => array(),
                'active' => 1,
            );

            list($error, $setupMessage, $errors) = create_admin($values);

            if ($error == 1) {
                $tUsername = htmlentities($values['username']);
                error_log("failed to add admin - " . json_encode([$error, $setupMessage, $errors]));
                echo "<p class='text-danger'>Admin addition failed; check field error messages or server logs.</p>";
            } else {
                // all good!.
                $setupMessage .= "<p>You are done with your basic setup. <b>You can now <a href='login.php'>login to PostfixAdmin</a> using the account you just created.</b></p>";
            }

            echo "</div>";
        }

        $table_admin = table_by_key('admin');
        $bool = db_get_boolean(true);
        $admins = db_query_all("SELECT * FROM $table_admin WHERE superadmin = '$bool' AND active = '$bool'");

        if (!empty($admins)) { ?>

            <div class="row">
                <div class="col-12">

                    <h2 class="h2">Super admins</h2>
                    <p>The following 'super-admin' accounts have already been added to the database.</p>
                    <ul>
                        <?php
                        foreach ($admins as $row) {
                            echo "<li>{$row['username']}</li>";
                        }
                        ?>
                    </ul>
                </div>
            </div>
        <?php } ?>

        <div class="row">
            <div class="col-12">
                <h2>Add Superadmin Account</h2>

                <form name="create_admin" class="form-horizontal" method="post">
                    <div class="form-group">
                        <label for="setup_password" class="col-sm-4 control-label">Setup password</label>
                        <div class="col-sm-4">
                            <input class="form-control" type="password" required="required"
                                   name="setup_password"
                                   minlength=5
                                   value=""/>

                        </div>
                    </div>


                    <div class="form-group">
                        <label for="username" class="col-sm-4 control-label"><?= $PALANG['admin'] ?></label>
                        <div class="col-sm-4">
                            <input class="form-control" type="text" required="required" name="username"
                                   minlength=5
                                   id="username"
                                   value=""/>

                            <?= _error_field($errors, 'username'); ?>

                        </div>
                    </div>


                    <div class="form-group">
                        <label for="password" class="col-sm-4 control-label"><?= $PALANG['password'] ?></label>
                        <div class="col-sm-4">
                            <input class="form-control" type="password" required=required
                                   name="password" minlength=5
                                   id="password" autocomplete="new-password"
                                   value=""/>
                            <?= _error_field($errors, 'password'); ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password2"
                               class="col-sm-4 control-label"><?= $PALANG['password_again'] ?></label>
                        <div class="col-sm-4">
                            <input class="form-control" type="password" required=required
                                   name="password2" minlength=5
                                   id="password2" autocomplete="new-password"
                                   value=""/>

                            <?= _error_field($errors, 'password2'); ?>

                        </div>
                    </div>


                    <div class="form-group">
                        <div class="col-sm-offset-4 col-sm-4">
                            <button class="btn btn-primary" type="submit" name="submit"
                                    value="createadmin"><?= $PALANG['pAdminCreate_admin_button'] ?>
                            </button>
                        </div>
                    </div>

                </form>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <p class="text-success"><?= $setupMessage ?></p>
            </div>
        </div>
        <?php
    }

    ?>


</div>

<footer class="footer mt-5 bg-dark">
    <div class="container text-center">
        <a target="_blank" rel="noopener" href="https://github.com/postfixadmin/postfixadmin/blob/master/DOCUMENTS/">Documentation</a>
        //
        <a target="_blank" rel="noopener"
           href="https://github.com/postfixadmin/postfixadmin/">Postfix Admin</a>
    </div>
</footer>

</body>
</html>

<?php

function _error_field($errors, $key) {
    if (!isset($errors[$key])) {
        return '';
    }
    return "<span style='color: #ff0000'>{$errors[$key]}</span>";
}


function create_admin($values) {
    define('POSTFIXADMIN_SETUP', 1); # avoids instant redirect to login.php after creating the admin

    $handler = new AdminHandler(1, 'setup.php');
    $formconf = $handler->webformConfig();

    if (!$handler->init($values['username'])) {
        return array(1, "", $handler->errormsg);
    }

    if (!$handler->set($values)) {
        return array(1, "", $handler->errormsg);
    }

    if (!$handler->save()) {
        return array(1, "", $handler->errormsg);
    }

    return array(
        0,
        $handler->infomsg['success'],
        array(),
    );
}

/**
 * @return array like: ['info' => string[], 'warn' => string[], 'error' => string[] ]
 */
function do_software_environment_check() {
    $CONF = Config::getInstance()->getAll();

    $warn = [];
    $error = [];
    $info = [];


//
    // Check for availability functions
//
    $f_phpversion = function_exists("phpversion");
    $f_apache_get_version = function_exists("apache_get_version");

    $m_pdo = extension_loaded("PDO");
    $m_pdo_mysql = extension_loaded("pdo_mysql");
    $m_pdo_pgsql = extension_loaded('pdo_pgsql');
    $m_pdo_sqlite = extension_loaded("pdo_sqlite");

    $f_session_start = function_exists("session_start");
    $f_preg_match = function_exists("preg_match");
    $f_mb_encode_mimeheader = function_exists("mb_encode_mimeheader");
    $f_imap_open = function_exists("imap_open");

    $file_local_config = realpath(__DIR__ . "/../config.local.php");

    // Fall back to looking in /etc/postfixadmin for config.local.php (Debian etc)
    // this check might produce a false positive if someone has a legacy PostfixAdmin installation.
    if (!file_exists($file_local_config) && is_dir('/etc/postfixadmin')) {
        if (file_exists('/etc/postfixadmin/config.local.php')) {
            $file_local_config = '/etc/postfixadmin/config.local.php';
        }
    }

    // Check for PHP version
    $phpversion = 'unknown-version';

    if ($f_phpversion) {
        if (version_compare(PHP_VERSION, '7.0.0', '<')) {
            $error[] = "Error: Depends on: PHP v7.0+. You must upgrade.";
        } else {
            $info[] = "PHP version - " . phpversion();
        }
    } else {
        $error[] = "Unable to check for PHP version. (PHP_VERSION not found?)";
    }

    // Check for Apache version
    if ($f_apache_get_version) {
        $info[] = "Webserver - " . apache_get_version();
    }


    $info[] = "Postfixadmin installed at - " . realpath(__DIR__);

    $error_log_file = ini_get('error_log');

    if (file_exists($error_log_file) && is_writable($error_log_file)) {
        $info[] = "PHP Error log (error_log) is - $error_log_file";
    }

    if (file_exists($error_log_file) && !is_writeable($error_log_file)) {
        $warn[] = "PHP Error log (error_log) is - $error_log_file, but is not writeable. Postfixadmin will be unable to log error(s)";
    }


    if (file_exists($file_local_config)) {
        $info[] = "config.local.php file found : " . realpath($file_local_config);
    } else {
        $warn[] = "Warning: config.local.php - NOT FOUND - It's Recommended to store your own settings in config.local.php instead of editing config.inc.php";
    }

    // Check if there is support for at least 1 database
    if (!$m_pdo and !$m_pdo_mysql and !$m_pdo_sqlite and !$m_pdo_pgsql) {
        $error[] = "There is no database (PDO) support in your PHP setup, you MUST install a suitable PHP PDO extension (e.g. pdo_pgsql, pdo_mysql or pdo_sqlite).";
    }

    if ($m_pdo_mysql) {
        $info[] = "Database - MySQL support available";
    } else {
        $info[] = "Database - MySQL (pdo_mysql) extension not found";
    }


    // PostgreSQL functions
    if ($m_pdo_pgsql) {
        $info[] = "Database - PostgreSQL support available ";
    } else {
        $warn[] = "Database - PostgreSQL (pdo_pgsql) extension not found";
    }

    if ($m_pdo_sqlite) {
        $info[] = "Database - SQLite support available";
    } else {
        $warn[] = "Database support - SQLite (pdo_sqlite) extension not found";
    }

    if (empty($CONF['encrypt'])) {
        $error[] = 'Password hashing - $CONF["encrypt"] is empty. Please check your config.inc.php / config.local.php file.';
    } else {
        $info[] = 'Password hashing - $CONF["encrypt"] = ' . $CONF['encrypt'];

        try {
            $output = pacrypt('foobar');
            if ($output == 'foobar') {
                $warn[] = "You appear to be using a cleartext \$CONF['encrypt'] setting. This is insecure. You have been warned. Your users deserve better";
            }
            $info[] = 'Password hashing - $CONF["encrypt"] - hash generation OK';
        } catch (\Exception $e) {
            $error[] = "Password Hashing - attempted to use configured encrypt backend ({$CONF['encrypt']}) triggered an error: " . $e->getMessage();

            if (is_writeable($error_log_file)) {
                $err = "Possibly helpful error_log messages - " . htmlspecialchars(
                        implode("",
                            array_slice(file($error_log_file), -4, 3)  // last three lines, might fail miserably if error_log is large.
                        )
                    );

                $error[] = nl2br($err);
            }

            $error[] = "You will have problems logging into PostfixAdmin.";

            if (preg_match('/^dovecot:/', $CONF['encrypt'])) {
                $error[] = "Check out our Dovecot documentation at https://github.com/postfixadmin/postfixadmin/blob/master/DOCUMENTS/DOVECOT.txt, specifically around '3. Permissions'.";
            }
        }
    }

    $link = null;
    $error_text = null;

    $dsn = 'Could not generate';

    try {
        $dsn = db_connection_string();

        $info[] = "Database connection configured OK (using PDO $dsn)";
        $link = db_connect();
        $info[] = "Database connection - Connected OK";
    } catch (Exception $e) {
        $error[] = "Database connection string : " . $dsn;
        $error[] = "Problem connecting to database, check database configuration (\$CONF['database_*'] entries in config.local.php)";
        $error[] = $e->getMessage();
    }


    // Session functions
    if ($f_session_start) {
        $info[] = "Depends on: PHP session support - OK";
    } else {
        $error[] = "Error: Depends on: PHP session support - NOT FOUND. (FreeBSD: portinstall php$phpversion-session ?)";
    }


    // PCRE functions
    if ($f_preg_match) {
        $info[] = "Depends on: PHP pcre support - OK";
    } else {
        $error[] = "Error: Depends on: PHP pcre support - NOT FOUND. (FreeBSD: portinstall php$phpversion-pcre)";
    }

    // Multibyte functions
    if ($f_mb_encode_mimeheader) {
        $info[] = "Depends on: PHP mbstring support - OK";
    } else {
        $error[] = "Error: Depends on: PHP mbstring support - NOT FOUND. (FreeBSD: portinstall php$phpversion-mbstring?)";
    }


    // Imap functions
    if ($f_imap_open) {
        $info[] = "Optional - PHP IMAP functions - OK";
    } else {
        $warn[] = "Warning: Optional dependency 'imap' extension missing, without this you may not be able to automate creation of sub-folders for new mailboxes";
    }


    return ['error' => $error, 'warn' => $warn, 'info' => $info];
}
