<?php
/**
 * Postfix Admin
 *
 * LICENSE
 * This source file is subject to the GPL license that is bundled with
 * this package in the file LICENSE.TXT.
 *
 * Further details on the project are available at http://postfixadmin.sf.net or https://github.com/postfixadmin/postfixadmin
 *
 * @version $Id$
 * @license GNU GPL v2 or later.
 *
 * File: index.php
 * Shows a sort-of welcome page.
 * Template File: -none-
 *
 * Template Variables: -none-
 *
 * Form POST \ GET Variables: -none-
 */

$CONF = array('configured' => false);

if (file_exists(dirname(__FILE__) . '/../config.inc.php')) {
    require_once(dirname(__FILE__) . '/../config.inc.php');
}

if ($CONF['configured'] === true) {
    header("Location: login.php");
    exit;
}
?>

<html>
    <head>
        <title>Welcome to Postfix Admin</title>
    </head>
    <body>
        <img id="login_header_logo" src="images/logo-default.png" />
        <h1>Welcome to Postfix Admin</h1>
        <h2>What is it?</h2>
        <p>Postfix Admin is a web based interface to configure and manage a Postfix based email server for many users.</p>
        <p>Postfix Admin can also be used to </p>
        <ul>
            <li>Forward email to other addresses</li>
            <li>Configure vacation/out-of-office auto responses</li>
            <li>Add/edit/remove mail accounts</li>
            <li>Add/edit/remove domains</li>
            <li>Broadcast emails to all users of the system</li>
            <li>Set quota on mailboxes</li>
            <li>And more...</li>
        </ul>

        <h2>Licensing</h2>
        <p>Postfix admin is released under the following license :</p>

        <code>
        This program is free software; you can redistribute it and/or modify
        it under the terms of the GNU General Public License 2 as published by 
        the Free Software Foundation. 
        </code>

        <p>See the following <a href="http://www.fsf.org/licenses/gpl-2.0.txt">FSF GPL2 page</a> for further information on the license.</p>

        <h2>What now?</h2>

        <ol>
            <li>Read the <a href='https://raw.githubusercontent.com/postfixadmin/postfixadmin/master/INSTALL.TXT'>INSTALL.TXT</a> file</li>
            <li>Configure Postfix to use your chosen database - see (for example) the following pages :
            <ul><!-- TODO: get newer URLs ... -->
                <li><a href="http://codepoets.co.uk/postfixadmin-postgresql-courier-squirrelmail-debian-etch-howto-tutorial">Postfix/PostgreSQL/Postfixadmin/Courier</a></li>
                <li><a href="http://bliki.rimuhosting.com/space/knowledgebase/linux/mail/postfixadmin+on+debian+sarge">Postfix/MySQL/Postfixadmin/Dovecot</a></li>
                <li><a href="http://gentoo-wiki.com/HOWTO_Setup_a_Virtual_Postfix/Courier_Mail_System_with_PostfixAdmin">Postfix/MySQL/Postfixamdin/Courier</a></li>
            </ul>
            <li>Use it</li>
        </ol>
        
        <p><b>When you have configured Postfixadmin, this page will be replaced with a login page.</b></p>
        <p>You can now run <a href="setup.php">setup</a> to make sure that all the PHP functions are available for Postfix Admin to run.<br />
        <p> If you still encounter any problems, please check the documentation and website for more information.</p>

        <h2>Postfix Admin Web sites</h2>
        <p>For further help, or documentation please check out -
        <ul>
            <li><a href="http://github.com/postfixadmin/postfixadmin">GitHub - Postfix Admin</a> web site</li>
            <li><a href="http://postfixadmin.org">Postfix Admin</a> web site<br /></li>
            <li><a href="http://sourceforge.net/forum/forum.php?forum_id=676076">Knowledge Base</a></li>
        </ul>
        </p>
        </p>
    </body>
</html>
<?php
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
