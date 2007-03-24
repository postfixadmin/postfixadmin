<?php
//
// File: functions.inc.php
//
if (ereg ("functions.inc.php", $_SERVER['PHP_SELF']))
{
   header ("Location: login.php");
   exit;
}

$version = "v2.0.1";

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
   return (preg_match ('/^[-!#$%&\'*+\\.\/0-9=?A-Z^_{|}~]+' . '@' . '([-0-9A-Z]+\.)+' . '([0-9A-Z]){2,4}$/i', trim($email)));
}



//
// get_domain_properties
// Action: Get all the properties of a domain.
// Call: get_domain_properties (string domain)
//
function get_domain_properties ($domain)
{
   $result = db_query ("SELECT * FROM alias WHERE domain='$domain'");
   $list['alias_count'] = $result['rows'];
   
   $result = db_query ("SELECT * FROM mailbox WHERE domain='$domain'");
   $list['mailbox_count'] = $result['rows'];
   $list['alias_count'] = $list['alias_count'] - $list['mailbox_count'];
   
   $result = db_query ("SELECT * FROM domain WHERE domain='$domain'");
   $row = mysql_fetch_array ($result['result']);
   $list['description'] = $row['description'];
   $list['aliases'] = $row['aliases'];
   $list['mailboxes'] = $row['mailboxes'];
   $list['maxquota'] = $row['maxquota'];
   $list['created'] = $row['created'];
   $list['modified'] = $row['modified'];
   $list['active'] = $row['active'];

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
   if ($limit['aliases'] >= 0)
   {
      if ($limit['alias_count'] >= $limit['aliases'])
      {
         return false;
      }
   }
   return true;
}



//
// check_mailbox
// Action: Checks if the domain is still able to create mailboxes.
// Call: ceck_mailbox (string domain)
//
function check_mailbox ($domain)
{
   $limit = get_domain_properties ($domain);
   if ($limit['mailboxes'] >= 0)
   {
      if ($limit['mailbox_count'] >= $limit['mailboxes'])
      {
         return false;
      }
   }
   return true;
}



//
// check_quota
// Action: Checks if the user is creating a mailbox with the correct quota
// Call: check_quota (string domain)
//
function check_quota ($quota, $domain)
{
   $limit = get_domain_properties ($domain);
   if ($limit['maxquota'] >= 0)
   {
      if ($quota > $limit['maxquota'])
      {
         return false;
      }
   }
   return true;
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
   $result = db_query ("SELECT * FROM domain LEFT JOIN domain_admins ON domain.domain=domain_admins.domain WHERE domain_admins.username='$username' AND domain.active='1' ORDER BY domain_admins.domain");
   if ($result['rows'] > 0)
   {
      $i = 0;
      while ($row = mysql_fetch_array ($result['result']))
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
   $result = db_query ("SELECT * FROM domain ORDER BY domain");
   if ($result['rows'] > 0)
   {
      $i = 0;
      while ($row = mysql_fetch_array ($result['result']))
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
   $result = db_query ("SELECT * FROM admin ORDER BY username");
   if ($result['rows'] > 0)
   {
      $i = 0;
      while ($row = mysql_fetch_array ($result['result']))
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
   $result = db_query ("SELECT * FROM domain_admins WHERE username='$username'");
   $list['domain_count'] = $result['rows'];
   
   $result = db_query ("SELECT * FROM admin WHERE username='$username'");
   $row = mysql_fetch_array ($result['result']);
   $list['created'] = $row['created'];
   $list['modified'] = $row['modified'];
   $list['active'] = $row['active'];

   return $list;
}



//
// pacrypt
// Action: Encrypts password based on config settings
// Call: pacrypt (string cleartextpassword)
//
function pacrypt ($pw, $salt="")
{
   global $CONF;
   
   if ($CONF['encrypt'] == 'md5crypt')
   {
      $password = md5crypt ($pw, $salt);
   }

   if ($CONF['encrypt'] == 'system')
   {
      $password = crypt ($pw, $salt);
   }

   if ($CONF['encrypt'] == 'cleartext')
   {
      $password = $pw;
   }

   return $password;
}



////////////////////////////////////////////////////////////////////////////////
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
   if ($salt == "") $salt = create_salt(); 
   $slist = explode ("$", $salt);
   if ($slist[0] == "1") $salt = $slist[1];
   $salt = substr ($salt, 0, 8);
   $ctx = $pw . $magic . $salt;
   $final = hex2bin (md5 ($pw . $salt . $pw));
   for ($i=strlen ($pw); $i>0; $i-=16) {
      if ($i > 16) 
         $ctx .= substr ($final,0,16);
      else
         $ctx .= substr ($final,0,$i);
   }
   $i = strlen ($pw);
   while ($i > 0) {
      if ($i & 1) $ctx .= chr (0);
      else $ctx .= $pw[0];
      $i = $i >> 1;
   }
   $final = hex2bin (md5 ($ctx));
   for ($i=0;$i<1000;$i++) {
      $ctx1 = "";
       if ($i & 1) $ctx1 .= $pw;
      else $ctx1 .= substr ($final,0,16);
      if ($i % 3) $ctx1 .= $salt;
        if ($i % 7) $ctx1 .= $pw;
      if ($i & 1) $ctx1 .= substr ($final,0,16);
      else $ctx1 .= $pw;
      $final = hex2bin (md5 ($ctx1));
   }
   $passwd = "";
   $passwd .= to64 ( ( (ord ($final[0]) << 16) | (ord ($final[6]) << 8) | (ord ($final[12])) ), 4);
   $passwd .= to64 ( ( (ord ($final[1]) << 16) | (ord ($final[7]) << 8) | (ord ($final[13])) ), 4);
   $passwd .= to64 ( ( (ord ($final[2]) << 16) | (ord ($final[8]) << 8) | (ord ($final[14])) ), 4);
   $passwd .= to64 ( ( (ord ($final[3]) << 16) | (ord ($final[9]) << 8) | (ord ($final[15])) ), 4);
   $passwd .= to64 ( ( (ord ($final[4]) << 16) | (ord ($final[10]) << 8) | (ord ($final[5])) ), 4);
   $passwd .= to64 ( ord ($final[11]), 2);
   return "$magic$salt\$$passwd";
}

function create_salt ()
{
   srand ((double)microtime ()*1000000);
   $salt = substr (md5 (rand (0,9999999)), 0, 8);
   return $salt;
}

function hex2bin ($str)
{
   $len = strlen ($str);
   $nstr = "";
   for ($i=0;$i<$len;$i+=2) {
      $num = sscanf (substr ($str,$i,2), "%x");
      $nstr.=chr ($num[0]);
   }
   return $nstr;
}

function to64 ($v, $n)
{
   global $ITOA64;
   $ret = "";
   while (($n - 1) >= 0) {
      $n--;
      $ret .= $ITOA64[$v & 0x3f];
      $v = $v >> 6;
   }
   return $ret;
}



//
// db_connect
// Action: Makes a connection to the database if it doesn't exist
// Call: db_connect ()
//
function db_connect ()
{
   global $CONF;
   $link = @mysql_connect ($CONF['database_host'], $CONF['database_user'], $CONF['database_password']) or die ("<p />DEBUG INFORMATION:<br />Connect: " .  mysql_error ());
   $succes = @mysql_select_db ($CONF['database_name'], $link) or die ("<p />DEBUG INFORMATION:<br />MySQL Select Database: " .  mysql_error ());
   return $link;
}



//
// db_query
// Action: Sends a query to the database and returns query result and number of rows
// Call: db_query (string query)
//
function db_query ($query)
{
   global $CONF;
   $link = db_connect ();
   $result = @mysql_query ($query, $link) or die ("<p />DEBUG INFORMATION:<br />Invalid query: " . mysql_error());
   if (eregi ("^select", $query))
   {
      // if $query was a select statement check the number of rows with mysql_num_rows ().
      $number_rows = mysql_num_rows ($result);
   }
   else
   {
      // if $query was something else, UPDATE, DELETE or INSERT check the number of rows with
      // mysql_affected_rows ().
      $number_rows = mysql_affected_rows ($link);
   }
   $return = array (
      "result" => $result,
      "rows" => $number_rows
   );
   return $return;
}



//
// db_delete
// Action: Deletes a row from a specified table
// Call db_delete (string table, string where, string delete)
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
   
   if ($CONF['logging'] == 'YES')
   {
      $result = db_query ("INSERT INTO log (timestamp,username,domain,action,data) VALUES (NOW(),'$username','$domain','$action','$data')");
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
