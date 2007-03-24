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
If you still encounter any problems please check the documentation and website for more information.
<p />
Your donations keep this project running...
<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="image" src="https://www.paypal.com/en_US/i/btn/x-click-but04.gif" border="0" name="submit" alt="Make payments with PayPal - it's fast, free and secure!">
<input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----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-----END PKCS7-----">
</form>
<p />
<a href="http://high5.net/postfixadmin/">Postfix Admin</a><br />
<a href="http://forums.high5.net/index.php?showforum=7">Knowledge Base</a>
</body>
</html>
EOF;
}
?>
