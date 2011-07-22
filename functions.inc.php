<?php
/** 
 * Postfix Admin 
 * 
 * LICENSE 
 * This source file is subject to the GPL license that is bundled with  
 * this package in the file LICENSE.TXT. 
 * 
 * Further details on the project are available at : 
 *     http://www.postfixadmin.com or http://postfixadmin.sf.net 
 * 
 * @version $Id$ 
 * @license GNU GPL v2 or later. 
 * 
 * File: functions.inc.php
 * Contains re-usable code.
 */

$version = '2.4 develop';

/**
 * check_session
 *  Action: Check if a session already exists, if not redirect to login.php
 * Call: check_session ()
 * @return String username (e.g. foo@example.com)
 */
function authentication_get_username() {
    global $CONF;

    if (defined('POSTFIXADMIN_CLI')) {
        return 'CLI';
    }

    if (!isset($_SESSION['sessid'])) {
        header ("Location: login.php");
        exit(0);
    }
    $SESSID_USERNAME = $_SESSION['sessid']['username'];
    return $SESSID_USERNAME;
}

/**
 * Returns the type of user - either 'user' or 'admin' 
 * Returns false if neither (E.g. if not logged in)
 * @return String admin or user or (boolean) false.
 */
function authentication_get_usertype() {
    if(isset($_SESSION['sessid'])) {
        if(isset($_SESSION['sessid']['type'])) {
            return $_SESSION['sessid']['type'];
        }
    }
    return false;
}
/**
 *
 * Used to determine whether a user has a particular role.
 * @param String role-name. (E.g. admin, global-admin or user)
 * @return boolean True if they have the requested role in their session.
 * Note, user < admin < global-admin
 */
function authentication_has_role($role) {
    global $CONF;
    if(isset($_SESSION['sessid'])) {
        if(isset($_SESSION['sessid']['roles'])) {
            if(in_array($role, $_SESSION['sessid']['roles'])) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Used to enforce that $user has a particular role when 
 * viewing a page.
 * If they are lacking a role, redirect them to 
 * $CONF['postfix_admin_url']/login.php
 *
 * Note, user < admin < global-admin
 */
function authentication_require_role($role) {
    global $CONF;
    // redirect to appropriate page?
    if(authentication_has_role($role)) {
        return True;
    }
    if($role === 'user') {
        header("Location: " . $CONF['postfix_admin_url'] . '/users/login.php');
    } else {
        header("Location: " . $CONF['postfix_admin_url'] . "/login.php");
    }
    exit(0);
}
/**
 * @return boolean TRUE if a admin, FALSE otherwise.
 */
function authentication_is_admin() {
    return authentication_get_usertype() == 'admin';
}

/**
 * @return boolean TRUE if a user, FALSE otherwise.
 */
function authentication_is_user() {
    return authentication_get_usertype() == 'user';
}


/**
 * Add an error message for display on the next page that is rendered.
 * @param String message to show. 
 *
 * Stores string in session. Flushed through header template.
 * @see _flash_string()
 */
function flash_error($string) {
    _flash_string('error', $string);
}

/**
 * Used to display an info message on successful update.
 * @param String $string
 * Stores data in sessio.
 * @see _flash_string()
 */
function flash_info($string) {
    _flash_string('info', $string);
}
/**
 * 'Private' method used for flash_info() and flash_error().
 */
function _flash_string($type, $string) {
    if(!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = array();
    }
    if(!isset($_SESSION['flash'][$type])) {
        $_SESSION['flash'][$type] = array();
    }
    $_SESSION['flash'][$type][] = $string;
}

//
// check_language
// Action: checks what language the browser uses
// Call: check_language
// Parameter: $use_post - set to 0 if $_POST should NOT be read
//
function check_language ($use_post = 1) {
    global $CONF;
    global $supported_languages; # from languages/languages.php

    $lang = $CONF['default_language'];

    if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $lang_array = preg_split ('/(\s*,\s*)/', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        if (safecookie('lang')) {
            array_unshift($lang_array, safecookie('lang')); # prefer language from cookie
        }
        if ( $use_post && safepost('lang')) {
            array_unshift($lang_array, safepost('lang')); # but prefer $_POST['lang'] even more
        }

        for($i = 0; $i < count($lang_array); $i++) {
            $lang_next = $lang_array[$i];
            $lang_next = strtolower(trim($lang_next));
            if(array_key_exists($lang_next, $supported_languages)) {
                $lang = $lang_next;
                break;
            }
        }
    }
    return $lang;
}

//
// language_selector
// Action: returns a language selector dropdown with the browser (or cookie) language preselected
// Call: language_selector()
//
function language_selector() {
    global $supported_languages; # from languages/languages.php

    $current_lang = check_language();

    $selector = '<select name="lang" xml:lang="en" dir="ltr">';

    foreach($supported_languages as $lang => $lang_name) {
        if ($lang == $current_lang) {
            $selected = ' selected="selected"';
        } else {
            $selected = '';
        }
        $selector .= "<option value='$lang'$selected>$lang_name</option>";
    }
    $selector .= "</select>";
    return $selector;
}

//
// check_string
// Action: checks if a string is valid and returns TRUE if this is the case.
// Call: check_string (string var)
//
function check_string ($var) {
    if (preg_match ('/^([A-Za-z0-9 ]+)+$/', $var)) {
        return true;
    } else {
        return false;
    }
}



//
// check_domain
// Action: Checks if domain is valid and returns TRUE if this is the case.
// Call: check_domain (string domain)
//
// TODO: make check_domain able to handle as example .local domains
function check_domain ($domain) {
    global $CONF;
    global $PALANG;

    if (!preg_match ('/^([-0-9A-Z]+\.)+' . '([0-9A-Z]){2,6}$/i', ($domain))) {
        flash_error(sprintf($PALANG['pInvalidDomainRegex'], htmlentities($domain)));
        return false;
    }

    if (isset($CONF['emailcheck_resolve_domain']) && 'YES' == $CONF['emailcheck_resolve_domain'] && 'WINDOWS'!=(strtoupper(substr(php_uname('s'), 0, 7)))) {

        // Look for an AAAA, A, or MX record for the domain

        if(function_exists('checkdnsrr')) {
            // AAAA (IPv6) is only available in PHP v. >= 5
            if (version_compare(phpversion(), "5.0.0", ">=")) {
                if (checkdnsrr($domain,'AAAA')) return true;
            }
            if (checkdnsrr($domain,'A')) return true;
            if (checkdnsrr($domain,'MX')) return true;
            flash_error(sprintf($PALANG['pInvalidDomainDNS'], htmlentities($domain)));
            return false;
        } else {
            flash_error("emailcheck_resolve_domain is enabled, but function (checkdnsrr) missing!");
        }
    }
    
    return true;
}


/**
 * check_email
 * Checks if an email is valid - if it is, return true, else false.
 * @param String $email - a string that may be an email address.
 * @return boolean true if it's an email address, else false.
 * TODO: make check_email able to handle already added domains
 */
function check_email ($email) {
    global $CONF;
    global $PALANG;

    $ce_email=$email;

    //strip the vacation domain out if we are using it
    //and change from blah#foo.com@autoreply.foo.com to blah@foo.com
    if ($CONF['vacation'] == 'YES') { 
        $vacation_domain = $CONF['vacation_domain'];
        $ce_email = preg_replace("/@$vacation_domain/", '', $ce_email);
        $ce_email = preg_replace("/#/", '@', $ce_email);
    }

    // Perform non-domain-part sanity checks
    if (!preg_match ('/^[-!#$%&\'*+\\.\/0-9=?A-Z^_{|}~]+' . '@' . '[^@]+$/i', trim ($ce_email))) {
        flash_error($PALANG['pInvalidMailRegex']);
        return false;
    }

    // Determine domain name
    $matches=array();
    if (!preg_match('|@(.+)$|',$ce_email,$matches)) {
        flash_error($PALANG['pInvalidMailRegex']);
        return false;
    }
    $domain=$matches[1];

    # check domain name
    return check_domain($domain);
}



/**
 * Clean a string, escaping any meta characters that could be
 * used to disrupt an SQL string. i.e. "'" => "\'" etc.
 *
 * @param String (or Array) 
 * @return String (or Array) of cleaned data, suitable for use within an SQL
 *    statement.
 */
function escape_string ($string) {
    global $CONF;
    // if the string is actually an array, do a recursive cleaning.
    // Note, the array keys are not cleaned.
    if(is_array($string)) {
        $clean = array();
        foreach(array_keys($string) as $row) {
            $clean[$row] = escape_string($string[$row]);  
        }
        return $clean;
    }
    if (get_magic_quotes_gpc ()) {
        $string = stripslashes($string);
    }
    if (!is_numeric($string)) {
        $link = db_connect();
        if ($CONF['database_type'] == "mysql") {
            $escaped_string = mysql_real_escape_string($string, $link);
        }
        if ($CONF['database_type'] == "mysqli") {
            $escaped_string = mysqli_real_escape_string($link, $string);
        }
        if ($CONF['database_type'] == "pgsql") {
            // php 5.2+ allows for $link to be specified.
            if (version_compare(phpversion(), "5.2.0", ">=")) {
                $escaped_string = pg_escape_string($link, $string);
            } else {
                $escaped_string = pg_escape_string($string);
            }
        }
    } else {
        $escaped_string = $string;
    }
    return $escaped_string;
}


/**
 * safeget
 * Action: get value from $_GET[$param], or $default if $_GET[$param] is not set
 * Call: $param = safeget('param')   # replaces $param = $_GET['param']
 *       - or -
 *  $param = safeget('param', 'default')
 *
 *  @param String parameter name.
 *  @param String (optional) - default value if key is not set.
 *  @return String 
 */
function safeget ($param, $default="") {
    $retval=$default;
    if (isset($_GET[$param])) $retval=$_GET[$param];
    return $retval;
}

/**
 * safepost - similar to safeget()
 * @see safeget()
 * @param String parameter name
 * @param String (optional) default value (defaults to "")
 * @return String - value in $_POST[$param] or $default
 * same as safeget, but for $_POST
 */
function safepost ($param, $default="") {
    $retval=$default;
    if (isset($_POST[$param])) $retval=$_POST[$param];
    return $retval;
}

/**
 * safeserver
 * @see safeget()
 * @param String $param 
 * @param String $default (optional)
 * @return String value from $_SERVER[$param] or $default
 */
function safeserver ($param, $default="") {
    $retval=$default;
    if (isset($_SERVER[$param])) $retval=$_SERVER[$param];
    return $retval;
}

/**
 * safecookie
 * @see safeget()
 * @param String $param 
 * @param String $default (optional)
 * @return String value from $_COOKIE[$param] or $default
 */
function safecookie ($param, $default="") {
    $retval=$default;
    if (isset($_COOKIE[$param])) $retval=$_COOKIE[$param];
    return $retval;
}


//
// get_domain_properties
// Action: Get all the properties of a domain.
// Call: get_domain_properties (string domain)
//
function get_domain_properties ($domain) {
    global $CONF;
    global $table_alias, $table_mailbox, $table_domain;
    $list = array ();

   $result = db_query ("SELECT COUNT(*) FROM $table_alias WHERE domain='$domain'");

   $row = db_row ($result['result']);
   $list['alias_count'] = $row[0];

    $result = db_query ("SELECT COUNT(*) FROM $table_mailbox WHERE domain='$domain'");
    $row = db_row ($result['result']);
    $list['mailbox_count'] = $row[0];

   $result = db_query ("SELECT SUM(quota) FROM $table_mailbox WHERE domain='$domain'");
   $row = db_row ($result['result']);
   $list['quota_sum'] = $row[0];
   $list['alias_count'] = $list['alias_count'] - $list['mailbox_count'];

    $query="SELECT * FROM $table_domain WHERE domain='$domain'";
    if ('pgsql'==$CONF['database_type']) {
        $query=" SELECT *, EXTRACT(epoch FROM created) AS uts_created, EXTRACT(epoch FROM modified) AS uts_modified FROM $table_domain WHERE domain='$domain' ";
    }
    $result = db_query ($query);
    $row = db_array ($result['result']);
    $list['description'] = $row['description'];
    $list['aliases'] = $row['aliases'];
    $list['mailboxes'] = $row['mailboxes'];
    $list['maxquota'] = $row['maxquota'];
    $list['quota'] = $row['quota'];
    $list['transport'] = $row['transport'];
    $list['backupmx'] = $row['backupmx'];
    $list['created'] = $row['created'];
    $list['modified'] = $row['modified'];
    $list['active'] = $row['active'];

    if ($CONF['database_type'] == "pgsql") {
        $list['active']=('t'==$row['active']) ? 1 : 0;
        $list['backupmx']=('t'==$row['backupmx']) ? 1 : 0;
        $list['created']= gmstrftime('%c %Z',$row['uts_created']);
        $list['modified']= gmstrftime('%c %Z',$row['uts_modified']);
    } else {
        $list['active'] = $row['active'];
        $list['backupmx'] = $row['backupmx'];
    }

    return $list;
}


/**
 * create_page_browser
 * Action: Get page browser for a long list of mailboxes, aliases etc.
 * Call: $pagebrowser = create_page_browser('table.field', 'query', 50)   # replaces $param = $_GET['param']
 *
 *  @param String idxfield - database field name to use as title
 *  @param String query - core part of the query (starting at "FROM")
 *  @return String 
 */
function create_page_browser($idxfield, $querypart) {
    global $CONF;
    $page_size = (int) $CONF['page_size'];
    $label_len = 2;
    $pagebrowser = array();

    if ($page_size < 2) { # will break the page browser
        die('$CONF[\'page_size\'] must be 2 or more!');
    }

    # get number of rows
    $query = "SELECT count(*) as counter $querypart";
    $result = db_query ($query);
    if ($result['rows'] > 0) {
        $row = db_array ($result['result']);
        $count_results = $row['counter'] -1; # we start counting at 0, not 1
    }
#    echo "<p>rows: " . ($count_results +1) . " --- $query";

    if ($count_results < $page_size) {
        return array(); # only one page - no pagebrowser required
    }

    # init row counter
    $initcount = "SET @row=-1";
    if ('pgsql'==$CONF['database_type']) {
        $initcount = "CREATE TEMPORARY SEQUENCE rowcount MINVALUE 0";
    }
    $result = db_query($initcount);

    # get labels for relevant rows (first and last of each page)
    $page_size_zerobase = $page_size - 1;
    $query = "
        SELECT * FROM (
            SELECT $idxfield AS label, @row := @row + 1 AS row $querypart 
        ) idx WHERE MOD(idx.row, $page_size) IN (0,$page_size_zerobase) OR idx.row = $count_results
    "; 
    if ('pgsql'==$CONF['database_type']) {
        $query = "
            SELECT * FROM (
                SELECT $idxfield AS label, nextval('rowcount') AS row $querypart 
            ) idx WHERE MOD(idx.row, $page_size) IN (0,$page_size_zerobase) OR idx.row = $count_results
        "; 
    }
#   echo "<p>$query";

# TODO: $query is MySQL-specific

# PostgreSQL: 
# http://www.postgresql.org/docs/8.1/static/sql-createsequence.html
# http://www.postgresonline.com/journal/archives/79-Simulating-Row-Number-in-PostgreSQL-Pre-8.4.html
# http://www.pg-forum.de/sql/1518-nummerierung-der-abfrageergebnisse.html
# CREATE TEMPORARY SEQUENCE foo MINVALUE 0 MAXVALUE $page_size_zerobase CYCLE
# afterwards: DROP SEQUENCE foo

    $result = db_query ($query);
    if ($result['rows'] > 0) {
        while ($row = db_array ($result['result'])) {
            if ($row2 = db_array ($result['result'])) {
                $label = substr($row['label'],0,$label_len) . '-' . substr($row2['label'],0,$label_len);
                $pagebrowser[] = $label;
            } else { # only one row remaining
                $label = substr($row['label'],0,$label_len);
                $pagebrowser[] = $label;
            }
        }
    }

    if ('pgsql'==$CONF['database_type']) {
        db_query ("DROP SEQUENCE rowcount");
    }

    return $pagebrowser;
}

//
// get_mailbox_properties
// Action: Get all the properties of a mailbox.
// Call: get_mailbox_properties (string mailbox)
//
function get_mailbox_properties ($username) {
    global $CONF;
    global $table_mailbox;
    $query="SELECT * FROM $table_mailbox WHERE username='$username'";
    if ('pgsql'==$CONF['database_type']) {
        $query="
            SELECT
            *,
            EXTRACT(epoch FROM created) AS uts_created,
            EXTRACT(epoch FROM modified) AS uts_modified
            FROM $table_mailbox
            WHERE username='$username'
            ";
    }
    $result = db_query ($query);
    $row = db_array ($result['result']);
    $list['name'] = $row['name'];
    $list['maildir'] = $row['maildir'];
    $list['quota'] = $row['quota'];
    $list['domain'] = $row['domain'];
    $list['created'] = $row['created'];
    $list['modified'] = $row['modified'];
    $list['active'] = $row['active'];

    if ($CONF['database_type'] == "pgsql") {
        $list['active']=('t'==$row['active']) ? 1 : 0;
        $list['created']= gmstrftime('%c %Z',$row['uts_created']);
        $list['modified']= gmstrftime('%c %Z',$row['uts_modified']);
    } else {
        $list['active'] = $row['active'];
    }

    return $list;
}



//
// check_alias
// Action: Checks if the domain is still able to create aliases.
// Call: check_alias (string domain)
//
function check_alias ($domain) {
    $limit = get_domain_properties ($domain);
    if ($limit['aliases'] == 0) {
        # 0 = unlimited, -1 = disabled
        return true;
    }
    if ($limit['aliases'] < 0) {
        return false;
    }
    if ($limit['alias_count'] >= $limit['aliases']) {
        return false;
    } else {
        return true;
    }
}



//
// check_mailbox
// Action: Checks if the domain is still able to create mailboxes.
// Call: check_mailbox (string domain)
//
function check_mailbox ($domain) {
    $limit = get_domain_properties ($domain);
    /* -1 = disable, 0 = unlimited */
    if ($limit['mailboxes'] == 0) {
        return true;
    }
    if ($limit['mailboxes'] < 0) {
        return false;
    }
    if ($limit['mailbox_count'] >= $limit['mailboxes']) {
        return false;
    } else {
        return true;
    }
}



//
// check_quota
// Action: Checks if the user is creating a mailbox with the correct quota
// Call: check_quota (string domain)
//
function check_quota ($quota, $domain, $username="") {
    global $CONF;
    $rval = false;
    $limit = get_domain_properties ($domain);

    if ($limit['maxquota'] == 0) {
        $rval = true; # maxquota unlimited -> OK, but domain level quota could still be hit
    }

    if (($limit['maxquota'] < 0) and ($quota < 0)) {
        return true; # maxquota and $quota are both disabled -> OK, no need for more checks
    }

    if (($limit['maxquota'] > 0) and ($quota == 0)) {
        return false; # mailbox with unlimited quota on a domain with maxquota restriction -> not allowed, no more checks needed
    }

    if ($limit['maxquota'] != 0 && $quota > $limit['maxquota']) {
        return false; # mailbox bigger than maxquota restriction (and maxquota != unlimited) -> not allowed, no more checks needed
    } else {
        $rval = true; # mailbox size looks OK, but domain level quota could still be hit
    }

    # TODO: detailed error message ("domain quota exceeded", "mailbox quota too big" etc.) via flash_error? Or "available quota: xxx MB"?
    if (!$rval || $CONF['domain_quota'] != 'YES') {
        return $rval;
    } elseif ($limit['quota'] <= 0) { # TODO: CHECK - 0 (unlimited) is fine, not sure about <= -1 (disabled)...
        $rval = true;
    } else {
        $table_mailbox = table_by_key('mailbox');
        $query = "SELECT SUM(quota) FROM $table_mailbox WHERE domain = '" . escape_string($domain) . "'";
        if ($username != "") {
            $query .= " AND username != '" . escape_string($username) . "'";
        }
        $result = db_query ($query);
        $row = db_row ($result['result']);
        $cur_quota_total = divide_quota($row[0]); # convert to MB
        if ( ($quota + $cur_quota_total) > $limit['quota'] ) {
            $rval = false;
        } else {
            $rval = true;
        }
    }

    return $rval;
}



//
// multiply_quota
// Action: Recalculates the quota from bytes to MBs (multiply, *)
// Call: multiply_quota (string $quota)
//
function multiply_quota ($quota) {
    global $CONF;
    if ($quota == -1) return $quota;
    $value = $quota * $CONF['quota_multiplier'];
    return $value;
}



//
// divide_quota
// Action: Recalculates the quota from MBs to bytes (divide, /)
// Call: divide_quota (string $quota)
//
function divide_quota ($quota) {
    global $CONF;
    if ($quota == -1) return $quota;
    $value = round($quota / $CONF['quota_multiplier'],2);
    return $value;
}



//
// check_owner
// Action: Checks if the admin is the owner of the domain (or global-admin)
// Call: check_owner (string admin, string domain)
//
function check_owner ($username, $domain) {
    global $table_domain_admins;
    $result = db_query ("SELECT 1 FROM $table_domain_admins WHERE username='$username' AND (domain='$domain' OR domain='ALL') AND active='1'");
    if ($result['rows'] != 1) {
        if ($result['rows'] > 1) { # "ALL" + specific domain permissions. 2.3 doesn't create such entries, but they are available as leftover from older versions
            flash_error("Permission check returned more than one result. Please go to 'edit admin' for your username and press the save "
             . "button once to fix the database. If this doesn't help, open a bugreport.");
        } 
        return false;
    } else {
        return true;
    }
}



//
// check_alias_owner
// Action: Checks if the admin is the owner of the alias.
// Call: check_alias_owner (string admin, string alias)
//
function check_alias_owner ($username, $alias) {
    global $CONF;
    if (authentication_has_role('global-admin')) return true;
    $tmp = preg_split('/\@/', $alias);
    if (($CONF['special_alias_control'] == 'NO') && array_key_exists($tmp[0], $CONF['default_aliases'])) {
        return false;
    } else {
        return true;
    }
}


/**
 * List domains for an admin user. 
 * @param String $username
 * @return array of domain names.
 */
function list_domains_for_admin ($username) {
    global $CONF;
    global $table_domain, $table_domain_admins;
    $list = array ();
    // does $username need escaping here?
    $active_sql = db_get_boolean(True);
    $backupmx_sql = db_get_boolean(False);
    $query = "SELECT $table_domain.domain, $table_domain_admins.username FROM $table_domain 
        LEFT JOIN $table_domain_admins ON $table_domain.domain=$table_domain_admins.domain 
        WHERE $table_domain_admins.username='$username' 
        AND $table_domain.active='$active_sql'
        AND $table_domain.backupmx='$backupmx_sql'
        ORDER BY $table_domain_admins.domain";

    $result = db_query ($query);
    if ($result['rows'] > 0) {
        $i = 0;
        while ($row = db_array ($result['result'])) {
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
function list_domains () {
    global $table_domain;
    $list = array();

    $result = db_query ("SELECT domain FROM $table_domain WHERE domain!='ALL' ORDER BY domain");
    if ($result['rows'] > 0) {
        $i = 0;
        while ($row = db_array ($result['result'])) {
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
function admin_exist ($username) {
    $result = db_query ("SELECT 1 FROM " . table_by_key ('admin') . " WHERE username='$username'");
    if ($result['rows'] != 1) {
        return false;
    } else {
        return true;
    }
}



//
// domain_exist
// Action: Checks if the domain already exists.
// Call: domain_exist (string domain)
//
function domain_exist ($domain) {
    global $table_domain;

    $result = db_query("SELECT 1 FROM $table_domain WHERE domain='$domain'");
    if ($result['rows'] != 1) {
        return false;
    } else {
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
function list_admins () {
    global $table_admin;
    $list = "";

    $result = db_query ("SELECT username FROM $table_admin ORDER BY username");
    if ($result['rows'] > 0) {
        $i = 0;
        while ($row = db_array ($result['result'])) {
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
//
function get_admin_properties ($username) {
    global $CONF;
    global $table_admin, $table_domain_admins;
    $list = array ();

    $result = db_query ("SELECT * FROM $table_domain_admins WHERE username='$username' AND domain='ALL'");
    if ($result['rows'] == 1) {
        $list['domain_count'] = 'ALL';
    } else {
        $result = db_query ("SELECT COUNT(*) FROM $table_domain_admins WHERE username='$username'");
        $row = db_row ($result['result']);
        $list['domain_count'] = $row[0];
    }

    $query = "SELECT * FROM $table_admin WHERE username='$username'";
    if ('pgsql'==$CONF['database_type']) {
        $query="
            SELECT
            *,
            EXTRACT(epoch FROM created) AS uts_created,
            EXTRACT (epoch FROM modified) AS uts_modified
            FROM $table_admin
            WHERE username='$username'
            ";
    }

    $result = db_query ($query);
    $row = db_array ($result['result']);
    $list['created'] = $row['created'];
    $list['modified'] = $row['modified'];
    $list['active'] = $row['active'];
    if ('pgsql'==$CONF['database_type']) {
        $list['active'] = ('t'==$row['active']) ? 1 : 0;
        $list['created']= gmstrftime('%c %Z',$row['uts_created']);
        $list['modified']= gmstrftime('%c %Z',$row['uts_modified']);
    }
    return $list;
}



//
// encode_header
// Action: Encode a string according to RFC 1522 for use in headers if it contains 8-bit characters.
// Call: encode_header (string header, string charset)
//
function encode_header ($string, $default_charset = "utf-8") {
    if (strtolower ($default_charset) == 'iso-8859-1') {
        $string = str_replace ("\240",' ',$string);
    }

    $j = strlen ($string);
    $max_l = 75 - strlen ($default_charset) - 7;
    $aRet = array ();
    $ret = '';
    $iEncStart = $enc_init = false;
    $cur_l = $iOffset = 0;

    for ($i = 0; $i < $j; ++$i) {
        switch ($string{$i}) {
            case '=':
            case '<':
            case '>':
            case ',':
            case '?':
            case '_':
                if ($iEncStart === false) {
                    $iEncStart = $i;
                }
                $cur_l+=3;
                if ($cur_l > ($max_l-2)) {
                    $aRet[] = substr ($string,$iOffset,$iEncStart-$iOffset);
                    $aRet[] = "=?$default_charset?Q?$ret?=";
                    $iOffset = $i;
                    $cur_l = 0;
                    $ret = '';
                    $iEncStart = false;
                } else {
                    $ret .= sprintf ("=%02X",ord($string{$i}));
                }
                break;
            case '(':
            case ')':
                if ($iEncStart !== false) {
                    $aRet[] = substr ($string,$iOffset,$iEncStart-$iOffset);
                    $aRet[] = "=?$default_charset?Q?$ret?=";
                    $iOffset = $i;
                    $cur_l = 0;
                    $ret = '';
                    $iEncStart = false;
                }
                break;
            case ' ':
                if ($iEncStart !== false) {
                    $cur_l++;
                    if ($cur_l > $max_l) {
                        $aRet[] = substr ($string,$iOffset,$iEncStart-$iOffset);
                        $aRet[] = "=?$default_charset?Q?$ret?=";
                        $iOffset = $i;
                        $cur_l = 0;
                        $ret = '';
                        $iEncStart = false;
                    } else {
                        $ret .= '_';
                    }
                }
                break;
            default:
                $k = ord ($string{$i});
                if ($k > 126) {
                    if ($iEncStart === false) {
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
                    if ($cur_l > ($max_l-2)) {
                        $aRet[] = substr ($string,$iOffset,$iEncStart-$iOffset);
                        $aRet[] = "=?$default_charset?Q?$ret?= ";
                        $cur_l = 3;
                        $ret = '';
                        $iOffset = $i;
                        $iEncStart = $i;
                    }
                    $enc_init = true;
                    $ret .= sprintf ("=%02X", $k);
                } else {
                    if ($iEncStart !== false) {
                        $cur_l++;
                        if ($cur_l > $max_l) {
                            $aRet[] = substr ($string,$iOffset,$iEncStart-$iOffset);
                            $aRet[] = "=?$default_charset?Q?$ret?=";
                            $iEncStart = false;
                            $iOffset = $i;
                            $cur_l = 0;
                            $ret = '';
                        } else {
                            $ret .= $string{$i};
                        }
                    }
                }
                break;
            # end switch
        }
    }
    if ($enc_init) {
        if ($iEncStart !== false) {
            $aRet[] = substr ($string,$iOffset,$iEncStart-$iOffset);
            $aRet[] = "=?$default_charset?Q?$ret?=";
        } else {
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
function generate_password () {
    global $CONF;

    //check that password length is sensible
    $length = (int) $CONF['min_password_length'];
    if ($length < 5 || $length > 32) {
  	    $length = 8;
    }

    // define possible characters
    $possible = "2345678923456789abcdefghijkmnpqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ"; # skip 0 and 1 to avoid confusion with O and l

    // add random characters to $password until $length is reached
    $password = "";
    while (strlen($password) < $length) {
        // pick a random character from the possible ones
        $char = substr($possible, mt_rand(0, strlen($possible)-1), 1);

        // we don't want this character if it's already in the password
        if (!strstr($password, $char)) {
            $password .= $char;
        }
    }

    return $password;
}



/**
 * Encrypt a password, using the apparopriate hashing mechanism as defined in 
 * config.inc.php ($CONF['encrypt']). 
 * When wanting to compare one pw to another, it's necessary to provide the salt used - hence
 * the second parameter ($pw_db), which is the existing hash from the DB.
 *
 * @param string $pw
 * @param string $encrypted password
 * @return string encrypted password.
 */
function pacrypt ($pw, $pw_db="") {
    global $CONF;
    $pw = stripslashes($pw);
    $password = "";
    $salt = "";

    if ($CONF['encrypt'] == 'md5crypt') {
        $split_salt = preg_split ('/\$/', $pw_db);
        if (isset ($split_salt[2])) {
            $salt = $split_salt[2];
        }
        $password = md5crypt ($pw, $salt);
    }

    elseif ($CONF['encrypt'] == 'md5') {
        $password = md5($pw);
    }

    elseif ($CONF['encrypt'] == 'system') {
        if (preg_match("/\\$1\\$/", $pw_db)) {
            $split_salt = preg_split ('/\$/', $pw_db);
            $salt = "\$1\$${split_salt[2]}\$";
        } else {
            if (strlen($pw_db) == 0) {
                $salt = substr (md5 (mt_rand ()), 0, 2);
            } else {
                $salt = substr ($pw_db, 0, 2);
            }
        }
        $password = crypt ($pw, $salt);
    }

    elseif ($CONF['encrypt'] == 'cleartext') {
        $password = $pw;
    }

    // See https://sourceforge.net/tracker/?func=detail&atid=937966&aid=1793352&group_id=191583
    // this is apparently useful for pam_mysql etc.
    elseif ($CONF['encrypt'] == 'mysql_encrypt') {
        if ($pw_db!="") {
            $salt=substr($pw_db,0,2);
            $res=db_query("SELECT ENCRYPT('".$pw."','".$salt."');");
        } else {
            $res=db_query("SELECT ENCRYPT('".$pw."');");
        }
        $l = db_row($res["result"]);
        $password = $l[0];
    }
    
    elseif ($CONF['encrypt'] == 'authlib') {
        $flavor = $CONF['authlib_default_flavor'];
        $salt = substr(create_salt(), 0, 2); # courier-authlib supports only two-character salts
        if(preg_match('/^{.*}/', $pw_db)) {
            // we have a flavor in the db -> use it instead of default flavor
            $result = preg_split('/[{}]/', $pw_db, 3); # split at { and/or }
            $flavor = $result[1];  
            $salt = substr($result[2], 0, 2);
        }
 
        if(stripos($flavor, 'md5raw') === 0) {
            $password = '{' . $flavor . '}' . md5($pw);
        } elseif(stripos($flavor, 'md5') === 0) {
            $password = '{' . $flavor . '}' . base64_encode(md5($pw, TRUE));
        } elseif(stripos($flavor, 'crypt') === 0) {
            $password = '{' . $flavor . '}' . crypt($pw, $salt);
		} elseif(stripos($flavor, 'SHA') === 0) {
			$password = '{' . $flavor . '}' . base64_encode(sha1($pw, TRUE));
        } else {
            die("authlib_default_flavor '" . $flavor . "' unknown. Valid flavors are 'md5raw', 'md5', 'SHA' and 'crypt'");
        }
    }
    
    elseif (preg_match("/^dovecot:/", $CONF['encrypt'])) {
        $split_method = preg_split ('/:/', $CONF['encrypt']);
        $method       = strtoupper($split_method[1]);
        if (! preg_match("/^[A-Z0-9-]+$/", $method)) { die("invalid dovecot encryption method"); }  # TODO: check against a fixed list?
        if (strtolower($method) == 'md5-crypt') die("\$CONF['encrypt'] = 'dovecot:md5-crypt' will not work because dovecotpw generates a random salt each time. Please use \$CONF['encrypt'] = 'md5crypt' instead."); 

        $dovecotpw = "dovecotpw";
        if (!empty($CONF['dovecotpw'])) $dovecotpw = $CONF['dovecotpw'];

        # Use proc_open call to avoid safe_mode problems and to prevent showing plain password in process table
        $spec = array(
			0 => array("pipe", "r"), // stdin
			1 => array("pipe", "w"), // stdout
			2 => array("pipe", "w"), // stderr
		);

		$pipe = proc_open("$dovecotpw '-s' $method", $spec, $pipes);

        if (!$pipe) {
            die("can't proc_open $dovecotpw");
        } else {
            // use dovecot's stdin, it uses getpass() twice
			// Write pass in pipe stdin
			fwrite($pipes[0], $pw . "\n", 1+strlen($pw)); usleep(1000);
			fwrite($pipes[0], $pw . "\n", 1+strlen($pw));
			fclose($pipes[0]);

			// Read hash from pipe stdout
			$password = fread($pipes[1], "200");

            if ( !preg_match('/^\{' . $method . '\}/', $password)) {
                $stderr_output = stream_get_contents($pipes[2]);
                error_log('dovecotpw password encryption failed.');
                error_log('STDERR output: ' . $stderr_output);
                die("can't encrypt password with dovecotpw, see error log for details"); 
            }

			fclose($pipes[1]);
			fclose($pipes[2]);
			proc_close($pipe);

            $password = trim(str_replace('{' . $method . '}', '', $password));
        }
    }

    else {
        die ('unknown/invalid $CONF["encrypt"] setting: ' . $CONF['encrypt']);
    }

    $password = escape_string ($password);
    return $password;
}

//
// md5crypt
// Action: Creates MD5 encrypted password
// Call: md5crypt (string cleartextpassword)
//

function md5crypt ($pw, $salt="", $magic="") {
    $MAGIC = "$1$";

    if ($magic == "") $magic = $MAGIC;
    if ($salt == "") $salt = create_salt ();
    $slist = explode ("$", $salt);
    if ($slist[0] == "1") $salt = $slist[1];

    $salt = substr ($salt, 0, 8);
    $ctx = $pw . $magic . $salt;
    $final = hex2bin (md5 ($pw . $salt . $pw));

    for ($i=strlen ($pw); $i>0; $i-=16) {
        if ($i > 16) {
            $ctx .= substr ($final,0,16);
        } else {
            $ctx .= substr ($final,0,$i);
        }
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
        if ($i & 1) {
            $ctx1 .= $pw;
        } else {
            $ctx1 .= substr ($final,0,16);
        }
        if ($i % 3) $ctx1 .= $salt;
        if ($i % 7) $ctx1 .= $pw;
        if ($i & 1) {
            $ctx1 .= substr ($final,0,16);
        } else {
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

function create_salt () {
    srand ((double) microtime ()*1000000);
    $salt = substr (md5 (rand (0,9999999)), 0, 8);
    return $salt;
}

function hex2bin ($str) {
    $len = strlen ($str);
    $nstr = "";
    for ($i=0;$i<$len;$i+=2) {
        $num = sscanf (substr ($str,$i,2), "%x");
        $nstr.=chr ($num[0]);
    }
    return $nstr;
}

function to64 ($v, $n) {
    $ITOA64 = "./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
    $ret = "";
    while (($n - 1) >= 0) {
        $n--;
        $ret .= $ITOA64[$v & 0x3f];
        $v = $v >> 6;
    }
    return $ret;
}



/**
 * smtp_mail
 * Action: Send email
 * Call: smtp_mail (string to, string from, string subject, string body]) - or -
 * Call: smtp_mail (string to, string from, string data) - DEPRECATED
 * @param String - To:
 * @param String - From:
 * @param String - Subject: (if called with 4 parameters) or full mail body (if called with 3 parameters)
 * @param String (optional, but recommended) - mail body
 * @return bool - true on success, otherwise false
 * TODO: Replace this with something decent like PEAR::Mail or Zend_Mail.
 */
function smtp_mail ($to, $from, $data, $body = "") {
    global $CONF;
    $smtpd_server = $CONF['smtp_server'];
    $smtpd_port = $CONF['smtp_port'];
    //$smtp_server = $_SERVER["SERVER_NAME"];
    $smtp_server = php_uname("n");
    $errno = "0";
    $errstr = "0";
    $timeout = "30";

    if ($body != "") {
        $maildata = 
            "To: " . $to . "\n"
            . "From: " . $from . "\n"
            . "Subject: " . encode_header ($data) . "\n"
            . "MIME-Version: 1.0\n"
            . "Content-Type: text/plain; charset=utf-8\n"
            . "Content-Transfer-Encoding: 8bit\n"
            . "\n"
            . $body
        ;
    } else {
        $maildata = $data;
    }

    $fh = @fsockopen ($smtpd_server, $smtpd_port, $errno, $errstr, $timeout);

    if (!$fh) {
        return false;
    } else {
        $res = smtp_get_response($fh);
        fputs ($fh, "EHLO $smtp_server\r\n");
        $res = smtp_get_response($fh);
        fputs ($fh, "MAIL FROM:<$from>\r\n");
        $res = smtp_get_response($fh);
        fputs ($fh, "RCPT TO:<$to>\r\n");
        $res = smtp_get_response($fh);
        fputs ($fh, "DATA\r\n");
        $res = smtp_get_response($fh);
        fputs ($fh, "$maildata\r\n.\r\n");
        $res = smtp_get_response($fh);
        fputs ($fh, "QUIT\r\n");
        $res = smtp_get_response($fh);
        fclose ($fh);
    }
    return true;
}

/**
 * smtp_get_admin_email
 * Action: Get configured email address or current user if nothing configured
 * Call: smtp_get_admin_email
 * @return String - username/mail address
 */
function smtp_get_admin_email() {
	global $CONF;
	if(isset($CONF['admin_email']) && !empty($CONF['admin_email']))
		return $CONF['admin_email'];
	else
		return authentication_get_username();
}


//
// smtp_get_response
// Action: Get response from mail server
// Call: smtp_get_response (string FileHandle)
//
function smtp_get_response ($fh) {
    $res ='';
    do {
    $line = fgets($fh, 256);
    $res .= $line;
}
while (preg_match("/^\d\d\d\-/", $line));
return $res;
}



$DEBUG_TEXT = "\n
    <p />\n
    Please check the documentation and website for more information.\n
    <p />\n
    <a href=\"http://postfixadmin.sf.net/\">Postfix Admin</a><br />\n
    <a href='https://sourceforge.net/forum/forum.php?forum_id=676076'>Forums</a>
    ";


/**
 * db_connect
 * Action: Makes a connection to the database if it doesn't exist
 * Call: db_connect ()
 * Optional parameter: $ignore_errors = TRUE, used by setup.php
 *
 * Return value:
 * a) without $ignore_errors or $ignore_errors == 0
 *    - $link - the database connection -OR-
 *    - call die() in case of connection problems
 * b) with $ignore_errors == TRUE
 *    array($link, $error_text);
 */
function db_connect ($ignore_errors = 0) {
    global $CONF;
    global $DEBUG_TEXT;
    if ($ignore_errors != 0) $DEBUG_TEXT = '';
    $error_text = '';
    $link = 0;

    if ($CONF['database_type'] == "mysql") {
        if (function_exists ("mysql_connect")) {
            $link = @mysql_connect ($CONF['database_host'], $CONF['database_user'], $CONF['database_password']) or $error_text .= ("<p />DEBUG INFORMATION:<br />Connect: " .  mysql_error () . "$DEBUG_TEXT");
            if ($link) {
                @mysql_query("SET CHARACTER SET utf8",$link);
                @mysql_query("SET COLLATION_CONNECTION='utf8_general_ci'",$link);
                $succes = @mysql_select_db ($CONF['database_name'], $link) or $error_text .= ("<p />DEBUG INFORMATION:<br />MySQL Select Database: " .  mysql_error () . "$DEBUG_TEXT");
            }
        } else {
            $error_text .= "<p />DEBUG INFORMATION:<br />MySQL 3.x / 4.0 functions not available! (php5-mysql installed?)<br />database_type = 'mysql' in config.inc.php, are you using a different database? $DEBUG_TEXT";
        }
    } elseif ($CONF['database_type'] == "mysqli") {
        if (function_exists ("mysqli_connect")) {
            $link = @mysqli_connect ($CONF['database_host'], $CONF['database_user'], $CONF['database_password']) or $error_text .= ("<p />DEBUG INFORMATION:<br />Connect: " .  mysqli_connect_error () . "$DEBUG_TEXT");
            if ($link) {
                @mysqli_query($link,"SET CHARACTER SET utf8");
                @mysqli_query($link,"SET COLLATION_CONNECTION='utf8_general_ci'");
                $success = @mysqli_select_db ($link, $CONF['database_name']) or $error_text .= ("<p />DEBUG INFORMATION:<br />MySQLi Select Database: " .  mysqli_error ($link) . "$DEBUG_TEXT");
            }
        } else {
            $error_text .= "<p />DEBUG INFORMATION:<br />MySQL 4.1 functions not available! (php5-mysqli installed?)<br />database_type = 'mysqli' in config.inc.php, are you using a different database? $DEBUG_TEXT";
        }
    } elseif ($CONF['database_type'] == "pgsql") {
        if (function_exists ("pg_pconnect")) {
			if(!isset($CONF['database_port'])) {
				$CONF['database_port'] = '5432';
			}
            $connect_string = "host=" . $CONF['database_host'] . " port=" . $CONF['database_port'] . " dbname=" . $CONF['database_name'] . " user=" . $CONF['database_user'] . " password=" . $CONF['database_password'];
            $link = @pg_pconnect ($connect_string) or $error_text .= ("<p />DEBUG INFORMATION:<br />Connect: failed to connect to database. $DEBUG_TEXT");
            if ($link) pg_set_client_encoding($link, 'UNICODE');
        } else {
            $error_text .= "<p />DEBUG INFORMATION:<br />PostgreSQL functions not available! (php5-pgsql installed?)<br />database_type = 'pgsql' in config.inc.php, are you using a different database? $DEBUG_TEXT";
        }
    } else {
        $error_text = "<p />DEBUG INFORMATION:<br />Invalid \$CONF['database_type']! Please fix your config.inc.php! $DEBUG_TEXT";
    }

    if ($ignore_errors) {
        return array($link, $error_text);
    } elseif ($error_text != "") {
        print $error_text;
        die();
    } elseif ($link) {
        return $link;
    } else {
        print "DEBUG INFORMATION:<br />\n";
        print "Connect: Unable to connect to database<br />\n";
        print "<br />\n";
        print "Make sure that you have set the correct database type in the config.inc.php file<br />\n";
        print $DEBUG_TEXT;
        die();
    }
}

/**
 * Returns the appropriate boolean value for the database.
 * Currently only PostgreSQL and MySQL are supported.
 * @param boolean $bool (REQUIRED)
 * @return String or int as appropriate.
 */
function db_get_boolean($bool) {
    if(!is_bool($bool)) {
        die("Invalid usage of 'db_get_boolean($bool)'");
    }

    global $CONF;
    if($CONF['database_type']=='pgsql') {
        // return either true or false (unquoted strings)
        if($bool) {
            return 't';
        }  
        return 'f';
    } elseif($CONF['database_type'] == 'mysql' || $CONF['database_type'] == 'mysqli') {
        if($bool) {
            return 1;  
        } 
        return 0;
    }
}

//
// db_query
// Action: Sends a query to the database and returns query result and number of rows
// Call: db_query (string query)
// Optional parameter: $ignore_errors = TRUE, used by upgrade.php
//
function db_query ($query, $ignore_errors = 0) {
    global $CONF;
    global $DEBUG_TEXT;
    $result = "";
    $number_rows = "";
    static $link;
    $error_text = "";
    if ($ignore_errors) $DEBUG_TEXT = "";

    if (!is_resource($link)) $link = db_connect ();

    if ($CONF['database_type'] == "mysql") $result = @mysql_query ($query, $link) 
        or $error_text = "<p />DEBUG INFORMATION:<br />Invalid query: " . mysql_error($link) . "$DEBUG_TEXT";
    if ($CONF['database_type'] == "mysqli") $result = @mysqli_query ($link, $query) 
        or $error_text = "<p />DEBUG INFORMATION:<br />Invalid query: " . mysqli_error($link) . "$DEBUG_TEXT";
    if ($CONF['database_type'] == "pgsql") {
        $result = @pg_query ($link, $query) 
            or $error_text = "<p />DEBUG INFORMATION:<br />Invalid query: " . pg_last_error() . "$DEBUG_TEXT";
    }
    if ($error_text != "" && $ignore_errors == 0) die($error_text);

    if ($error_text == "") {
        if (preg_match("/^SELECT/i", trim($query))) {
            // if $query was a SELECT statement check the number of rows with [database_type]_num_rows ().
            if ($CONF['database_type'] == "mysql") $number_rows = mysql_num_rows ($result);
            if ($CONF['database_type'] == "mysqli") $number_rows = mysqli_num_rows ($result);
            if ($CONF['database_type'] == "pgsql") $number_rows = pg_num_rows ($result);
        } else {
            // if $query was something else, UPDATE, DELETE or INSERT check the number of rows with
            // [database_type]_affected_rows ().
            if ($CONF['database_type'] == "mysql") $number_rows = mysql_affected_rows ($link);
            if ($CONF['database_type'] == "mysqli") $number_rows = mysqli_affected_rows ($link);
            if ($CONF['database_type'] == "pgsql") $number_rows = pg_affected_rows ($result);
        }
    }

    $return = array (
        "result" => $result,
        "rows" => $number_rows,
        "error" => $error_text
    );
    return $return;
}



// db_row
// Action: Returns a row from a table
// Call: db_row (int result)
//
function db_row ($result) {
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
function db_array ($result) {
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
function db_assoc ($result) {
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
function db_delete ($table,$where,$delete) {
    $table = table_by_key($table);
    $query = "DELETE FROM $table WHERE " . escape_string($where) . "='" . escape_string($delete) . "'";
    $result = db_query ($query);

    if ($result['rows'] >= 1) {
        return $result['rows'];
    } else {
        return 0;
    }
}


/**
 * db_insert
 * Action: Inserts a row from a specified table
 * Call: db_insert (string table, array values [, array timestamp])
 * @param String - table name
 * @param array  - key/value map of data to insert into the table.
 * @param array (optional) - array of fields to set to now() - default: array('created', 'modified')
 * @return int - number of inserted rows
 */
function db_insert ($table, $values, $timestamp = array('created', 'modified') ) {
    $table = table_by_key ($table);

    foreach(array_keys($values) as $key) {
        $values[$key] = "'" . escape_string($values[$key]) . "'";
    }

    foreach($timestamp as $key) {
        $values[$key] = "now()";
    }

    $sql_values = "(" . implode(",",escape_string(array_keys($values))).") VALUES (".implode(",",$values).")";

    $result = db_query ("INSERT INTO $table $sql_values");
    return $result['rows'];
}


/**
 * db_update
 * Action: Updates a specified table
 * Call: db_update (string table, string where_col, string where_value, array values [, array timestamp])
 * @param String - table name
 * @param String - column of WHERE condition
 * @param String - value of WHERE condition
 * @param array - key/value map of data to insert into the table.
 * @param array (optional) - array of fields to set to now() - default: array('modified')
 * @return int - number of updated rows
 */
function db_update ($table, $where_col, $where_value, $values, $timestamp = array('modified') ) {
    $where = $where_col . " = '" . escape_string($where_value) . "'";
    return db_update_q ($table, $where, $values, $timestamp );
}

/**
 * db_update_q
 * Action: Updates a specified table
 * Call: db_update_q (string table, string where, array values [, array timestamp])
 * @param String - table name
 * @param String - WHERE condition (as SQL)
 * @param array - key/value map of data to insert into the table.
 * @param array (optional) - array of fields to set to now() - default: array('modified')
 * @return int - number of updated rows
 */
function db_update_q ($table, $where, $values, $timestamp = array('modified') ) {
    $table = table_by_key ($table);

    foreach(array_keys($values) as $key) {
        $sql_values[$key] = escape_string($key) . "='" . escape_string($values[$key]) . "'";
    }

    foreach($timestamp as $key) {
        $sql_values[$key] = escape_string($key) . "=now()";
    }

    $sql="UPDATE $table SET ".implode(",",$sql_values)." WHERE $where";

    $result = db_query ($sql);
    return $result['rows'];
}

/**
 * db_begin / db_commit / db_rollback
 * Action: BEGIN / COMMIT / ROLLBACK transaction (PostgreSQL only!)
 * Call: db_begin()
 */
function db_begin () {
    global $CONF;
#    if ('pgsql'== Config::read('database_type')) {
    if ('pgsql'== $CONF['database_type']) {
        db_query('BEGIN');
    }
}

function db_commit () {
    global $CONF;
#    if ('pgsql'== Config::read('database_type')) {
    if ('pgsql'== $CONF['database_type']) {
        db_query('COMMIT');
    }
}

function db_rollback () {
    global $CONF;
#    if ('pgsql'== Config::read('database_type')) {
    if ('pgsql'== $CONF['database_type']) {
        db_query('ROLLBACK');
    }
}





/**
 * db_log
 * Action: Logs actions from admin
 * Call: db_log (string domain, string action, string data)
 * Possible actions are:
 * 'create_domain'
 * 'create_alias'
 * 'create_alias_domain'
 * 'create_mailbox'
 * 'delete_domain'
 * 'delete_alias'
 * 'delete_alias_domain'
 * 'delete_mailbox'
 * 'edit_domain'
 * 'edit_alias'
 * 'edit_alias_state'
 * 'edit_alias_domain_state'
 * 'edit_mailbox'
 * 'edit_mailbox_state'
 * 'edit_password'
 */
function db_log ($domain,$action,$data) {
    global $CONF;
    global $table_log;
    $REMOTE_ADDR = getRemoteAddr();

    $username = authentication_get_username();

    $action_list = array(  
        'create_alias', 'edit_alias', 'edit_alias_state', 'delete_alias',
        'create_mailbox', 'edit_mailbox', 'edit_mailbox_state', 'delete_mailbox',
        'create_domain', 'edit_domain', 'delete_domain',
        'create_alias_domain', 'edit_alias_domain_state', 'delete_alias_domain',
        'edit_password',
    );

    if(!in_array($action, $action_list)) {
        die("Invalid log action : $action");   // could do with something better?
    }

    if ($CONF['logging'] == 'YES') {
        $logdata = array(
            'username'  => "$username ($REMOTE_ADDR)",
            'domain'    => $domain,
            'action'    => $action,
            'data'      => $data,
        );
        $result = db_insert('log', $logdata, array('timestamp') );
        if ($result != 1) {
            return false;
        } else {
            return true;
        }
    }
}

/**
 * db_in_clause
 * Action: builds and returns the "field in(x, y)" clause for database queries
 * Call: db_in_clause (string field, array values)
 */
function db_in_clause($field, $values) {
	return " $field IN ('"
	. implode("','",escape_string(array_values($values)))
	. "') ";
}

//
// table_by_key
// Action: Return table name for given key
// Call: table_by_key (string table_key)
//
function table_by_key ($table_key) {
    global $CONF;
    if (empty($CONF['database_tables'][$table_key])) {
        $table = $table_key;
    } else {
        $table = $CONF['database_tables'][$table_key];
    }

    return $CONF['database_prefix'].$table;
}



/*
   Called after a mailbox has been created in the DBMS.
   Returns: boolean.
 */
function mailbox_postcreation($username,$domain,$maildir,$quota) {
    if (empty($username) || empty($domain) || empty($maildir)) {
        trigger_error('In '.__FUNCTION__.': empty username, domain and/or maildir parameter',E_USER_ERROR);
        return FALSE;
    }

    global $CONF;
    $confpar='mailbox_postcreation_script';

    if (!isset($CONF[$confpar]) || empty($CONF[$confpar])) return TRUE;

    $cmdarg1=escapeshellarg($username);
    $cmdarg2=escapeshellarg($domain);
    $cmdarg3=escapeshellarg($maildir);
    if ($quota <= 0) $quota = 0;
    $cmdarg4=escapeshellarg($quota);
    $command=$CONF[$confpar]." $cmdarg1 $cmdarg2 $cmdarg3 $cmdarg4";
    $retval=0;
    $output=array();
    $firstline='';
    $firstline=exec($command,$output,$retval);
    if (0!=$retval) {
        error_log("Running $command yielded return value=$retval, first line of output=$firstline");
        print '<p>WARNING: Problems running mailbox postcreation script!</p>';
        return FALSE;
    }

    return TRUE;
}

/*
   Called after a mailbox has been altered in the DBMS.
   Returns: boolean.
 */
function mailbox_postedit($username,$domain,$maildir,$quota) {
    if (empty($username) || empty($domain) || empty($maildir)) {
        trigger_error('In '.__FUNCTION__.': empty username, domain and/or maildir parameter',E_USER_ERROR);
        return FALSE;
    }

    global $CONF;
    $confpar='mailbox_postedit_script';

    if (!isset($CONF[$confpar]) || empty($CONF[$confpar])) return TRUE;

    $cmdarg1=escapeshellarg($username);
    $cmdarg2=escapeshellarg($domain);
    $cmdarg3=escapeshellarg($maildir);
    if ($quota <= 0) $quota = 0;
    $cmdarg4=escapeshellarg($quota);
    $command=$CONF[$confpar]." $cmdarg1 $cmdarg2 $cmdarg3 $cmdarg4";
    $retval=0;
    $output=array();
    $firstline='';
    $firstline=exec($command,$output,$retval);
    if (0!=$retval) {
        error_log("Running $command yielded return value=$retval, first line of output=$firstline");
        print '<p>WARNING: Problems running mailbox postedit script!</p>';
        return FALSE;
    }

    return TRUE;
}


/*
   Called after a mailbox has been deleted in the DBMS.
   Returns: boolean.
 */
function mailbox_postdeletion($username,$domain) {
    global $CONF;
    $confpar='mailbox_postdeletion_script';

    if (!isset($CONF[$confpar]) || empty($CONF[$confpar])) {
        return true;
    }

    if (empty($username) || empty($domain)) {
        print '<p>Warning: empty username and/or domain parameter.</p>';
        return false;
    }

    $cmdarg1=escapeshellarg($username);
    $cmdarg2=escapeshellarg($domain);
    $command=$CONF[$confpar]." $cmdarg1 $cmdarg2";
    $retval=0;
    $output=array();
    $firstline='';
    $firstline=exec($command,$output,$retval);
    if (0!=$retval) {
        error_log("Running $command yielded return value=$retval, first line of output=$firstline");
        print '<p>WARNING: Problems running mailbox postdeletion script!</p>';
        return FALSE;
    }

    return TRUE;
}

/*
   Called after a domain has been added in the DBMS.
   Returns: boolean.
 */
function domain_postcreation($domain) {
    global $CONF;
    $confpar='domain_postcreation_script';

    if (!isset($CONF[$confpar]) || empty($CONF[$confpar])) {
        return true;
    }

    if (empty($domain)) {
        print '<p>Warning: empty domain parameter.</p>';
        return false;
    }

    $cmdarg1=escapeshellarg($domain);
    $command=$CONF[$confpar]." $cmdarg1";
    $retval=0;
    $output=array();
    $firstline='';
    $firstline=exec($command,$output,$retval);
    if (0!=$retval) {
        error_log("Running $command yielded return value=$retval, first line of output=$firstline");
        print '<p>WARNING: Problems running domain postcreation script!</p>';
        return FALSE;
    }

    return TRUE;
}

/*
   Called after a domain has been deleted in the DBMS.
   Returns: boolean.
 */
function domain_postdeletion($domain) {
    global $CONF;
    $confpar='domain_postdeletion_script';

    if (!isset($CONF[$confpar]) || empty($CONF[$confpar])) {
        return true;
    }

    if (empty($domain)) {
        print '<p>Warning: empty domain parameter.</p>';
        return false;
    }

    $cmdarg1=escapeshellarg($domain);
    $command=$CONF[$confpar]." $cmdarg1";
    $retval=0;
    $output=array();
    $firstline='';
    $firstline=exec($command,$output,$retval);
    if (0!=$retval) {
        error_log("Running $command yielded return value=$retval, first line of output=$firstline");
        print '<p>WARNING: Problems running domain postdeletion script!</p>';
        return FALSE;
    }

    return TRUE;
}

/*
   Called after an alias_domain has been deleted in the DBMS.
   Returns: boolean.
 */
function alias_domain_postdeletion($alias_domain) {
    global $CONF;
    $confpar='alias_domain_postdeletion_script';

    if (!isset($CONF[$confpar]) || empty($CONF[$confpar])) {
        return true;
    }

    if (empty($alias_domain)) {
        print '<p>Warning: empty alias_domain parameter.</p>';
        return false;
    }

    $cmdarg1=escapeshellarg($alias_domain);
    $command=$CONF[$confpar]." $cmdarg1";
    $retval=0;
    $output=array();
    $firstline='';
    $firstline=exec($command,$output,$retval);
    if (0!=$retval) {
        error_log("Running $command yielded return value=$retval, first line of output=$firstline");
        print '<p>WARNING: Problems running alias_domain postdeletion script!</p>';
        return FALSE;
    }

    return TRUE;
}

/*
   Called by mailbox_postcreation() after a mailbox has been
   created. Immediately returns, unless configuration indicates
   that one or more sub-folders should be created.

   Triggers E_USER_ERROR if configuration error is detected.

   If IMAP login fails, the problem is logged to the system log
   (such as /var/log/httpd/error_log), and the function returns
   FALSE.

   Returns FALSE on all other errors, or TRUE if everything
   succeeds.

   Doesn't clean up, if only some of the folders could be
   created.
 */
function create_mailbox_subfolders($login,$cleartext_password) {
    global $CONF;

    if (empty($login)) {
        trigger_error('In '.__FUNCTION__.': empty $login',E_USER_ERROR);
        return FALSE;
    }

    if (!isset($CONF['create_mailbox_subdirs']) || empty($CONF['create_mailbox_subdirs'])) return TRUE;

    if (!is_array($CONF['create_mailbox_subdirs'])) {
        trigger_error('create_mailbox_subdirs must be an array',E_USER_ERROR);
        return FALSE;
    }

    if (!isset($CONF['create_mailbox_subdirs_host']) || empty($CONF['create_mailbox_subdirs_host'])) {
        trigger_error('An IMAP/POP server host ($CONF["create_mailbox_subdirs_host"]) must be configured, if sub-folders are to be created',E_USER_ERROR);
        return FALSE;
    }

    $s_host=$CONF['create_mailbox_subdirs_host'];
    $s_prefix=$CONF['create_mailbox_subdirs_prefix'];
    $s_options='';
    $s_port='';

    if (
        isset($CONF['create_mailbox_subdirs_hostoptions'])
        && !empty($CONF['create_mailbox_subdirs_hostoptions'])
    ) {
        if (!is_array($CONF['create_mailbox_subdirs_hostoptions'])) {
            trigger_error('The $CONF["create_mailbox_subdirs_hostoptions"] parameter must be an array',E_USER_ERROR);
            return FALSE;
        }
        foreach ($CONF['create_mailbox_subdirs_hostoptions'] as $o) {
            $s_options.='/'.$o;
        }
    }

    if (isset($CONF['create_mailbox_subdirs_hostport']) && !empty($CONF['create_mailbox_subdirs_hostport'])) {
        $s_port=$CONF['create_mailbox_subdirs_hostport'];
        if (intval($s_port)!=$s_port) {
            trigger_error('The $CONF["create_mailbox_subdirs_hostport"] parameter must be an integer',E_USER_ERROR);
            return FALSE;
        }
        $s_port=':'.$s_port;
    }

    $s='{'.$s_host.$s_port.$s_options.'}';

    sleep(1); # give the mail triggering the mailbox creation a chance to do its job

    $i=@imap_open($s,$login,$cleartext_password);
    if (FALSE==$i) {
        error_log('Could not log into IMAP/POP server: '.imap_last_error());
        return FALSE;
    }

    foreach($CONF['create_mailbox_subdirs'] as $f) {
        $f='{'.$s_host.'}'.$s_prefix.$f;
        $res=imap_createmailbox($i,$f);
        if (!$res) {
            error_log('Could not create IMAP folder $f: '.imap_last_error());
            @imap_close($i);
            return FALSE;
        }
        @imap_subscribe($i,$f);
    }

    @imap_close($i);
    return TRUE;
}


//
// gen_show_status
// Action: Return a string of colored &nbsp;'s that indicate 
//         the if an alias goto has an error or is sent to
//         addresses list in show_custom_domains 
// Call: gen_show_status (string alias_address)
//
function gen_show_status ($show_alias) {
    global $CONF, $table_alias;
    $stat_string = "";

    $stat_goto = "";
    $stat_result = db_query ("SELECT goto FROM $table_alias WHERE address='$show_alias'");
    if ($stat_result['rows'] > 0) {
        $row = db_row ($stat_result['result']);
        $stat_goto = $row[0];
    }

    if (!empty($CONF['recipient_delimiter'])) {
        $delimiter = preg_quote($CONF['recipient_delimiter'], "/");
        $delimiter_regex = '/' .$delimiter. '[^' .$delimiter. '@]*@/';
    }

    // UNDELIVERABLE CHECK
    if ( $CONF['show_undeliverable'] == 'YES' ) {
        $gotos=array();
        $gotos=explode(',',$stat_goto);
        $undel_string="";

        //make sure this alias goes somewhere known
        $stat_ok = 1;
        while ( ($g=array_pop($gotos)) && $stat_ok ) {
            list(/*NULL*/,$stat_domain) = explode('@',$g);
            $stat_delimiter = "";
			if (!empty($CONF['recipient_delimiter'])) {
				$stat_delimiter = "OR address = '" . preg_replace($delimiter_regex, "@", $g) . "'";
			}
			$stat_result = db_query ("SELECT address FROM $table_alias WHERE address = '$g' OR address = '@$stat_domain' $stat_delimiter");
            if ($stat_result['rows'] == 0) {
                $stat_ok = 0;
            }
            if ( $stat_ok == 0 ) {
                if ( $stat_domain == $CONF['vacation_domain'] || in_array($stat_domain, $CONF['show_undeliverable_exceptions']) ) {
                    $stat_ok = 1;
                }
            }
        } // while
        if ( $stat_ok == 0 ) {
            $stat_string .= "<span style='background-color:" . $CONF['show_undeliverable_color'] .
                "'>" . $CONF['show_status_text'] . "</span>&nbsp;";
        } else {
            $stat_string .= $CONF['show_status_text'] . "&nbsp;";
        } 
    }

    // POP/IMAP CHECK
    if ( $CONF['show_popimap'] == 'YES' ) {
		 $stat_delimiter = "";
		 if (!empty($CONF['recipient_delimiter'])) {
			 $stat_delimiter = ',' . preg_replace($delimiter_regex, "@", $stat_goto);
		 }

        //if the address passed in appears in its own goto field, its POP/IMAP
        # TODO: or not (might also be an alias loop) -> check mailbox table!
        if ( preg_match ('/,' . $show_alias . ',/', ',' . $stat_goto . $stat_delimiter . ',') ) {
            $stat_string .= "<span  style='background-color:" . $CONF['show_popimap_color'] .
                "'>" . $CONF['show_status_text'] . "</span>&nbsp;";
        } else {
            $stat_string .= $CONF['show_status_text'] . "&nbsp;";
        } 
    }

    // CUSTOM DESTINATION CHECK
    if ( count($CONF['show_custom_domains']) > 0 ) {
        for ($i = 0; $i < sizeof ($CONF['show_custom_domains']); $i++) {
            if (preg_match ('/^.*' . $CONF['show_custom_domains'][$i] . '.*$/', $stat_goto)) {
                $stat_string .= "<span  style='background-color:" . $CONF['show_custom_colors'][$i] .
                    "'>" . $CONF['show_status_text'] . "</span>&nbsp;";
            } else {
                $stat_string .= $CONF['show_status_text'] . "&nbsp;";
            } 
        } 
    } else {
        $stat_string .= ";&nbsp;";
    } 

    //   $stat_string .= "<span style='background-color:green'> &nbsp; </span> &nbsp;" .
    //                  "<span style='background-color:blue'> &nbsp; </span> &nbsp;";
    return $stat_string;
}

/*
   Called by create-admin.php and setup.php

   Returns:
   array(
      'error' => 0,                             # 0 on success, otherwise > 0
      'pAdminCreate_admin_message' => '',                         # success / failure message
      'pAdminCreate_admin_username_text' => '', # help text / error message for username
      'pAdminCreate_admin_password_text' => ''  # error message for username
   )
 */

function create_admin($fUsername, $fPassword, $fPassword2, $fDomains, $no_generate_password=0) {
    global $PALANG;
    global $CONF;
    $error = 0;
    $pAdminCreate_admin_message = '';
    $pAdminCreate_admin_username_text_error = '';
    $pAdminCreate_admin_password_text_error = '';

    if (!check_email ($fUsername)) {
        $error = 1;
        $pAdminCreate_admin_username_text_error = $PALANG['pAdminCreate_admin_username_text_error1'];
    }

    if (empty ($fUsername) or admin_exist ($fUsername)) {
        $error = 1;
        $pAdminCreate_admin_username_text_error = $PALANG['pAdminCreate_admin_username_text_error2'];
    }

    if (empty ($fPassword) or empty ($fPassword2) or ($fPassword != $fPassword2)) {
        if (empty ($fPassword) and empty ($fPassword2) and $CONF['generate_password'] == "YES" && $no_generate_password == 0) {
            $fPassword = generate_password ();
        } else {
            $error = 1;
            $pAdminCreate_admin_password_text_error = $PALANG['pAdminCreate_admin_password_text_error'];
        }
    }

    if ($error != 1) {
        $password = pacrypt($fPassword);
        // $pAdminCreate_admin_username_text = $PALANG['pAdminCreate_admin_username_text'];

        $result = db_query ("INSERT INTO " . table_by_key('admin') . " (username,password,created,modified) VALUES ('$fUsername','$password',NOW(),NOW())");
        if ($result['rows'] != 1) {
            $pAdminCreate_admin_message = $PALANG['pAdminCreate_admin_result_error'] . "<br />($fUsername)<br />";
        } else {
            if (!empty ($fDomains[0])) {
                for ($i = 0; $i < sizeof ($fDomains); $i++) {
                    $domain = $fDomains[$i];
                    $result = db_query ("INSERT INTO " . table_by_key ('domain_admins') . " (username,domain,created) VALUES ('$fUsername','$domain',NOW())");
                }
            }
            $pAdminCreate_admin_message = $PALANG['pAdminCreate_admin_result_success'] . "<br />($fUsername";
            if ($CONF['generate_password'] == "YES" && $no_generate_password == 0) {
                $pAdminCreate_admin_message .= " / $fPassword)</br />";
            } else {
                if ($CONF['show_password'] == "YES" && $no_generate_password == 0) {
                    $pAdminCreate_admin_message .= " / $fPassword)</br />";
                } else {
                    $pAdminCreate_admin_message .= ")</br />";
                }
            }
        }
    }

    # TODO: should we log creation, editing and deletion of admins?
    # Note: needs special handling in viewlog, because domain is empty
    # db_log ('', 'create_admin', "$fUsername");

    return array(
        $error,
        $pAdminCreate_admin_message,
        $pAdminCreate_admin_username_text_error,
        $pAdminCreate_admin_password_text_error
    );


}
function getRemoteAddr() {
    $REMOTE_ADDR = 'localhost';
    if (isset($_SERVER['REMOTE_ADDR'])) 
        $REMOTE_ADDR = $_SERVER['REMOTE_ADDR'];
    return $REMOTE_ADDR;
}



/*
   Convert $CONF['whatever'] to boolean
   (obviously only useful for settings that can be YES or NO)

   Returns: TRUE (on YES/yes) or FALSE (on NO/no/not set/unknown value)
 */

function boolconf($setting) {
    global $CONF;
    if (!isset($CONF[$setting])) { # not set
        # TODO: show/log error message on unknown settings?
        return false;
    } elseif (strtoupper($CONF[$setting]) == 'YES') { # YES
        return true;
    } else { # NO, unknown value
        # TODO: show/log error message on unknown value?
        return false;
    }
}

$table_admin = table_by_key ('admin');
$table_alias = table_by_key ('alias');
$table_alias_domain = table_by_key ('alias_domain');
$table_domain = table_by_key ('domain');
$table_domain_admins = table_by_key ('domain_admins');
$table_log = table_by_key ('log');
$table_mailbox = table_by_key ('mailbox');
$table_vacation = table_by_key ('vacation');
$table_vacation_notification = table_by_key('vacation_notification');
$table_quota = table_by_key ('quota');
$table_quota2 = table_by_key ('quota2');
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
