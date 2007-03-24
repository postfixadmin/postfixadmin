<?
//
// If my_lib.php is called directly, redirect to login.php
//
if (ereg("my_lib.php", $PHP_SELF)) {
	header("Location: login.php");
}

include "site_lib.php";



//
// check_session
// Action: Check if a session already exists, if not redirect to login.php
// Call: check_session()
//
function check_session() {
	session_name("SessID");
	session_start();
	if (!session_is_registered("sessid")) {
		// if session is not registered redirect to login.php
		header("Location: login.php");
		exit;
	}
	$sessid[domain] = $_SESSION[sessid][domain];
	$sessid[username] = $_SESSION[sessid][username];
	return $sessid;
}



//
// check_admin
// Action: Check if user is admin and allowed to view the page
// Call: check_admin(string admin);
//
function check_admin($admin) {
	if ($admin == "N") {
		print_header();
		print "You are not allowed to view this page.\n";
		print_footer();
		exit;
	}
}



//
// check_string
// Action: checks if a string is valid and returns TRUE is this is the case.
// Call: check_string(string var)
//
function check_string($var) {
	return (preg_match('/^([A-Za-z ]+)+$/', $var));
}



//
// check_escape
// Action: checks to see if there are chars that need to be escaped
// Call: check_escape(string var)
//
function check_escape($var) {
	$search = array ("'<script[^>]*?>.*?</script>'si",
		"'<[\/\!]*?[^<>]*?>'si",
		"'\''i");

	$replace = array ("",
		"",
		"");

	$escaped = preg_replace ($search, $replace, $var);
	return $escaped;
}



//
// check_email
// Action: Checks if email is valid and returns TRUE if this is the case.
// Call: check_email(string email)
//
function check_email($email) { 
	return (preg_match('/^[-!#$%&\'*+\\.\/0-9=?A-Z^_{|}~]+' . '@' . '([-0-9A-Z]+\.)+' . '([0-9A-Z]){2,4}$/i', trim($email)));
}



//
// md5crypt
// Action: Creates an MD5 passwd that is readable by FreeBSD daemons
// Call: md5crypt(string cleartextpasswd)
//

$MAGIC = "$1$";
$ITOA64 = "./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";

function md5crypt($pw, $salt="", $magic="") {
	global $MAGIC;
	if ($magic == "") $magic = $MAGIC;
	if ($salt == "") $salt = create_salt(); 
	$slist = explode("$", $salt);
	if ($slist[0] == "1") $salt = $slist[1];
	$salt = substr($salt, 0, 8);
	$ctx = $pw . $magic . $salt;
	$final = hex2bin(md5($pw . $salt . $pw));
	for ($i=strlen($pw); $i>0; $i-=16) {
		if ($i > 16) 
			$ctx .= substr($final,0,16);
		else
			$ctx .= substr($final,0,$i);
	}
	$i = strlen($pw);
	while ($i > 0) {
		if ($i & 1) $ctx .= chr(0);
		else $ctx .= $pw[0];
		$i = $i >> 1;
	}
	$final = hex2bin(md5($ctx));
	for ($i=0;$i<1000;$i++) {
		$ctx1 = "";
    	if ($i & 1) $ctx1 .= $pw;
		else $ctx1 .= substr($final,0,16);
		if ($i % 3) $ctx1 .= $salt;
	  	if ($i % 7) $ctx1 .= $pw;
		if ($i & 1) $ctx1 .= substr($final,0,16);
		else $ctx1 .= $pw;
		$final = hex2bin(md5($ctx1));
	}
	$passwd = "";
	$passwd .= to64( ( (ord($final[0]) << 16) | (ord($final[6]) << 8) | (ord($final[12])) ), 4);
	$passwd .= to64( ( (ord($final[1]) << 16) | (ord($final[7]) << 8) | (ord($final[13])) ), 4);
	$passwd .= to64( ( (ord($final[2]) << 16) | (ord($final[8]) << 8) | (ord($final[14])) ), 4);
	$passwd .= to64( ( (ord($final[3]) << 16) | (ord($final[9]) << 8) | (ord($final[15])) ), 4);
	$passwd .= to64( ( (ord($final[4]) << 16) | (ord($final[10]) << 8) | (ord($final[5])) ), 4);
	$passwd .= to64( ord($final[11]), 2);
	return "$magic$salt\$$passwd";
}
function create_salt() {
	srand((double)microtime()*1000000);
	$salt = substr(md5(rand(0,9999999)), 0, 8);
	return $salt;
}
function hex2bin($str) {
	$len = strlen($str);
	$nstr = "";
	for ($i=0;$i<$len;$i+=2) {
		$num = sscanf(substr($str,$i,2), "%x");
		$nstr.=chr($num[0]);
	}
	return $nstr;
}
function to64($v, $n) {
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
// print_header
// Action: Prints out the default header for every page
// Call: print_header([string title],[string subtitle],[hex bgcolor])
//
function print_header($title = "", $subtitle = "") {
	if (empty($title)) {
		global $title;
	}
	if (empty($subtitle)) {
		global $subtitle;
	}
	print "<html>\n";
	print "<head>\n";
	if (file_exists(realpath("./stylesheet.css"))) {
		print "<link rel=stylesheet href=stylesheet.css>\n";
	}
	print "<title>$title $subtitle</title>\n";
	print "</head>\n";
	print "<body>\n";
	print "<center>\n";
}



//
// print_footer
// Action: Prints out the default footer for every page
// Call: print_footer()
//
function print_footer() {
	global $version;
	print "<hr class=footer>\n";
	print "<p class=footer>\n";
	print "$version<br>\n";
	print "2002 &copy High5!\n";
	print "</center>\n";
	print "</body>\n";
	print "</html>\n";
}



//
// print_error
// Action: Prints an error message and exits/dies
// Call: print_error(string error message);
//
function print_error($msg, $header = "YES") {
	if ($header == "YES") {
		print_header();
	}
	print "$msg";
	print_footer();
	exit;
}



//
// db_connect
// Action: Makes a connection to the database if it doesn't exist
// Call: db_connect()
//
function db_connect() {
	global $db_host;
	global $db_name;
	global $db_user;
	global $db_pass;
	$link = mysql_connect("$db_host", "$db_user", "$db_pass") or print_error("Could not connect to database server: <b>$db_host</b>.");
	$succes = mysql_select_db("$db_name", $link) or print_error("Could not select database: <b>$db_name</b>.");
	return ($link);
}



//
// db_query
// Action: Sends a query to the database and returns query result and number of rows
// Call: db_query(string query)
//
function db_query($query) {
	$link = db_connect();
	$result = mysql_query("$query", $link) or print_error("Could not query the table.<br>", "NO");
	// if $query was a select statement check the number of rows with mysql_num_rows().
	if (eregi("^select", $query)) {
		$number_rows = mysql_num_rows($result);
	// if $query was something else, UPDATE, DELETE or INSERT check the number of rows with
	// mysql_affected_rows().
	} else {
		$number_rows = mysql_affected_rows($link);
	}
	$return = array (
		"result" => $result,
		"rows" => $number_rows
	);
	return ($return);
}
?>
