<?php
// 
// Postfix Admin 
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// License Info: http://www.postfixadmin.com/?file=LICENSE.TXT
//
// File: functions.inc.php
//
//error_reporting  (E_NOTICE | E_ERROR | E_WARNING | E_PARSE);

if (ereg ("functions.inc.php", $_SERVER['PHP_SELF']))
{
   header ("Location: login.php");
   exit;
}

$version = "2.1.0";

//
// check_session
// Action: Check if a session already exists, if not redirect to login.php
// Call: check_session ()
//
function check_session ()
{
   session_start ();
   if (!session_is_registered ("sessid"))
   {
      header ("Location: login.php");
      exit;
   }
   $SESSID_USERNAME = $_SESSION['sessid']['username'];
   return $SESSID_USERNAME;
}

function check_user_session ()
{
   session_start ();
   if (!session_is_registered ("userid"))
   {
      header ("Location: login.php");
      exit;
   }
   $USERID_USERNAME = $_SESSION['userid']['username'];
   return $USERID_USERNAME;
}



//
// check_language
// Action: checks what language the browser uses
// Call: check_language
//
function check_language ()
{
   global $CONF;
   $supported_languages = array ('bg', 'ca', 'cn', 'cs', 'da', 'de', 'en', 'es', 'et', 'eu', 'fi', 'fo', 'fr', 'hu', 'is', 'it', 'mk', 'nl', 'nn', 'pl', 'pt-br', 'ru', 'sl', 'sv', 'tr', 'tw');
   $lang_array = preg_split ('/(\s*,\s*)/', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
   if (is_array ($lang_array))
   {
      $lang_first = strtolower ((trim (strval ($lang_array[0]))));
      $lang_first = substr ($lang_first, 0, 2);
      if (in_array ($lang_first, $supported_languages))
      {
         $lang = $lang_first;
      }
      else
      {
         $lang = $CONF['default_language'];
      }
   }
   else
   {
      $lang = $CONF['default_language'];
   }
   return $lang;
}



//
// check_string
// Action: checks if a string is valid and returns TRUE is this is the case.
// Call: check_string (string var)
//
function check_string ($var)
{
   if (preg_match ('/^([A-Za-z0-9 ]+)+$/', $var))
   {
      return true;
   }
   else
   {
      return false;
   }
} 



//
// check_email
// Action: Checks if email is valid and returns TRUE if this is the case.
// Call: check_email (string email)
//
function check_email ($email)
{
   if (preg_match ('/^[-!#$%&\'*+\\.\/0-9=?A-Z^_{|}~]+' . '@' . '([-0-9A-Z]+\.)+' . '([0-9A-Z]){2,4}$/i', trim ($email)))
   {
      return true;
   }
   else
   {
      return false;
   }
}



//
// escape_string
// Action: Escape a string
// Call: escape_string (string string)
//
function escape_string ($string)
{
   global $CONF;
   if (get_magic_quotes_gpc () == 0)
   {
      if ($CONF['database_type'] == "mysql")  $escaped_string = mysql_real_escape_string ($string);
      if ($CONF['database_type'] == "mysqli")  $escaped_string = mysqli_real_escape_string ($string);
      if ($CONF['database_type'] == "pgsql")  $escaped_string = pg_escape_string ($string);
   }
   else
   {
      $escaped_string = $string;
   }
   return $escaped_string;
}



//
// get_domain_properties
// Action: Get all the properties of a domain.
// Call: get_domain_properties (string domain)
//
function get_domain_properties ($domain)
{
   global $CONF;
   $list = array ();
   
   $result = db_query ("SELECT COUNT(*) FROM alias WHERE domain='$domain'");
   $row = db_row ($result['result']);
   $list['alias_count'] = $row[0];
   
   $result = db_query ("SELECT COUNT(*) FROM mailbox WHERE domain='$domain'");
   $row = db_row ($result['result']);
   $list['mailbox_count'] = $row[0];
   if ($CONF['alias_control'] == "NO")
   {
      $list['alias_count'] = $list['alias_count'] - $list['mailbox_count'];
   }
   else
   {
      $list['alias_count'] = $list['alias_count'];
   }
   
   $result = db_query ("SELECT * FROM domain WHERE domain='$domain'");
   $row = db_array ($result['result']);
   $list['description'] = $row['description'];
   $list['aliases'] = $row['aliases'];
   $list['mailboxes'] = $row['mailboxes'];
   $list['maxquota'] = $row['maxquota'];
   $list['transport'] = $row['transport'];
   $list['backupmx'] = $row['backupmx'];
   $list['created'] = $row['created'];
   $list['modified'] = $row['modified'];
   $list['active'] = $row['active'];

   if ($CONF['database_type'] == "pgsql")
   {
      if ($row['active'] == "t")
      {
         $list['active'] = 1;
      }
      else
      {
         $list['active'] = 0;
      }

      if ($row['backupmx'] == "t")
      {
         $list['backupmx'] = 1;
      }
      else
      {
         $list['backupmx'] = 0;
      }
   }
   else
   {
      $list['active'] = $row['active'];
      $list['backupmx'] = $row['backupmx'];
   }

   return $list;
}



//
// check_alias
// Action: Checks if the domain is still able to create aliases.
// Call: check_alias (string domain)
//
function check_alias ($domain)
{
   $limit = get_domain_properties ($domain);
   if ($limit['aliases'] == 0)
   {
      return true;
   }
   if ($limit['aliases'] < 0)
   {
      return false;
   }
   if ($limit['alias_count'] >= $limit['aliases'])
   {
      return false;
   }
   else
   {
      return true;
   }
}



//
// check_mailbox
// Action: Checks if the domain is still able to create mailboxes.
// Call: ceck_mailbox (string domain)
//
function check_mailbox ($domain)
{
   $limit = get_domain_properties ($domain);
   if ($limit['mailboxes'] == 0)
   {
      return true;
   }
   if ($limit['mailboxes'] < 0)
   {
      return false;
   }
   if ($limit['mailbox_count'] >= $limit['mailboxes'])
   {
      return false;
   }
   else
   {
      return true;
   }
}



//
// check_quota
// Action: Checks if the user is creating a mailbox with the correct quota
// Call: check_quota (string domain)
//
function check_quota ($quota, $domain)
{
   $limit = get_domain_properties ($domain);
   if ($limit['maxquota'] == 0)
   {
      return true;
   }
   if (($limit['maxquota'] < 0) and ($quota < 0))
   {
      return true;
   }
   if (($limit['maxquota'] > 0) and ($quota == 0))
   {
      return false;
   }
   if ($quota > $limit['maxquota'])
   {
      return false;
   }
   else
   {
      return true;
   }
}



//
// check_owner
// Action: Checks if the admin is the owner of the domain.
// Call: check_owner (string admin, string domain)
//
function check_owner ($username, $domain)
{
   $result = db_query ("SELECT * FROM domain_admins WHERE username='$username' AND domain='$domain' AND active='1'");
   if ($result['rows'] != 1)
   {
      return false;
   }
   else
   {
      return true;
   }
}



//
// list_domains_for_admin
// Action: Lists all the domains for an admin.
// Call: list_domains_for_admin (string admin)
//
function list_domains_for_admin ($username)
{
   $list = array ();
   
   $result = db_query ("SELECT * FROM domain LEFT JOIN domain_admins ON domain.domain=domain_admins.domain WHERE domain_admins.username='$username' AND domain.active='1' AND domain.backupmx='0' ORDER BY domain_admins.domain");
   if ($result['rows'] > 0)
   {
      $i = 0;
      while ($row = db_array ($result['result']))
      {
         $list[$i] = $row['domain'];
         $i++;
      }
   }
   return $list;
}



//
// list_domains
// Action: List all available domains.
// Call: list_domains ()
//
function list_domains ()
{
   $list = array();
   
   $result = db_query ("SELECT * FROM domain ORDER BY domain");
   if ($result['rows'] > 0)
   {
      $i = 0;
      while ($row = db_array ($result['result']))
      {
         $list[$i] = $row['domain'];
         $i++;
      }
   }
   return $list;
}



//
// admin_exist
// Action: Checks if the admin already exists.
// Call: admin_exist (string admin)
//
// was check_admin
//
function admin_exist ($username)
{
	$result = db_query ("SELECT * FROM admin WHERE username='$username'");
   if ($result['rows'] != 1)
   {
      return false;
   }
   else
   {
      return true;
   }
}

//
// domain_exist
// Action: Checks if the domain already exists.
// Call: domain_exist (string domain)
//
function domain_exist ($domain)
{
	$result = db_query ("SELECT * FROM domain WHERE domain='$domain'");
   if ($result['rows'] != 1)
   {
      return false;
   }
   else
   {
      return true;
   }
}


//
// list_admins
// Action: Lists all the admins
// Call: list_admins ()
//
// was admin_list_admins
//
function list_admins ()
{
   $list = "";
   
   $result = db_query ("SELECT * FROM admin ORDER BY username");
   if ($result['rows'] > 0)
   {
      $i = 0;
      while ($row = db_array ($result['result']))
      {
         $list[$i] = $row['username'];
         $i++;
      }
   }
   return $list;
}



//
// get_admin_properties
// Action: Get all the admin properties.
// Call: get_admin_properties (string admin)
function get_admin_properties ($username)
{
   $list = array ();
   
   $result = db_query ("SELECT COUNT(*) FROM domain_admins WHERE username='$username'");
   $row = db_row ($result['result']);
   $list['domain_count'] = $row[0];
   
   $result = db_query ("SELECT * FROM admin WHERE username='$username'");
   $row = db_array ($result['result']);
   $list['created'] = $row['created'];
   $list['modified'] = $row['modified'];
   $list['active'] = $row['active'];
   return $list;
}



// 
// encode_header
// Action: Encode a string according to RFC 1522 for use in headers if it contains 8-bit characters.
// Call: encode_header (string header, string charset)
//
function encode_header ($string, $default_charset) 
{
   if (strtolower ($default_charset) == 'iso-8859-1')
   {
      $string = str_replace ("\240",' ',$string);
   }

   $j = strlen ($string);
   $max_l = 75 - strlen ($default_charset) - 7;
   $aRet = array ();
   $ret = '';
   $iEncStart = $enc_init = false;
   $cur_l = $iOffset = 0;

   for ($i = 0; $i < $j; ++$i)
   {
      switch ($string{$i})
      {
         case '=':
         case '<':
         case '>':
         case ',':
         case '?':
         case '_':
         if ($iEncStart === false)
         {
            $iEncStart = $i;
         }
         $cur_l+=3;
         if ($cur_l > ($max_l-2))
         {
            $aRet[] = substr ($string,$iOffset,$iEncStart-$iOffset);
            $aRet[] = "=?$default_charset?Q?$ret?=";
            $iOffset = $i;
            $cur_l = 0;
            $ret = '';
            $iEncStart = false;
         }
         else
         {
            $ret .= sprintf ("=%02X",ord($string{$i}));
         }
         break;
         case '(':
         case ')':
         if ($iEncStart !== false)
         {
            $aRet[] = substr ($string,$iOffset,$iEncStart-$iOffset);
            $aRet[] = "=?$default_charset?Q?$ret?=";
            $iOffset = $i;
            $cur_l = 0;
            $ret = '';
            $iEncStart = false;
         }
         break;
         case ' ':
         if ($iEncStart !== false)
         {
            $cur_l++;
            if ($cur_l > $max_l)
            {
               $aRet[] = substr ($string,$iOffset,$iEncStart-$iOffset);
               $aRet[] = "=?$default_charset?Q?$ret?=";
               $iOffset = $i;
               $cur_l = 0;
               $ret = '';
               $iEncStart = false;
            }
            else
            {
               $ret .= '_';
            }
         }
         break;
         default:
         $k = ord ($string{$i});
         if ($k > 126)
         {
            if ($iEncStart === false)
            {
               // do not start encoding in the middle of a string, also take the rest of the word.
               $sLeadString = substr ($string,0,$i);
               $aLeadString = explode (' ',$sLeadString);
               $sToBeEncoded = array_pop ($aLeadString);                  
               $iEncStart = $i - strlen ($sToBeEncoded);
               $ret .= $sToBeEncoded;
               $cur_l += strlen ($sToBeEncoded);
            }
            $cur_l += 3;
            // first we add the encoded string that reached it's max size
            if ($cur_l > ($max_l-2))
            {
               $aRet[] = substr ($string,$iOffset,$iEncStart-$iOffset);
               $aRet[] = "=?$default_charset?Q?$ret?= ";
               $cur_l = 3;
               $ret = '';
               $iOffset = $i;
               $iEncStart = $i;
            }
            $enc_init = true;
            $ret .= sprintf ("=%02X", $k);
            }
            else
            {
            if ($iEncStart !== false)
            {
               $cur_l++;
               if ($cur_l > $max_l)
               {
                  $aRet[] = substr ($string,$iOffset,$iEncStart-$iOffset);
                  $aRet[] = "=?$default_charset?Q?$ret?=";
                  $iEncStart = false;
                  $iOffset = $i;
                  $cur_l = 0;
                  $ret = '';
                  }
                  else
                  {
                     $ret .= $string{$i};
                  }
               }
            }
            break;
         }
      }
      if ($enc_init)
      {
         if ($iEncStart !== false)
         {
            $aRet[] = substr ($string,$iOffset,$iEncStart-$iOffset);
            $aRet[] = "=?$default_charset?Q?$ret?=";
         }
         else
         {
            $aRet[] = substr ($string,$iOffset);
         }
         $string = implode ('',$aRet);
      }
   return $string;
}



//
// generate_password
// Action: Generates a random password
// Call: generate_password ()
//
function generate_password ()
{
   $password = substr (md5 (mt_rand ()), 0, 8);
   return $password;
}



//
// pacrypt
// Action: Encrypts password based on config settings
// Call: pacrypt (string cleartextpassword)
//
function pacrypt ($pw, $pw_db="")
{
   global $CONF;
   $password = "";
   $salt = "";

   if ($CONF['encrypt'] == 'md5crypt')
   {
      $split_salt = preg_split ('/\$/', $pw_db);
      if (isset ($split_salt[2])) $salt = $split_salt[2];

      $password = md5crypt ($pw, $salt);
   }

   if ($CONF['encrypt'] == 'system')
   {
      if (ereg ("\$1\$", $pw_db))
      {
         $split_salt = preg_split ('/\$/', $pw_db);
         $salt = $split_salt[2];
      }
      else
      {
         $salt = substr ($pw_db, 0, 2);
      }
      $password = crypt ($pw, $salt);
   }

   if ($CONF['encrypt'] == 'cleartext')
   {
      $password = $pw;
   }

   return $password;
}



//
// md5crypt
// Action: Creates MD5 encrypted password
// Call: md5crypt (string cleartextpassword)
//
$MAGIC = "$1$";
$ITOA64 = "./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";

function md5crypt ($pw, $salt="", $magic="")
{
   global $MAGIC;

   if ($magic == "") $magic = $MAGIC;
   if ($salt == "") $salt = create_salt (); 
   $slist = explode ("$", $salt);
   if ($slist[0] == "1") $salt = $slist[1];

   $salt = substr ($salt, 0, 8);
   $ctx = $pw . $magic . $salt;
   $final = hex2bin (md5 ($pw . $salt . $pw));

   for ($i=strlen ($pw); $i>0; $i-=16)
   {
      if ($i > 16)
      {
         $ctx .= substr ($final,0,16);
      }
      else
      {
         $ctx .= substr ($final,0,$i);
      }
   }
   $i = strlen ($pw);
   
   while ($i > 0)
   {
      if ($i & 1) $ctx .= chr (0);
      else $ctx .= $pw[0];
      $i = $i >> 1;
   }
   $final = hex2bin (md5 ($ctx));

   for ($i=0;$i<1000;$i++)
   {
      $ctx1 = "";
      if ($i & 1)
      {
         $ctx1 .= $pw;
      }
      else
      {
         $ctx1 .= substr ($final,0,16);
      }
      if ($i % 3) $ctx1 .= $salt;
      if ($i % 7) $ctx1 .= $pw;
      if ($i & 1)
      {
         $ctx1 .= substr ($final,0,16);
      }
      else
      {
         $ctx1 .= $pw;
      }
      $final = hex2bin (md5 ($ctx1));
   }
   $passwd = "";
   $passwd .= to64 (((ord ($final[0]) << 16) | (ord ($final[6]) << 8) | (ord ($final[12]))), 4);
   $passwd .= to64 (((ord ($final[1]) << 16) | (ord ($final[7]) << 8) | (ord ($final[13]))), 4);
   $passwd .= to64 (((ord ($final[2]) << 16) | (ord ($final[8]) << 8) | (ord ($final[14]))), 4);
   $passwd .= to64 (((ord ($final[3]) << 16) | (ord ($final[9]) << 8) | (ord ($final[15]))), 4);
   $passwd .= to64 (((ord ($final[4]) << 16) | (ord ($final[10]) << 8) | (ord ($final[5]))), 4);
   $passwd .= to64 (ord ($final[11]), 2);
   return "$magic$salt\$$passwd";
}

function create_salt ()
{
   srand ((double) microtime ()*1000000);
   $salt = substr (md5 (rand (0,9999999)), 0, 8);
   return $salt;
}

function hex2bin ($str)
{
   $len = strlen ($str);
   $nstr = "";
   for ($i=0;$i<$len;$i+=2)
   {
      $num = sscanf (substr ($str,$i,2), "%x");
      $nstr.=chr ($num[0]);
   }
   return $nstr;
}

function to64 ($v, $n)
{
   global $ITOA64;
   $ret = "";
   while (($n - 1) >= 0)
   {
      $n--;
      $ret .= $ITOA64[$v & 0x3f];
      $v = $v >> 6;
   }
   return $ret;
}



//
// smtp_mail
// Action: Sends email to new account.
// Call: smtp_mail (string To, string From, string Data)
//
function smtp_mail ($to, $from, $data)
{
   global $CONF;
   $smtp_server = $CONF['smtp_server'];
   $smtp_port = $CONF['smtp_port'];
   $errno = "0";
   $errstr = "0";
   $timeout = "30";
   
   $fh = @fsockopen ($smtp_server, $smtp_port, $errno, $errstr, $timeout);

   if (!$fh)
   {
      return false;
   }
   else
   {
      fputs ($fh, "EHLO $smtp_server\r\n");
      $res = fgets ($fh, 256);
      fputs ($fh, "MAIL FROM:<$from>\r\n");
      $res = fgets ($fh, 256);
      fputs ($fh, "RCPT TO:<$to>\r\n");
      $res = fgets ($fh, 256);
      fputs ($fh, "DATA\r\n");
      $res = fgets ($fh, 256);
      fputs ($fh, "$data\r\n.\r\n");
      $res = fgets ($fh, 256);
      fputs ($fh, "QUIT\r\n");
      $res = fgets ($fh, 256);
      fclose ($fh);
   }
   return true;
}



$DEBUG_TEXT = "\n
<p />\n
Please check the documentation and website for more information.\n
<p />\n
<a href=\"http://high5.net/postfixadmin/\">Postfix Admin</a><br />\n
<a href=\"http://forums.high5.net/index.php?showforum=7\">Knowledge Base</a>\n
";

//
// db_connect
// Action: Makes a connection to the database if it doesn't exist
// Call: db_connect ()
//
function db_connect ()
{
   global $CONF;
   global $DEBUG_TEXT;
   $link = "";

   if ($CONF['database_type'] == "mysql")
   {
      if (function_exists ("mysql_connect"))
      {
         $link = @mysql_connect ($CONF['database_host'], $CONF['database_user'], $CONF['database_password']) or die ("<p />DEBUG INFORMATION:<br />Connect: " .  mysql_error () . "$DEBUG_TEXT");
         $succes = @mysql_select_db ($CONF['database_name'], $link) or die ("<p />DEBUG INFORMATION:<br />MySQL Select Database: " .  mysql_error () . "$DEBUG_TEXT");
      }
      else
      {
         print "<p />DEBUG INFORMATION:<br />MySQL 3.x / 4.0 functions not available!<br />database_type = 'mysql' in config.inc.php, are you using a different database? $DEBUG_TEXT";
         die;
      }
   }

   if ($CONF['database_type'] == "mysqli")
   {
      if (function_exists ("mysqli_connect"))
      {
         $link = @mysqli_connect ($CONF['database_host'], $CONF['database_user'], $CONF['database_password']) or die ("<p />DEBUG INFORMATION:<br />Connect: " .  mysqli_connect_error () . "$DEBUG_TEXT");
         $succes = @mysqli_select_db ($link, $CONF['database_name']) or die ("<p />DEBUG INFORMATION:<br />MySQLi Select Database: " .  mysqli_error () . "$DEBUG_TEXT");
      }
      else
      {
         print "<p />DEBUG INFORMATION:<br />MySQL 4.1 functions not available!<br />database_type = 'mysqli' in config.inc.php, are you using a different database? $DEBUG_TEXT";
         die;
      }
   }

   if ($CONF['database_type'] == "pgsql")
   {
      if (function_exists ("pg_connect"))
      {
         $connect_string = "host=" . $CONF['database_host'] . " dbname=" . $CONF['database_name'] . " user=" . $CONF['database_user'] . " password=" . $CONF['database_password'];
         $link = @pg_connect ($connect_string) or die ("<p />DEBUG INFORMATION:<br />Connect: " .  pg_last_error () . "$DEBUG_TEXT");
      }
      else
      {
         print "<p />DEBUG INFORMATION:<br />PostgreSQL functions not available!<br />database_type = 'pgsql' in config.inc.php, are you using a different database? $DEBUG_TEXT";
         die;
      }
   }

   if ($link)
   {
      return $link;
   }
   else
   {
      print "DEBUG INFORMATION:<br />\n";
      print "Connect: Unable to connect to database<br />\n";
      print "<br />\n";
      print "Make sure that you have set the correct database type in the config.inc.php file<br />\n";
      print $DEBUG_TEXT;
      die;
   }
}



//
// db_query
// Action: Sends a query to the database and returns query result and number of rows
// Call: db_query (string query)
//
function db_query ($query)
{
   global $CONF;
   global $DEBUG_TEXT;
   $result = "";
   $number_rows = "";

   $link = db_connect ();

   // database prefix workaround
   if (!empty ($CONF['database_prefix']))
   {
      if (eregi ("^SELECT", $query))
      {
         $query = substr ($query, 0, 14) . $CONF['database_prefix'] . substr ($query, 14);
      }
      else
      {
         $query = substr ($query, 0, 6) . $CONF['database_prefix'] . substr ($query, 7);
      }
   }
   
   if ($CONF['database_type'] == "mysql") $result = @mysql_query ($query, $link) or die ("<p />DEBUG INFORMATION:<br />Invalid query: " . mysql_error() . "$DEBUG_TEXT");
   if ($CONF['database_type'] == "mysqli") $result = @mysqli_query ($link, $query) or die ("<p />DEBUG INFORMATION:<br />Invalid query: " . mysqli_error() . "$DEBUG_TEXT");
   if ($CONF['database_type'] == "pgsql")
   {
      if (eregi ("LIMIT", $query)) 
      { 
         $search = "/LIMIT (\w+), (\w+)/";
         $replace = "LIMIT \$2 OFFSET \$1";
         $query = preg_replace ($search, $replace, $query); 
      }
      $result = @pg_query ($link, $query) or die ("<p />DEBUG INFORMATION:<br />Invalid query: " . pg_last_error() . "$DEBUG_TEXT");
   } 

   if (eregi ("^SELECT", $query))
   {
      // if $query was a SELECT statement check the number of rows with [database_type]_num_rows ().
      if ($CONF['database_type'] == "mysql") $number_rows = mysql_num_rows ($result);
      if ($CONF['database_type'] == "mysqli") $number_rows = mysqli_num_rows ($result);      
      if ($CONF['database_type'] == "pgsql") $number_rows = pg_num_rows ($result);
   }
   else
   {
      // if $query was something else, UPDATE, DELETE or INSERT check the number of rows with
      // [database_type]_affected_rows ().
      if ($CONF['database_type'] == "mysql") $number_rows = mysql_affected_rows ($link);
      if ($CONF['database_type'] == "mysqli") $number_rows = mysqli_affected_rows ($link);
      if ($CONF['database_type'] == "pgsql") $number_rows = pg_affected_rows ($result);      
   }

   if ($CONF['database_type'] == "mysql") mysql_close ($link);
   if ($CONF['database_type'] == "mysqli") mysqli_close ($link);
   if ($CONF['database_type'] == "pgsql") pg_close ($link);      

   $return = array (
      "result" => $result,
      "rows" => $number_rows
   );
   return $return;
}



// db_row
// Action: Returns a row from a table
// Call: db_row (int result)
//
function db_row ($result)
{
   global $CONF;
   $row = "";
   if ($CONF['database_type'] == "mysql") $row = mysql_fetch_row ($result);
   if ($CONF['database_type'] == "mysqli") $row = mysqli_fetch_row ($result);
   if ($CONF['database_type'] == "pgsql") $row = pg_fetch_row ($result);
   return $row;
}



// db_array
// Action: Returns a row from a table
// Call: db_array (int result)
//
function db_array ($result)
{
   global $CONF;
   $row = "";
   if ($CONF['database_type'] == "mysql") $row = mysql_fetch_array ($result);
   if ($CONF['database_type'] == "mysqli") $row = mysqli_fetch_array ($result);
   if ($CONF['database_type'] == "pgsql") $row = pg_fetch_array ($result);   
   return $row;
}



// db_assoc
// Action: Returns a row from a table
// Call: db_assoc(int result)
//
function db_assoc ($result)
{
   global $CONF;
   $row = "";
   if ($CONF['database_type'] == "mysql") $row = mysql_fetch_assoc ($result);
   if ($CONF['database_type'] == "mysqli") $row = mysqli_fetch_assoc ($result);
   if ($CONF['database_type'] == "pgsql") $row = pg_fetch_assoc ($result);   
   return $row;
}



//
// db_delete
// Action: Deletes a row from a specified table
// Call: db_delete (string table, string where, string delete)
//
function db_delete ($table,$where,$delete)
{
   $result = db_query ("DELETE FROM $table WHERE $where='$delete'");
   if ($result['rows'] >= 1)
   {
      return $result['rows'];
   }
   else
   {
      return true;
   }
}



//
// db_log
// Action: Logs actions from admin
// Call: db_delete (string username, string domain, string action, string data)
//
function db_log ($username,$domain,$action,$data)
{
   global $CONF;
   $REMOTE_ADDR = $_SERVER['REMOTE_ADDR'];
   
   if ($CONF['logging'] == 'YES')
   {
      $result = db_query ("INSERT INTO log (timestamp,username,domain,action,data) VALUES (NOW(),'$username ($REMOTE_ADDR)','$domain','$action','$data')");
      if ($result['rows'] != 1)
      {
         return false;
      }
      else
      {
         return true;
      }
   }
}

?>
