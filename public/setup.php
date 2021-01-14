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

require_once(dirname(__FILE__) . '/common.php'); # make sure correct common.php is used.

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

<html lang="">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>

    <title>Postfix Admin - Setup</title>
    <link rel="shortcut icon" href="images/favicon.ico"/>
    <link rel="stylesheet" href="css/bootstrap-3.4.1-dist/css/bootstrap.min.css"/>

    <!-- https://www.srihash.org/ -->
    <script src="jquery-1.12.4.min.js"
            integrity="sha384-nvAa0+6Qg9clwYCGGPpDQLVpLNn0fRaROjHqs13t4Ggj3Ez50XnGQqc/r8MhnRDZ" crossorigin="anonymous"
    ></script>

    <script src="css/bootstrap-3.4.1-dist/js/moment-with-locales.min.js"></script>
    <script src="css/bootstrap-3.4.1-dist/js/bootstrap.min.js"></script>
    <script src="css/bootstrap-3.4.1-dist/js/bootstrap-datetimepicker.min.js"></script>
</head>
<body>

<nav class="navbar navbar-default ">
    <div class="container">
        <div class="navbar-header">
            <a class="navbar-brand" href='main.php'>
                <img id="login_header_logo" src="images/logo-default.png"
                     alt="Logo"/></a>
        </div>
    </div>
</nav>

<?php
$todo = '<span class="font-weight-bold text-primary">TODO</span>';
$authenticatedLabel = $todo;

$configSetupLabel = $todo;
$tick = ' ✅ ';


if ($configSetupDone) {
    $configSetupLabel = $tick;

    if ($authenticated) {
        $authenticatedLabel = $tick;
    }
}
?>

<div class="container">

    <div class="row">
        <?php

        if (!isset($_SERVER['HTTPS'])) {
            echo "<h2 class='h2 text-danger'>Warning: connection not secure, switch to https if possible</h2>";
        } ?>

        <div class="col-12">
            <h1>Configure and Setup Postfixadmin</h1>

            <ul>
                <li><?= $configSetupLabel ?> You need to have a setup_password configured in a
                    <code>config.local.php</code> file.
                </li>
                <li><?= $authenticatedLabel ?> Login using your setup password.</li>
                <li>Then you can run some self tests to check compatability with
                    Postfixadmin
                </li>
                <li>Create / update your database of choice</li>
                <li>and Add a new super user account</li>
            </ul>

        </div>

    </div>


    <?php
    if ($configSetupDone && !$authenticated) { ?>

        <div class="row">

            <h2 class="h2">Login with setup_password</h2>

            <p>If you've forgotten your super-admin password, you can generate a new one using the <em>Generate</em>
                form
                and update your <code>config.local.php</code></p>

            <form name="authenticate" class="form-horizontal" method="post">

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
        </div>
        <?php
    } ?>


    <div class="row">
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

        <h2>Generate setup_password hash</h2>

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
                    $result .= "<pre>\$CONF['setup_password'] = '$hash';</pre><p>After adding, refresh this page and login using it.</p>";
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
                    <button class="btn btn-primary" type="submit" name="submit" value="setuppw">Generate setup_password hash
                    </button>
                </div>
            </div>
        </form>

        <?= $result ?>

    </div>

<?php
}  // end if(!$authenticated)?>

    <div class="row">

        <h2>Hosting Environment Check</h2>

        <?php
        $check = do_software_environment_check();

        if ($authenticated) {
            if (!empty($check['error'])) {
                echo "<p><p>Errors were found with your environment. These will be displayed once you've configured a setup_password and confirmed it.</p>";
            }

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

    <div class="row">

        <h2>Database Update</h2>

        <?php
        if ($authenticated) {
            print "<p>Everything seems fine... attempting to create/update database structure</p>\n";
            require_once(dirname(__FILE__) . '/upgrade.php');
        } else {
            echo "<h3 class='text-warning'>Please login to see perform database update.</h3>";
        }
        ?>

    </div>

    <?php


    if ($authenticated) {
        $setupMessage = '';

        if (safepost("submit") === "createadmin") {
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
        } ?>
        <div class="row">
            <h2>Add Superadmin Account</h2>

            <form name="create_admin" class="form-horizontal" method="post">
                <div class="form-group">
                    <label for="setup_password" class="col-sm-4 control-label">Setup password</label>
                    <div class="col-sm-4">
                        <input class="form-control" type="password" required="required" name="setup_password"
                               minlength=5
                               value=""/>

                    </div>
                </div>


                <div class="form-group">
                    <label for="username" class="col-sm-4 control-label"><?= $PALANG['admin'] ?></label>
                    <div class="col-sm-4">
                        <input class="form-control" type="text" required="required" name="username" minlength=5
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
                    <label for="password2" class="col-sm-4 control-label"><?= $PALANG['password_again'] ?></label>
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

        <p class="text-success"><?= $setupMessage ?></p>
        <?php
    }

    ?>
</div>

</div>

<footer class="footer mt-5 bg-dark">
    <div class="container text-center"><a target="_blank" rel="noopener"
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
 * @return array['info' => string[], 'warn' => string[], 'error' => string[] ]
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

    $file_local_config = file_exists(realpath("./../config.local.php"));

    // Fall back to looking in /etc/postfixadmin for config.local.php (Debian etc)
    if (!$file_local_config && is_dir('/etc/postfixadmin')) {
        $file_local_config = file_exists('/etc/postfixadmin/config.local.php');
    }

//
    // Check for PHP version
//
    $phpversion = 'unknown-version';

    if ($f_phpversion == 1) {
        if (version_compare(PHP_VERSION, '7.0.0', '<')) {
            $error[] = "Error: Depends on: PHP v7.0+. You must upgrade.";
        } else {
            $info[] = "PHP version " . phpversion();
        }
    } else {
        $error[] = "Unable to check for PHP version. (PHP_VERSION not found?)";
    }

//
    // Check for Apache version
//
    if ($f_apache_get_version == 1) {
        $info[] = apache_get_version();
    }

//
    // Check for config.local.php
//
    if ($file_local_config == 1) {
        $info[] = "Depends on: presence config.local.php - Found";
    } else {
        $warn[] = "<b>Warning: config.local.php - NOT FOUND - It's Recommended to store your own settings in config.local.php instead of editing config.inc.php";
    }

    // Check if there is support for at least 1 database
    if (($m_pdo == 0) and ($m_pdo_mysql == 0) and ($m_pdo_sqlite == 0) and ($m_pdo_pgsql == 0)) {
        $error[] = "There is no database (PDO) support in your PHP setup, you MUST install a suitable PHP PDO extension (e.g. pdo_pgsql, pdo_mysql or pdo_sqlite).";
    }

    if ($m_pdo_mysql == 1) {
        $info[] = "Database - PDO MySQL - Found";
    } else {
        $info[] = "Database - MySQL (pdo_mysql) extension not found";
    }

//
    // PostgreSQL functions
//
    if ($m_pdo_pgsql == 1) {
        $info[] = "Database support : PDO PostgreSQL - Found ";
        if (Config::read_string('database_type') != 'pgsql') {
            $warn[] = "Change the database_type to 'pgsql' in config.local.php if you want to use PostgreSQL";
        }
    } else {
        $warn[] = "Database - PostgreSQL (pdo_pgsql) extension not found";
    }

    if ($m_pdo_sqlite == 1) {
        $info[] = "Database support : PDO SQLite - Found";
        if (Config::read_string('database_type') != 'sqlite') {
            $warn[] = "Change the database_type to 'sqlite' in config.local.php if you want to use SQLite";
        }
    } else {
        $warn[] = "Database support - SQLite (pdo_sqlite) extension not found";
    }

    $link = null;
    $error_text = null;

    try {
        $link = db_connect();
    } catch (Exception $e) {
        $error_text = $e->getMessage();
    }


    if (!empty($link) && $error_text == "") {
        $info[] = "Testing database connection (using config) - Success";
    } else {
        $error[] = "Error: Can't connect to database - please check the \$CONF['database_*'] parameters in config.local.php : $error_text";
    }

//
    // Session functions
//
    if ($f_session_start == 1) {
        $info[] = "Depends on: session - OK";
    } else {
        $error[] = "Error: Depends on: session - NOT FOUND. (FreeBSD: portinstall php$phpversion-session ?)";
    }

//
    // PCRE functions
//
    if ($f_preg_match == 1) {
        $info[] = "Depends on: pcre - Found";
    } else {
        $error[] = "Error: Depends on: pcre - NOT FOUND. (FreeBSD: portinstall php$phpversion-pcre)";
    }

//
    // Multibyte functions
//
    if ($f_mb_encode_mimeheader == 1) {
        $info[] = "Depends on: multibyte string - Found";
    } else {
        $error[] = "Error: Depends on: multibyte string - mbstring extension missing. (FreeBSD: portinstall php$phpversion-mbstring?)";
    }


//
    // Imap functions
//
    if ($f_imap_open == 1) {
        $info[] = "IMAP functions - Found";
    } else {
        $warn[] = "Warning: Optional dependency 'imap' extension missing, without this you may not be able to automcate creation of subfolders for new mailboxes";
    }


    return ['error' => $error, 'warn' => $warn, 'info' => $info];
}

?>
