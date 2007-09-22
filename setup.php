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
?>
<html>
<head>
<title>Postfix Admin Setup Checker</title>
</head>
<body>
<img id="login_header_logo" src="images/postbox.png" />
<img id="login_header_logo" src="images/postfixadmin2.png" />
<h2>Postfix Admin Setup Checker 1.0.0</h2>
Running software:<br />
<p />
<?php
//
// Check for availablilty functions
//
$f_phpversion = function_exists ("phpversion");
$f_apache_get_version = function_exists ("apache_get_version");
$f_get_magic_quotes_gpc = function_exists ("get_magic_quotes_gpc");
$f_mysql_connect = function_exists ("mysql_connect");
$f_mysqli_connect = function_exists ("mysqli_connect");
$f_pg_connect = function_exists ("pg_connect");
$f_session_start = function_exists ("session_start");
$f_preg_match = function_exists ("preg_match");

$file_config = file_exists (realpath ("./config.inc.php"));

$error = 0;

//
// Check for PHP version
//
if ($f_phpversion == 1)
{
   if (phpversion() < 5) $phpversion = 4;
   if (phpversion() >= 5) $phpversion = 5;
   print "- PHP version " . phpversion () . "<br />\n";
}
else
{
   print "<li><b>Unable to check for PHP version. (missing function: phpversion())</b><br />\n";
}
print "<p />\n";

//
// Check for Apache version
//
if ($f_apache_get_version == 1)
{
   print "- " . apache_get_version() . "<br /><p />\n";
}
else
{
   print "<li><b>Unable to check for Apache version. (missing function: apache_get_version())</b><br />\n";
}
print "<p />\n";

print "Checking for dependencies:<br />\n";
print "<p />\n";

//
// Check for Magic Quotes
//
if ($f_get_magic_quotes_gpc == 1)
{
   if (get_magic_quotes_gpc () == 0)
   {
      print "- Magic Quotes: Disabled - OK<br /><p />\n";
   }
   else
   {
      print "<li><b>Warning: Magic Quotes: ON (internal workaround used)</b><br /><p />\n";   
   }
}
else
{
   print "<li><b>Unable to check for Magic Quotes. (missing function: get_magic_quotes_gpc())</b><br />\n";
}
print "<p />\n";


//
// Check for config.inc.php
//
if ($file_config == 1)
{
   print "- Depends on: presence config.inc.php - OK<br />\n";
}
else
{
   print "<li><b>Error: Depends on: presence config.inc.php - NOT FOUND</b><br />\n";
   print "Create the file.<br />";
   print "For example:<br />\n";
   print "<pre>% cp config.inc.php.sample config.inc.php</pre>\n";
   $error =+ 1;
}
print "<p />\n";

//
// Check if there is support for at least 1 database
//
if (($f_mysql_connect == 0) and ($f_mysqli_connect == 0) and ($f_pg_connect == 0))
{
   print "<li><b>Error: There is no database support in your PHP setup</b><br />\n";
   print "To install MySQL 3.23 or 4.0 support on FreeBSD:<br />\n";
   print "<pre>% cd /usr/ports/databases/php$phpversion-mysql/\n";
   print "% make clean install\n";
   print " - or with portupgrade -\n";
   print "% portinstall php$phpversion-mysql</pre>\n";
   if ($phpversion >= 5)
   {
      print "To install MySQL 4.1 support on FreeBSD:<br />\n";
      print "<pre>% cd /usr/ports/databases/php5-mysqli/\n";
      print "% make clean install\n";
      print " - or with portupgrade -\n";
      print "% portinstall php5-mysqli</pre>\n";
   }
   print "To install PostgreSQL support on FreeBSD:<br />\n";
   print "<pre>% cd /usr/ports/databases/php$phpversion-pgsql/\n";
   print "% make clean install\n";
   print " - or with portupgrade -\n";
   print "% portinstall php$phpversion-pgsql</pre>\n";
   $error =+ 1;
}
//
// MySQL 3.23, 4.0 functions
//
if ($f_mysql_connect == 1)
{
   print "- Depends on: MySQL 3.23, 4.0 - OK<br />\n";
}
print "<p />\n";

//
// MySQL 4.1 functions
//
if ($phpversion >= 5)
{
   if ($f_mysqli_connect == 1)
   {
      print "- Depends on: MySQL 4.1 - OK (change the database_type in config.inc.php!!)<br />\n";
   }
}
print "<p />\n";

//
// PostgreSQL functions
//
if ($f_pg_connect == 1)
{
   print "- Depends on: PostgreSQL - OK (change the database_type in config.inc.php!!)<br />\n";
}
print "<p />\n";

//
// Session functions
//
if ($f_session_start == 1)
{
   print "- Depends on: session - OK<br />\n";
}
else
{
   print "<li><b>Error: Depends on: session - NOT FOUND</b><br />\n";
   print "To install session support on FreeBSD:<br />\n";
   print "<pre>% cd /usr/ports/www/php$phpversion-session/\n";
   print "% make clean install\n";
   print " - or with portupgrade -\n";
   print "% portinstall php$phpversion-session</pre>\n";
   $error =+ 1;
}
print "<p />\n";

//
// PCRE functions
//
if ($f_preg_match == 1)
{
   print "- Depends on: pcre - OK<br />\n";
}
else
{
   print "<li><b>Error: Depends on: pcre - NOT FOUND</b><br />\n";
   print "To install pcre support on FreeBSD:<br />\n";
   print "<pre>% cd /usr/ports/devel/php$phpversion-pcre/\n";
   print "% make clean install\n";
   print " - or with portupgrade -\n";
   print "% portinstall php$phpversion-pcre</pre>\n";
   $error =+ 1;
}
print "<p />\n";

if ($error == 0)
{
   print "Everything seems fine... you are ready to rock & roll!</br>\n";
   print "<b>Make sure you delete this setup.php file!</b><br />\n";
   print "Also check the config.inc.php file for any settings that you might need to change!<br />\n";
   print "Click here to go to the <a href=\"admin\">admin section</a> (make sure that your .htaccess is setup properly)\n";
}
?>
</body>
</html>
