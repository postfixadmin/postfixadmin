<?php
//
// Postfix Admin
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// Licensed under GPL for more info check GPL-LICENSE.TXT
//
// File: index.php
//
// Template File: -none-
//
// Template Variables:
//
// -none-
//
// Form POST \ GET Variables:
//
// -none-
//
if (!file_exists (realpath ("./setup.php")))
{
    header ("Location: login.php");
    exit;
}
else
{
    print <<< EOF
<html>
    <head>
        <title>Welcome to Postfix Admin</title>
    </head>
    <body>
        <img id="login_header_logo" src="images/postbox.png" />
        <img id="login_header_logo2" src="images/postfixadmin2.png" />
        <h1>Welcome to Postfix Admin</h1>
        It seems that you are running this version of Postfix Admin for the first time.<br />
        <p />
        You can now run <a href="setup.php">setup</a> to make sure that all the functions are available for Postfix Admin to run.<br />
        <p />
        If you still encounter any problems, please check the documentation and website for more information.
        <p />
        <p />
        <a href="http://postfixadmin.org">Postfix Admin</a> web site<br />
        <a href="http://sourceforge.net/forum/forum.php?forum_id=676076">Knowledge Base</a>
    </body>
</html>
EOF;
}
?>
