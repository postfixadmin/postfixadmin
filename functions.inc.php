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
 * File: functions.inc.php
 * Contains re-usable code.
 */

$version = '3.2';
$min_db_version = 1840;  # update (at least) before a release with the latest function numbrer in upgrade.php

/**
 * check_session
 *  Action: Check if a session already exists, if not redirect to login.php
 * Call: check_session ()
 * @return String username (e.g. foo@example.com)
 */
function authentication_get_username() {
    if (defined('POSTFIXADMIN_CLI')) {
        return 'CLI';
    }

    if (defined('POSTFIXADMIN_SETUP')) {
        return 'SETUP.PHP';
    }

    if (!isset($_SESSION['sessid'])) {
        header("Location: login.php");
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
    if (isset($_SESSION['sessid'])) {
        if (isset($_SESSION['sessid']['type'])) {
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
    if (isset($_SESSION['sessid'])) {
        if (isset($_SESSION['sessid']['roles'])) {
            if (in_array($role, $_SESSION['sessid']['roles'])) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Used to enforce that $user has a particular role when
 * viewing a page.
 * If they are lacking a role, redirect them to login.php
 *
 * Note, user < admin < global-admin
 */
function authentication_require_role($role) {
    // redirect to appropriate page?
    if (authentication_has_role($role)) {
        return true;
    }

    header("Location: login.php");
    exit(0);
}

/**
 * Initialize a user or admin session
 *
 * @param String $username the user or admin name
 * @param boolean $is_admin true if the user is an admin, false otherwise
 * @return boolean true on success
 */
function init_session($username, $is_admin = false) {
    $status = session_regenerate_id(true);
    $_SESSION['sessid'] = array();
    $_SESSION['sessid']['roles'] = array();
    $_SESSION['sessid']['roles'][] = $is_admin ? 'admin' : 'user';
    $_SESSION['sessid']['username'] = $username;
    $_SESSION['PFA_token'] = md5(uniqid(rand(), true));

    return $status;
}

/**
 * Add an error message for display on the next page that is rendered.
 * @param String/Array message(s) to show.
 *
 * Stores string in session. Flushed through header template.
 * @see _flash_string()
 */
function flash_error($string) {
    _flash_string('error', $string);
}

/**
 * Used to display an info message on successful update.
 * @param String/Array message(s) to show.
 * Stores data in session.
 * @see _flash_string()
 */
function flash_info($string) {
    _flash_string('info', $string);
}
/**
 * 'Private' method used for flash_info() and flash_error().
 */
function _flash_string($type, $string) {
    if (is_array($string)) {
        foreach ($string as $singlestring) {
            _flash_string($type, $singlestring);
        }
        return;
    }

    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = array();
    }
    if (!isset($_SESSION['flash'][$type])) {
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
function check_language($use_post = 1) {
    global $supported_languages; # from languages/languages.php

    $lang = Config::read('default_language');

    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $lang_array = preg_split('/(\s*,\s*)/', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        if (safecookie('lang')) {
            array_unshift($lang_array, safecookie('lang')); # prefer language from cookie
        }
        if ($use_post && safepost('lang')) {
            array_unshift($lang_array, safepost('lang')); # but prefer $_POST['lang'] even more
        }

        for ($i = 0; $i < count($lang_array); $i++) {
            $lang_next = $lang_array[$i];
            $lang_next = strtolower(trim($lang_next));
            $lang_next = preg_replace('/;.*$/', '', $lang_next); # remove things like ";q=0.8"
            if (array_key_exists($lang_next, $supported_languages)) {
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

    foreach ($supported_languages as $lang => $lang_name) {
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




/**
 * Checks if a domain is valid
 * @param string $domain
 * @return empty string if the domain is valid, otherwise string with the errormessage
 *
 * TODO: make check_domain able to handle as example .local domains
 * TODO: skip DNS check if the domain exists in PostfixAdmin?
 */
function check_domain($domain) {
    if (!preg_match('/^([-0-9A-Z]+\.)+' . '([-0-9A-Z]){2,13}$/i', ($domain))) {
        return sprintf(Config::lang('pInvalidDomainRegex'), htmlentities($domain));
    }

    if (Config::bool('emailcheck_resolve_domain') && 'WINDOWS'!=(strtoupper(substr(php_uname('s'), 0, 7)))) {

        // Look for an AAAA, A, or MX record for the domain

        if (function_exists('checkdnsrr')) {
            $start = microtime(true); # check for slow nameservers, part 1

            // AAAA (IPv6) is only available in PHP v. >= 5
            if (version_compare(phpversion(), "5.0.0", ">=") && checkdnsrr($domain, 'AAAA')) {
                $retval = '';
            } elseif (checkdnsrr($domain, 'A')) {
                $retval = '';
            } elseif (checkdnsrr($domain, 'MX')) {
                $retval = '';
            } else {
                $retval = sprintf(Config::lang('pInvalidDomainDNS'), htmlentities($domain));
            }

            $end = microtime(true); # check for slow nameservers, part 2
            $time_needed = $end - $start;
            if ($time_needed > 2) {
                error_log("Warning: slow nameserver - lookup for $domain took $time_needed seconds");
            }

            return $retval;
        } else {
            return 'emailcheck_resolve_domain is enabled, but function (checkdnsrr) missing!';
        }
    }

    return '';
}


/**
 * check_email
 * Checks if an email is valid - if it is, return true, else false.
 * @param String $email - a string that may be an email address.
 * @return empty string if it's a valid email address, otherwise string with the errormessage
 * TODO: make check_email able to handle already added domains
 */
function check_email($email) {
    $ce_email=$email;

    //strip the vacation domain out if we are using it
    //and change from blah#foo.com@autoreply.foo.com to blah@foo.com
    if (Config::bool('vacation')) {
        $vacation_domain = Config::read('vacation_domain');
        $ce_email = preg_replace("/@$vacation_domain\$/", '', $ce_email);
        $ce_email = preg_replace("/#/", '@', $ce_email);
    }

    // Perform non-domain-part sanity checks
    if (!preg_match('/^[-!#$%&\'*+\\.\/0-9=?A-Z^_{|}~]+' . '@' . '[^@]+$/i', $ce_email)) {
        return Config::lang_f('pInvalidMailRegex', $email);
    }

    if (function_exists('filter_var')) {
        $check = filter_var($email, FILTER_VALIDATE_EMAIL);
        if (!$check) {
            return Config::lang_f('pInvalidMailRegex', $email);
        }
    }
    // Determine domain name
    $matches=array();
    if (!preg_match('|@(.+)$|', $ce_email, $matches)) {
        return Config::lang_f('pInvalidMailRegex', $email);
    }
    $domain=$matches[1];

    # check domain name
    return check_domain($domain);
}



/**
 * Clean a string, escaping any meta characters that could be
 * used to disrupt an SQL string. i.e. "'" => "\'" etc.
 *
 * @param string|array parameters to escape
 * @return string|array of cleaned data, suitable for use within an SQL statement.
 */
function escape_string($string) {
    global $CONF;
    // if the string is actually an array, do a recursive cleaning.
    // Note, the array keys are not cleaned.
    if (is_array($string)) {
        $clean = array();
        foreach ($string as $k => $v) {
            $clean[$k] = escape_string($v);
        }
        return $clean;
    }
    if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
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
        if (db_sqlite()) {
            $escaped_string = SQLite3::escapeString($string);
        }
        if (db_pgsql()) {
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
 *  @param string $param parameter name.
 *  @param string $default (optional) - default value if key is not set.
 *  @return string
 */
function safeget($param, $default="") {
    $retval=$default;
    if (isset($_GET[$param])) {
        $retval=$_GET[$param];
    }
    return $retval;
}

/**
 * safepost - similar to safeget() but for $_POST
 * @see safeget()
 * @param string $param parameter name
 * @param string $default (optional) default value (defaults to "")
 * @return string - value in $_POST[$param] or $default
 */
function safepost($param, $default="") {
    $retval=$default;
    if (isset($_POST[$param])) {
        $retval=$_POST[$param];
    }
    return $retval;
}

/**
 * safeserver
 * @see safeget()
 * @param string $param
 * @param string $default (optional)
 * @return string value from $_SERVER[$param] or $default
 */
function safeserver($param, $default="") {
    $retval=$default;
    if (isset($_SERVER[$param])) {
        $retval=$_SERVER[$param];
    }
    return $retval;
}

/**
 * safecookie
 * @see safeget()
 * @param string $param
 * @param string $default (optional)
 * @return string value from $_COOKIE[$param] or $default
 */
function safecookie($param, $default="") {
    $retval=$default;
    if (isset($_COOKIE[$param])) {
        $retval=$_COOKIE[$param];
    }
    return $retval;
}

/**
 * safesession
 * @see safeget()
 * @param string $param
 * @param string $default (optional)
 * @return string value from $_SESSION[$param] or $default
 */
function safesession($param, $default="") {
    $retval=$default;
    if (isset($_SESSION[$param])) {
        $retval=$_SESSION[$param];
    }
    return $retval;
}


/**
 * pacol
 * @param int $allow_editing
 * @param int $display_in_form
 * @param int display_in_list
 * @param string $type
 * @param string PALANG_label
 * @param string PALANG_desc
 * @param any optional $default
 * @param array $options optional options
 * @param int or $not_in_db - if array, can contain the remaining parameters as associated array. Otherwise counts as $not_in_db
 * @param ...
 * @return array for $struct
 */
function pacol($allow_editing, $display_in_form, $display_in_list, $type, $PALANG_label, $PALANG_desc, $default = "", $options = array(), $multiopt=0, $dont_write_to_db=0, $select="", $extrafrom="", $linkto="") {
    if ($PALANG_label != '') {
        $PALANG_label = Config::lang($PALANG_label);
    }
    if ($PALANG_desc  != '') {
        $PALANG_desc  = Config::lang($PALANG_desc);
    }

    if (is_array($multiopt)) { # remaining parameters provided in named array
        $not_in_db = 0; # keep default value
        foreach ($multiopt as $key => $value) {
            $$key = $value; # extract everything to the matching variable
        }
    } else {
        $not_in_db = $multiopt;
    }

    return array(
        'editable'          => $allow_editing,
        'display_in_form'   => $display_in_form,
        'display_in_list'   => $display_in_list,
        'type'              => $type,
        'label'             => $PALANG_label,   # $PALANG field label
        'desc'              => $PALANG_desc,    # $PALANG field description
        'default'           => $default,
        'options'           => $options,
        'not_in_db'         => $not_in_db,
        'dont_write_to_db'  => $dont_write_to_db,
        'select'            => $select,         # replaces the field name after SELECT
        'extrafrom'         => $extrafrom,      # added after FROM xy - useful for JOINs etc.
        'linkto'            => $linkto,         # make the value a link - %s will be replaced with the ID
    );
}

//
// get_domain_properties
// Action: Get all the properties of a domain.
// Call: get_domain_properties (string domain)
//
function get_domain_properties($domain) {
    $handler = new DomainHandler();
    if (!$handler->init($domain)) {
        die("Error: " . join("\n", $handler->errormsg));
    }

    if (!$handler->view()) {
        die("Error: " . join("\n", $handler->errormsg));
    }

    $result = $handler->result();
    return $result;
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
    $query = "SELECT count(*) as counter FROM (SELECT $idxfield $querypart) AS tmp";
    $result = db_query($query);
    if ($result['rows'] > 0) {
        $row = db_assoc($result['result']);
        $count_results = $row['counter'] -1; # we start counting at 0, not 1
    }
    #    echo "<p>rows: " . ($count_results +1) . " --- $query";

    if ($count_results < $page_size) {
        return array(); # only one page - no pagebrowser required
    }

    # init row counter
    $initcount = "SET @row=-1";
    if (db_pgsql()) {
        $initcount = "CREATE TEMPORARY SEQUENCE rowcount MINVALUE 0";
    }
    if (!db_sqlite()) {
        $result = db_query($initcount);
    }

    # get labels for relevant rows (first and last of each page)
    $page_size_zerobase = $page_size - 1;
    $query = "
        SELECT * FROM (
            SELECT $idxfield AS label, @row := @row + 1 AS row $querypart
        ) idx WHERE MOD(idx.row, $page_size) IN (0,$page_size_zerobase) OR idx.row = $count_results
    ";

    if (db_pgsql()) {
        $query = "
            SELECT * FROM (
                SELECT $idxfield AS label, nextval('rowcount') AS row $querypart
            ) idx WHERE MOD(idx.row, $page_size) IN (0,$page_size_zerobase) OR idx.row = $count_results
        ";
    }

    if (db_sqlite()) {
        $query = "
            WITH idx AS (SELECT * $querypart)
                SELECT $idxfield AS label, (SELECT (COUNT(*) - 1) FROM idx t1 WHERE t1.$idxfield <= t2.$idxfield) AS row
                FROM idx t2
                WHERE (row % $page_size) IN (0,$page_size_zerobase) OR row = $count_results";
    }

    # TODO: $query is MySQL-specific

    # PostgreSQL:
    # http://www.postgresql.org/docs/8.1/static/sql-createsequence.html
    # http://www.postgresonline.com/journal/archives/79-Simulating-Row-Number-in-PostgreSQL-Pre-8.4.html
    # http://www.pg-forum.de/sql/1518-nummerierung-der-abfrageergebnisse.html
    # CREATE TEMPORARY SEQUENCE foo MINVALUE 0 MAXVALUE $page_size_zerobase CYCLE
    # afterwards: DROP SEQUENCE foo

    $result = db_query($query);
    if ($result['rows'] > 0) {
        while ($row = db_assoc($result['result'])) {
            if ($row2 = db_assoc($result['result'])) {
                $label = substr($row['label'], 0, $label_len) . '-' . substr($row2['label'], 0, $label_len);
                $pagebrowser[] = $label;
            } else { # only one row remaining
                $label = substr($row['label'], 0, $label_len);
                $pagebrowser[] = $label;
            }
        }
    }

    if (db_pgsql()) {
        db_query("DROP SEQUENCE rowcount");
    }

    return $pagebrowser;
}






//
// divide_quota
// Action: Recalculates the quota from MBs to bytes (divide, /)
// Call: divide_quota (string $quota)
//
function divide_quota($quota) {
    if ($quota == -1) {
        return $quota;
    }
    $value = round($quota / Config::read('quota_multiplier'), 2);
    return $value;
}



//
// check_owner
// Action: Checks if the admin is the owner of the domain (or global-admin)
// Call: check_owner (string admin, string domain)
//
function check_owner($username, $domain) {
    $table_domain_admins = table_by_key('domain_admins');
    $E_username = escape_string($username);
    $E_domain = escape_string($domain);
    $result = db_query("SELECT 1 FROM $table_domain_admins WHERE username='$E_username' AND (domain='$E_domain' OR domain='ALL') AND active='1'");

    if ($result['rows'] == 1 || $result['rows'] == 2) { # "ALL" + specific domain permissions is possible
        # TODO: if superadmin, check if given domain exists in the database
        return true;
    } else {
        if ($result['rows'] > 2) { # more than 2 results means something really strange happened...
            flash_error("Permission check returned multiple results. Please go to 'edit admin' for your username and press the save "
             . "button once to fix the database. If this doesn't help, open a bugreport.");
        }
        return false;
    }
}



/**
 * List domains for an admin user.
 * @param String $username
 * @return array of domain names.
 */
function list_domains_for_admin($username) {
    $table_domain = table_by_key('domain');
    $table_domain_admins = table_by_key('domain_admins');

    $E_username = escape_string($username);

    $query = "SELECT $table_domain.domain FROM $table_domain ";
    $condition[] = "$table_domain.domain != 'ALL'";

    $result = db_query("SELECT username FROM $table_domain_admins WHERE username='$E_username' AND domain='ALL'");
    if ($result['rows'] < 1) { # not a superadmin
        $query .= " LEFT JOIN $table_domain_admins ON $table_domain.domain=$table_domain_admins.domain ";
        $condition[] = "$table_domain_admins.username='$E_username' ";
        $condition[] = "$table_domain.active='"   . db_get_boolean(true)  . "'"; # TODO: does it really make sense to exclude inactive...
        $condition[] = "$table_domain.backupmx='" . db_get_boolean(false) . "'"; # TODO: ... and backupmx domains for non-superadmins?
    }

    $query .= " WHERE " . join(' AND ', $condition);
    $query .= " ORDER BY $table_domain.domain";

    $list = array();
    $result = db_query($query);
    if ($result['rows'] > 0) {
        $i = 0;
        while ($row = db_assoc($result['result'])) {
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
function list_domains() {
    $list = array();

    $table_domain = table_by_key('domain');
    $result = db_query("SELECT domain FROM $table_domain WHERE domain!='ALL' ORDER BY domain");
    if ($result['rows'] > 0) {
        $i = 0;
        while ($row = db_assoc($result['result'])) {
            $list[$i] = $row['domain'];
            $i++;
        }
    }
    return $list;
}




//
// list_admins
// Action: Lists all the admins
// Call: list_admins ()
//
// was admin_list_admins
//
function list_admins() {
    $handler = new AdminHandler();

    $handler->getList('');
    return $handler->result();
}



//
// encode_header
// Action: Encode a string according to RFC 1522 for use in headers if it contains 8-bit characters.
// Call: encode_header (string header, string charset)
//
function encode_header($string, $default_charset = "utf-8") {
    if (strtolower($default_charset) == 'iso-8859-1') {
        $string = str_replace("\240", ' ', $string);
    }

    $j = strlen($string);
    $max_l = 75 - strlen($default_charset) - 7;
    $aRet = array();
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
                    $aRet[] = substr($string, $iOffset, $iEncStart-$iOffset);
                    $aRet[] = "=?$default_charset?Q?$ret?=";
                    $iOffset = $i;
                    $cur_l = 0;
                    $ret = '';
                    $iEncStart = false;
                } else {
                    $ret .= sprintf("=%02X", ord($string{$i}));
                }
                break;
            case '(':
            case ')':
                if ($iEncStart !== false) {
                    $aRet[] = substr($string, $iOffset, $iEncStart-$iOffset);
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
                        $aRet[] = substr($string, $iOffset, $iEncStart-$iOffset);
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
                $k = ord($string{$i});
                if ($k > 126) {
                    if ($iEncStart === false) {
                        // do not start encoding in the middle of a string, also take the rest of the word.
                        $sLeadString = substr($string, 0, $i);
                        $aLeadString = explode(' ', $sLeadString);
                        $sToBeEncoded = array_pop($aLeadString);
                        $iEncStart = $i - strlen($sToBeEncoded);
                        $ret .= $sToBeEncoded;
                        $cur_l += strlen($sToBeEncoded);
                    }
                    $cur_l += 3;
                    // first we add the encoded string that reached it's max size
                    if ($cur_l > ($max_l-2)) {
                        $aRet[] = substr($string, $iOffset, $iEncStart-$iOffset);
                        $aRet[] = "=?$default_charset?Q?$ret?= ";
                        $cur_l = 3;
                        $ret = '';
                        $iOffset = $i;
                        $iEncStart = $i;
                    }
                    $enc_init = true;
                    $ret .= sprintf("=%02X", $k);
                } else {
                    if ($iEncStart !== false) {
                        $cur_l++;
                        if ($cur_l > $max_l) {
                            $aRet[] = substr($string, $iOffset, $iEncStart-$iOffset);
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
            $aRet[] = substr($string, $iOffset, $iEncStart-$iOffset);
            $aRet[] = "=?$default_charset?Q?$ret?=";
        } else {
            $aRet[] = substr($string, $iOffset);
        }
        $string = implode('', $aRet);
    }
    return $string;
}



/**/ if (!function_exists('random_int')) { # random_int() is available since PHP 7, compat wrapper for PHP 5.x
    function random_int($min, $max) {
        return mt_rand($min, $max);
    }
/**/ }

/**
 * Generate a random password of $length characters.
 * @param int $length (optional, default: 12)
 * @return string
 *
 */
function generate_password($length = 12) {

    // define possible characters
    $possible = "2345678923456789abcdefghijkmnpqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ"; # skip 0 and 1 to avoid confusion with O and l

    // add random characters to $password until $length is reached
    $password = "";
    while (strlen($password) < $length) {
        $random = random_int(0, strlen($possible) -1);
        $char = substr($possible, $random, 1);

        // we don't want this character if it's already in the password
        if (!strstr($password, $char)) {
            $password .= $char;
        }
    }

    return $password;
}



/**
 * Check if a password is strong enough based on the conditions in $CONF['password_validation']
 * @param string $password
 * @return array of error messages, or empty array if the password is ok
 */
function validate_password($password) {
    $val_conf = Config::read('password_validation');
    $result = array();

    $minlen = (int) Config::read('min_password_length'); # used up to 2.3.x - check it for backward compatibility
    if ($minlen > 0) {
        $val_conf['/.{' . $minlen . '}/'] = "password_too_short $minlen";
    }

    foreach ($val_conf as $regex => $message) {
        if (!preg_match($regex, $password)) {
            $msgparts = preg_split("/ /", $message, 2);
            if (count($msgparts) == 1) {
                $result[] = Config::lang($msgparts[0]);
            } else {
                $result[] = sprintf(Config::lang($msgparts[0]), $msgparts[1]);
            }
        }
    }

    return $result;
}

function _pacrypt_md5crypt($pw, $pw_db) {
    $split_salt = preg_split('/\$/', $pw_db);
    if (isset($split_salt[2])) {
        $salt = $split_salt[2];
        return md5crypt($pw, $salt);
    }

    return md5crypt($pw);
}

function _pacrypt_crypt($pw, $pw_db) {
    if ($pw_db) {
        return crypt($pw, $pw_db);
    }
    return crypt($pw);
}

function _pacrypt_mysql_encrypt($pw, $pw_db) {
    // See https://sourceforge.net/tracker/?func=detail&atid=937966&aid=1793352&group_id=191583
    // this is apparently useful for pam_mysql etc.
    $pw = escape_string($pw);
    if ($pw_db!="") {
        $salt=escape_string(substr($pw_db, 0, 2));
        $res=db_query("SELECT ENCRYPT('".$pw."','".$salt."');");
    } else {
        $res=db_query("SELECT ENCRYPT('".$pw."');");
    }
    $l = db_row($res["result"]);
    $password = $l[0];
    return $password;
}

function _pacrypt_authlib($pw, $pw_db) {
    global $CONF;
    $flavor = $CONF['authlib_default_flavor'];
    $salt = substr(create_salt(), 0, 2); # courier-authlib supports only two-character salts
    if (preg_match('/^{.*}/', $pw_db)) {
        // we have a flavor in the db -> use it instead of default flavor
        $result = preg_split('/[{}]/', $pw_db, 3); # split at { and/or }
        $flavor = $result[1];
        $salt = substr($result[2], 0, 2);
    }

    if (stripos($flavor, 'md5raw') === 0) {
        $password = '{' . $flavor . '}' . md5($pw);
    } elseif (stripos($flavor, 'md5') === 0) {
        $password = '{' . $flavor . '}' . base64_encode(md5($pw, true));
    } elseif (stripos($flavor, 'crypt') === 0) {
        $password = '{' . $flavor . '}' . crypt($pw, $salt);
    } elseif (stripos($flavor, 'SHA') === 0) {
        $password = '{' . $flavor . '}' . base64_encode(sha1($pw, true));
    } else {
        die("authlib_default_flavor '" . $flavor . "' unknown. Valid flavors are 'md5raw', 'md5', 'SHA' and 'crypt'");
    }
    return $password;
}

/**
 * @param string $pw - plain text password
 * @param string $pw_db - encrypted password, or '' for generation.
 * @return string
 */
function _pacrypt_dovecot($pw, $pw_db) {
    global $CONF;

    $split_method = preg_split('/:/', $CONF['encrypt']);
    $method       = strtoupper($split_method[1]);
    # If $pw_db starts with {method}, change $method accordingly
    if (!empty($pw_db) && preg_match('/^\{([A-Z0-9.-]+)\}.+/', $pw_db, $method_matches)) {
        $method = $method_matches[1];
    }
    if (! preg_match("/^[A-Z0-9.-]+$/", $method)) {
        die("invalid dovecot encryption method");
    }

    # TODO: check against a fixed list?
    # if (strtolower($method) == 'md5-crypt') die("\$CONF['encrypt'] = 'dovecot:md5-crypt' will not work because dovecotpw generates a random salt each time. Please use \$CONF['encrypt'] = 'md5crypt' instead.");
    # $crypt_method = preg_match ("/.*-CRYPT$/", $method);

    # digest-md5 and SCRAM-SHA-1 hashes include the username - until someone implements it, let's declare it as unsupported
    if (strtolower($method) == 'digest-md5') {
        die("Sorry, \$CONF['encrypt'] = 'dovecot:digest-md5' is not supported by PostfixAdmin.");
    }
    if (strtoupper($method) == 'SCRAM-SHA-1') {
        die("Sorry, \$CONF['encrypt'] = 'dovecot:scram-sha-1' is not supported by PostfixAdmin.");
    }
    # TODO: add -u option for those hashes, or for everything that is salted (-u was available before dovecot 2.1 -> no problem with backward compability)

    $dovecotpw = "doveadm pw";
    if (!empty($CONF['dovecotpw'])) {
        $dovecotpw = $CONF['dovecotpw'];
    }

    # Use proc_open call to avoid safe_mode problems and to prevent showing plain password in process table
    $spec = array(
        0 => array("pipe", "r"), // stdin
        1 => array("pipe", "w"), // stdout
        2 => array("pipe", "w"), // stderr
    );

    $nonsaltedtypes = "SHA|SHA1|SHA256|SHA512|CLEAR|CLEARTEXT|PLAIN|PLAIN-TRUNC|CRAM-MD5|HMAC-MD5|PLAIN-MD4|PLAIN-MD5|LDAP-MD5|LANMAN|NTLM|RPA";
    $salted = ! preg_match("/^($nonsaltedtypes)(\.B64|\.BASE64|\.HEX)?$/", strtoupper($method));

    $dovepasstest = '';
    if ($salted && (!empty($pw_db))) {
        # only use -t for salted passwords to be backward compatible with dovecot < 2.1
        $dovepasstest = " -t " . escapeshellarg($pw_db);
    }
    $pipe = proc_open("$dovecotpw '-s' $method$dovepasstest", $spec, $pipes);

    if (!$pipe) {
        die("can't proc_open $dovecotpw");
    }

    // use dovecot's stdin, it uses getpass() twice (except when using -t)
    // Write pass in pipe stdin
    if (empty($dovepasstest)) {
        fwrite($pipes[0], $pw . "\n", 1+strlen($pw));
        usleep(1000);
    }
    fwrite($pipes[0], $pw . "\n", 1+strlen($pw));
    fclose($pipes[0]);

    // Read hash from pipe stdout
    $password = fread($pipes[1], "200");

    if (empty($dovepasstest)) {
        if (!preg_match('/^\{' . $method . '\}/', $password)) {
            $stderr_output = stream_get_contents($pipes[2]);
            error_log('dovecotpw password encryption failed. STDERR output: '. $stderr_output);
            die("can't encrypt password with dovecotpw, see error log for details");
        }
    } else {
        if (!preg_match('(verified)', $password)) {
            $password="Thepasswordcannotbeverified";
        } else {
            $password = rtrim(str_replace('(verified)', '', $password));
        }
    }

    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($pipe);

    if ((!empty($pw_db)) && (substr($pw_db, 0, 1) != '{')) {
        # for backward compability with "old" dovecot passwords that don't have the {method} prefix
        $password = str_replace('{' . $method . '}', '', $password);
    }

    return rtrim($password);
}

/**
 * @param string $pw
 * @param string $pw_db (can be empty if setting a new password)
 * @return string
 */
function _pacrypt_php_crypt($pw, $pw_db) {
    global $CONF;

    // use PHPs crypt(), which uses the system's crypt()
    // same algorithms as used in /etc/shadow
    // you can have mixed hash types in the database for authentication, changed passwords get specified hash type
    // the algorithm for a new hash is chosen by feeding a salt with correct magic to crypt()
    // set $CONF['encrypt'] to 'php_crypt' to use the default SHA512 crypt method
    // set $CONF['encrypt'] to 'php_crypt:METHOD' to use another method; methods supported: DES, MD5, BLOWFISH, SHA256, SHA512
    // tested on linux

    if (strlen($pw_db) > 0) {
        // existing pw provided. send entire password hash as salt for crypt() to figure out
        $salt = $pw_db;
    } else {
        $salt_method = 'SHA512'; // hopefully a reasonable default (better than MD5)
        // no pw provided. create new password hash
        if (strpos($CONF['encrypt'], ':') !== false) {
            // use specified hash method
            $split_method = explode(':', $CONF['encrypt']);
            $salt_method = $split_method[1];
        }
        // create appropriate salt for selected hash method
        $salt = _php_crypt_generate_crypt_salt($salt_method);
    }
    // send it to PHPs crypt()
    $password = crypt($pw, $salt);
    return $password;
}

/**
 * @param string $hash_type must be one of: MD5, DES, BLOWFISH, SHA256 or SHA512  (default)
 * @return string
 */
function _php_crypt_generate_crypt_salt($hash_type='SHA512') {
    // generate a salt (with magic matching chosen hash algorithm) for the PHP crypt() function

    // most commonly used alphabet
    $alphabet = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    switch ($hash_type) {
    case 'DES':
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $length = 2;
        $salt = _php_crypt_random_string($alphabet, $length);
        return $salt;

    case 'MD5':
        $length = 12;
        $algorithm = '1';
        $salt = _php_crypt_random_string($alphabet, $length);
        return sprintf('$%s$%s', $algorithm, $salt);

    case 'BLOWFISH':
        $length = 22;
        $cost = 10;
        if (version_compare(PHP_VERSION, '5.3.7') >= 0) {
            $algorithm = '2y'; // bcrypt, with fixed unicode problem
        } else {
            $algorithm = '2a'; // bcrypt
        }
        $salt = _php_crypt_random_string($alphabet, $length);
        return sprintf('$%s$%02d$%s', $algorithm, $cost, $salt);

    case 'SHA256':
        $length = 16;
        $algorithm = '5';
        $salt = _php_crypt_random_string($alphabet, $length);
        return sprintf('$%s$%s', $algorithm, $salt);

    case 'SHA512':
        $length = 16;
        $algorithm = '6';
        $salt = _php_crypt_random_string($alphabet, $length);
        return sprintf('$%s$%s', $algorithm, $salt);

    default:
        die("unknown hash type: '$hash_type'");
    }
}

/**
 * Generates a random string of specified $length from $characters.
 * @param string $characters
 * @param int $length
 * @return string of given $length
 */
function _php_crypt_random_string($characters, $length) {
    $string = '';
    for ($p = 0; $p < $length; $p++) {
        $string .= $characters[random_int(0, strlen($characters) -1)];
    }
    return $string;
}


/**
 * Encrypt a password, using the apparopriate hashing mechanism as defined in
 * config.inc.php ($CONF['encrypt']).
 * When wanting to compare one pw to another, it's necessary to provide the salt used - hence
 * the second parameter ($pw_db), which is the existing hash from the DB.
 *
 * @param string $pw
 * @param string $pw_db optional encrypted password
 * @return string encrypted password.
 */
function pacrypt($pw, $pw_db="") {
    global $CONF;

    switch ($CONF['encrypt']) {
        case 'md5crypt':
            return _pacrypt_md5crypt($pw, $pw_db);
        case 'md5':
            return md5($pw);
        case 'system':
            return _pacrypt_crypt($pw, $pw_db);
        case 'cleartext':
            return $pw;
        case 'mysql_encrypt':
            return _pacrypt_mysql_encrypt($pw, $pw_db);
        case 'authlib':
            return _pacrypt_authlib($pw, $pw_db);
    }

    if (preg_match("/^dovecot:/", $CONF['encrypt'])) {
        return _pacrypt_dovecot($pw, $pw_db);
    }

    if (substr($CONF['encrypt'], 0, 9) === 'php_crypt') {
        return _pacrypt_php_crypt($pw, $pw_db);
    }

    die('unknown/invalid $CONF["encrypt"] setting: ' . $CONF['encrypt']);
}

//
// md5crypt
// Action: Creates MD5 encrypted password
// Call: md5crypt (string cleartextpassword)
//

function md5crypt($pw, $salt="", $magic="") {
    $MAGIC = "$1$";

    if ($magic == "") {
        $magic = $MAGIC;
    }
    if ($salt == "") {
        $salt = create_salt();
    }
    $slist = explode("$", $salt);
    if ($slist[0] == "1") {
        $salt = $slist[1];
    }

    $salt = substr($salt, 0, 8);
    $ctx = $pw . $magic . $salt;
    $final = hex2bin(md5($pw . $salt . $pw));

    for ($i=strlen($pw); $i>0; $i-=16) {
        if ($i > 16) {
            $ctx .= substr($final, 0, 16);
        } else {
            $ctx .= substr($final, 0, $i);
        }
    }
    $i = strlen($pw);

    while ($i > 0) {
        if ($i & 1) {
            $ctx .= chr(0);
        } else {
            $ctx .= $pw[0];
        }
        $i = $i >> 1;
    }
    $final = hex2bin(md5($ctx));

    for ($i=0;$i<1000;$i++) {
        $ctx1 = "";
        if ($i & 1) {
            $ctx1 .= $pw;
        } else {
            $ctx1 .= substr($final, 0, 16);
        }
        if ($i % 3) {
            $ctx1 .= $salt;
        }
        if ($i % 7) {
            $ctx1 .= $pw;
        }
        if ($i & 1) {
            $ctx1 .= substr($final, 0, 16);
        } else {
            $ctx1 .= $pw;
        }
        $final = hex2bin(md5($ctx1));
    }
    $passwd = "";
    $passwd .= to64(((ord($final[0]) << 16) | (ord($final[6]) << 8) | (ord($final[12]))), 4);
    $passwd .= to64(((ord($final[1]) << 16) | (ord($final[7]) << 8) | (ord($final[13]))), 4);
    $passwd .= to64(((ord($final[2]) << 16) | (ord($final[8]) << 8) | (ord($final[14]))), 4);
    $passwd .= to64(((ord($final[3]) << 16) | (ord($final[9]) << 8) | (ord($final[15]))), 4);
    $passwd .= to64(((ord($final[4]) << 16) | (ord($final[10]) << 8) | (ord($final[5]))), 4);
    $passwd .= to64(ord($final[11]), 2);
    return "$magic$salt\$$passwd";
}

function create_salt() {
    srand((double) microtime()*1000000);
    $salt = substr(md5(rand(0, 9999999)), 0, 8);
    return $salt;
}

/**/ if (!function_exists('hex2bin')) { # PHP around 5.3.8 includes hex2bin as native function - http://php.net/hex2bin
    function hex2bin($str) {
        $len = strlen($str);
        $nstr = "";
        for ($i=0;$i<$len;$i+=2) {
            $num = sscanf(substr($str, $i, 2), "%x");
            $nstr.=chr($num[0]);
        }
        return $nstr;
    }
    /**/
}

/*
 * remove item $item from array $array
 */
function remove_from_array($array, $item) {
    # array_diff might be faster, but doesn't provide an easy way to know if the value was found or not
    # return array_diff($array, array($item));
    $ret = array_search($item, $array);
    if ($ret === false) {
        $found = 0;
    } else {
        $found = 1;
        unset($array[$ret]);
    }
    return array($found, $array);
}

function to64($v, $n) {
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
function smtp_mail($to, $from, $data, $body = "") {
    global $CONF;
    $smtpd_server = $CONF['smtp_server'];
    $smtpd_port = $CONF['smtp_port'];
    //$smtp_server = $_SERVER["SERVER_NAME"];
    $smtp_server = php_uname('n');
    if (!empty($CONF['smtp_client'])) {
        $smtp_server = $CONF['smtp_client'];
    }
    $errno = "0";
    $errstr = "0";
    $timeout = "30";

    if ($body != "") {
        $maildata =
            "To: " . $to . "\n"
            . "From: " . $from . "\n"
            . "Subject: " . encode_header($data) . "\n"
            . "MIME-Version: 1.0\n"
            . "Content-Type: text/plain; charset=utf-8\n"
            . "Content-Transfer-Encoding: 8bit\n"
            . "\n"
            . $body
        ;
    } else {
        $maildata = $data;
    }

    $fh = @fsockopen($smtpd_server, $smtpd_port, $errno, $errstr, $timeout);

    if (!$fh) {
        error_log("fsockopen failed - errno: $errno - errstr: $errstr");
        return false;
    } else {
        $res = smtp_get_response($fh);
        fputs($fh, "EHLO $smtp_server\r\n");
        $res = smtp_get_response($fh);
        fputs($fh, "MAIL FROM:<$from>\r\n");
        $res = smtp_get_response($fh);
        fputs($fh, "RCPT TO:<$to>\r\n");
        $res = smtp_get_response($fh);
        fputs($fh, "DATA\r\n");
        $res = smtp_get_response($fh);
        fputs($fh, "$maildata\r\n.\r\n");
        $res = smtp_get_response($fh);
        fputs($fh, "QUIT\r\n");
        $res = smtp_get_response($fh);
        fclose($fh);
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
    $admin_email = Config::read('admin_email');
    if (!empty($admin_email)) {
        return $admin_email;
    } else {
        return authentication_get_username();
    }
}


//
// smtp_get_response
// Action: Get response from mail server
// Call: smtp_get_response (string FileHandle)
//
function smtp_get_response($fh) {
    $res ='';
    do {
        $line = fgets($fh, 256);
        $res .= $line;
    } while (preg_match("/^\d\d\d\-/", $line));
    return $res;
}



$DEBUG_TEXT = "\n
    <p />\n
    Please check the documentation and website for more information.\n
    <p />\n
    <a href=\"http://postfixadmin.sf.net/\">Postfix Admin</a><br />\n
    <a href='https://sourceforge.net/p/postfixadmin/discussion/676076'>Forums</a>
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
 *
 * @return resource connection to db (normally)
 */
function db_connect($ignore_errors = false) {
    global $CONF;
    global $DEBUG_TEXT;
    if ($ignore_errors != 0) {
        $DEBUG_TEXT = '';
    }
    $error_text = '';

    static $link;
    if (isset($link) && $link) {
        if ($ignore_errors) {
            return array($link, $error_text);
        }
        return $link;
    }
    $link = 0;

    if ($CONF['database_type'] == "mysql") {
        if (function_exists("mysql_connect")) {
            $link = @mysql_connect($CONF['database_host'], $CONF['database_user'], $CONF['database_password']) or $error_text .= ("<p />DEBUG INFORMATION:<br />Connect: " .  mysql_error() . "$DEBUG_TEXT");
            if ($link) {
                @mysql_query("SET CHARACTER SET utf8", $link);
                @mysql_query("SET COLLATION_CONNECTION='utf8_general_ci'", $link);
                @mysql_select_db($CONF['database_name'], $link) or $error_text .= ("<p />DEBUG INFORMATION:<br />MySQL Select Database: " .  mysql_error() . "$DEBUG_TEXT");
            }
        } else {
            $error_text .= "<p />DEBUG INFORMATION:<br />MySQL 3.x / 4.0 functions not available! (php5-mysql installed?)<br />database_type = 'mysql' in config.inc.php, are you using a different database? $DEBUG_TEXT";
        }
    } elseif ($CONF['database_type'] == "mysqli") {
        $is_connected = false;
        if ($CONF['database_use_ssl']) {
            if (function_exists("mysqli_real_connect")) {
                $link = mysqli_init();
                $link->ssl_set($CONF['database_ssl_key'], $CONF['database_ssl_cert'], $CONF['database_ssl_ca'], $CONF['database_ssl_ca_path'], $CONF['database_ssl_cipher']);
                $connected = mysqli_real_connect($link, $CONF['database_host'], $CONF['database_user'], $CONF['database_password'], $CONF['database_name'], $CONF['database_port']);
                $is_connected = $connected;
            } else {
                $error_text .= "<p />DEBUG INFORMATION:<br />MySQLi 5 functions not available! (php5-mysqli installed?)<br />database_type = 'mysqli' in config.inc.php, are you using a different database? $DEBUG_TEXT";
            }
        } else {
            if (function_exists("mysqli_connect")) {
                $link = @mysqli_connect($CONF['database_host'], $CONF['database_user'], $CONF['database_password'], $CONF['database_name'], $CONF['database_port'], $CONF['database_socket']) or $error_text .= ("<p />DEBUG INFORMATION:<br />Connect: " . mysqli_connect_error() . "$DEBUG_TEXT");
                $is_connected = $link;
            } else {
                $error_text .= "<p />DEBUG INFORMATION:<br />MySQL 4.1 functions not available! (php5-mysqli installed?)<br />database_type = 'mysqli' in config.inc.php, are you using a different database? $DEBUG_TEXT";
            }
        }
        if ($is_connected) {
            @mysqli_query($link, "SET CHARACTER SET utf8");
            @mysqli_query($link, "SET COLLATION_CONNECTION='utf8_general_ci'");
        }
    } elseif (db_sqlite()) {
        if (class_exists("SQLite3")) {
            if ($CONF['database_name'] == '' || !is_dir(dirname($CONF['database_name'])) || !is_writable(dirname($CONF['database_name']))) {
                $error_text .= ("<p />DEBUG INFORMATION<br />Connect: given database path does not exist, is not writable, or \$CONF['database_name'] is empty.");
            } else {
                $link = new SQLite3($CONF['database_name']) or $error_text .= ("<p />DEBUG INFORMATION<br />Connect: failed to connect to database. $DEBUG_TEXT");
                $link->createFunction('base64_decode', 'base64_decode');
            }
        } else {
            $error_text .= "<p />DEBUG INFORMATION:<br />SQLite functions not available! (php5-sqlite installed?)<br />database_type = 'sqlite' in config.inc.php, are you using a different database? $DEBUG_TEXT";
        }
    } elseif (db_pgsql()) {
        if (function_exists("pg_pconnect")) {
            if (!isset($CONF['database_port'])) {
                $CONF['database_port'] = '5432';
            }
            $connect_string = "host=" . $CONF['database_host'] . " port=" . $CONF['database_port'] . " dbname=" . $CONF['database_name'] . " user=" . $CONF['database_user'] . " password=" . $CONF['database_password'];
            $link = @pg_pconnect($connect_string) or $error_text .= ("<p />DEBUG INFORMATION:<br />Connect: failed to connect to database. $DEBUG_TEXT");
            if ($link) {
                pg_set_client_encoding($link, 'UNICODE');
            }
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
    if (! (is_bool($bool) || $bool == '0' || $bool == '1')) {
        error_log("Invalid usage of 'db_get_boolean($bool)'");
        die("Invalid usage of 'db_get_boolean($bool)'");
    }

    if (db_pgsql()) {
        // return either true or false (unquoted strings)
        if ($bool) {
            return 't';
        }
        return 'f';
    } elseif (Config::Read('database_type') == 'mysql' || Config::Read('database_type') == 'mysqli' || db_sqlite()) {
        if ($bool) {
            return 1;
        }
        return 0;
    } else {
        die('Unknown value in $CONF[database_type]');
    }
}

/**
 * Returns a query that reports the used quota ("x / y")
 * @param string column containing used quota
 * @param string column containing allowed quota
 * @param string column that will contain "x / y"
 * @return string
 */
function db_quota_text($count, $quota, $fieldname) {
    if (db_pgsql() || db_sqlite()) {
        // SQLite and PostgreSQL use || to concatenate strings
        return " CASE $quota
            WHEN '-1' THEN (coalesce($count,0) || ' / -')
            WHEN '0' THEN (coalesce($count,0) || ' / " . escape_string(html_entity_decode('&infin;')) . "')
            ELSE (coalesce($count,0) || ' / ' || $quota)
        END AS $fieldname";
    } else {
        return " CASE $quota
            WHEN '-1' THEN CONCAT(coalesce($count,0), ' / -')
            WHEN '0' THEN CONCAT(coalesce($count,0), ' / ', '" . escape_string(html_entity_decode('&infin;')) . "')
            ELSE CONCAT(coalesce($count,0), ' / ', $quota)
        END AS $fieldname";
    }
}

/**
 * Returns a query that reports the used quota ("x / y")
 * @param string column containing used quota
 * @param string column containing allowed quota
 * @param string column that will contain "x / y"
 * @return string
 */
function db_quota_percent($count, $quota, $fieldname) {
    return " CASE $quota
        WHEN '-1' THEN -1
        WHEN '0' THEN -1
        ELSE round(100 * coalesce($count,0) / $quota)
    END AS $fieldname";
}

/**
 * @return boolean true if it's a MySQL database variant.
 */
function db_mysql() {
    $type = Config::Read('database_type');

    if ($type == 'mysql' || $type == 'mysqli') {
        return true;
    }
    return false;
}

/**
 * returns true if PostgreSQL is used, false otherwise
 */
function db_pgsql() {
    if (Config::Read('database_type')=='pgsql') {
        return true;
    }
    return false;
}

/**
 * returns true if SQLite is used, false otherwise
 */
function db_sqlite() {
    if (Config::Read('database_type')=='sqlite') {
        return true;
    } else {
        return false;
    }
}

/**
 * @param string $query SQL to execute
 * @param int $ignore_errors (default 0 aka do not ignore errors)
 * @return array ['result' => resource, 'rows' => int ,'error' => string]
 */
function db_query($query, $ignore_errors = 0) {
    global $CONF;
    global $DEBUG_TEXT;
    $result = "";
    $number_rows = "";
    $link = db_connect();
    $error_text = "";
    if ($ignore_errors) {
        $DEBUG_TEXT = "";
    }

    if ($CONF['database_type'] == "mysql") {
        $result = @mysql_query($query, $link)
        or $error_text = "Invalid query: " . mysql_error($link);
    }
    if ($CONF['database_type'] == "mysqli") {
        $result = @mysqli_query($link, $query)
        or $error_text = "Invalid query: " . mysqli_error($link);
    }
    if (db_sqlite()) {
        $result = @$link->query($query)
        or $error_text = "Invalid query: " . $link->lastErrorMsg();
    }
    if (db_pgsql()) {
        $result = @pg_query($link, $query)
            or $error_text = "Invalid query: " . pg_last_error();
    }
    if ($error_text != "" && $ignore_errors == 0) {
        error_log($error_text);
        error_log("caused by query: $query");
        die("<p />DEBUG INFORMATION:<br />$error_text <p>Check your error_log for the failed query. $DEBUG_TEXT");
    }

    if ($error_text == "") {
        if (db_sqlite()) {
            if ($result->numColumns()) {
                // Query returned something
                $num_rows = 0;
                while (@$result->fetchArray(SQLITE3_ASSOC)) {
                    $num_rows++;
                }
                $result->reset();
                $number_rows = $num_rows;
            } else {
                // Query was UPDATE, DELETE or INSERT
                $number_rows = $link->changes();
            }
        } elseif (preg_match("/^SELECT/i", trim($query))) {
            // if $query was a SELECT statement check the number of rows with [database_type]_num_rows ().
            if ($CONF['database_type'] == "mysql") {
                $number_rows = mysql_num_rows($result);
            }
            if ($CONF['database_type'] == "mysqli") {
                $number_rows = mysqli_num_rows($result);
            }
            if (db_pgsql()) {
                $number_rows = pg_num_rows($result);
            }
        } else {
            // if $query was something else, UPDATE, DELETE or INSERT check the number of rows with
            // [database_type]_affected_rows ().
            if ($CONF['database_type'] == "mysql") {
                $number_rows = mysql_affected_rows($link);
            }
            if ($CONF['database_type'] == "mysqli") {
                $number_rows = mysqli_affected_rows($link);
            }
            if (db_pgsql()) {
                $number_rows = pg_affected_rows($result);
            }
        }
    }

    $return = array(
        "result" => $result,
        "rows" => $number_rows,
        "error" => $error_text
    );
    return $return;
}



// db_row
// Action: Returns a row from a table
// Call: db_row (int result)

function db_row($result) {
    global $CONF;
    $row = "";
    if ($CONF['database_type'] == "mysql") {
        $row = mysql_fetch_row($result);
    }
    if ($CONF['database_type'] == "mysqli") {
        $row = mysqli_fetch_row($result);
    }
    if (db_sqlite()) {
        $row = $result->fetchArray(SQLITE3_NUM);
    }
    if (db_pgsql()) {
        $row = pg_fetch_row($result);
    }
    return $row;
}


/**
 * Return array from a db resource (presumably not associative).
 * @param resource $result
 * @return array|null|string
 */
function db_array($result) {
    global $CONF;
    $row = "";
    if ($CONF['database_type'] == "mysql") {
        $row = mysql_fetch_array($result);
    }
    if ($CONF['database_type'] == "mysqli") {
        $row = mysqli_fetch_array($result);
    }
    if (db_sqlite()) {
        $row = $result->fetchArray();
    }
    if (db_pgsql()) {
        $row = pg_fetch_array($result);
    }
    return $row;
}


/**
 * Get an associative array from a DB query resource.
 *
 * @param resource $result
 * @return array|null|string
 */
function db_assoc($result) {
    global $CONF;
    $row = "";
    if ($CONF['database_type'] == "mysql") {
        $row = mysql_fetch_assoc($result);
    }
    if ($CONF['database_type'] == "mysqli") {
        $row = mysqli_fetch_assoc($result);
    }
    if (db_sqlite()) {
        $row = $result->fetchArray(SQLITE3_ASSOC);
    }
    if (db_pgsql()) {
        $row = pg_fetch_assoc($result);
    }
    return $row;
}


/**
 * Delete a row from the specified table.
 *
 * DELETE FROM $table WHERE $where = $delete $aditionalWhere
 *
 * @param string $table
 * @param string $where - should never be a user supplied value
 * @param string $delete
 * @param string $additionalwhere (default '').
 * @return int|mixed rows deleted.
 */
function db_delete($table, $where, $delete, $additionalwhere='') {
    $table = table_by_key($table);

    $query = "DELETE FROM $table WHERE $where ='" . escape_string($delete) . "' " . $additionalwhere;
    $result = db_query($query);

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
 *
 * @param string - table name
 * @param array  - key/value map of data to insert into the table.
 * @param array (optional) - array of fields to set to now() - default: array('created', 'modified')
 * @return int - number of inserted rows
 */
function db_insert($table, $values, $timestamp = array('created', 'modified')) {
    $table = table_by_key($table);

    foreach (array_keys($values) as $key) {
        $values[$key] = "'" . escape_string($values[$key]) . "'";
    }

    foreach ($timestamp as $key) {
        if (db_sqlite()) {
            $values[$key] = "datetime('now')";
        } else {
            $values[$key] = "now()";
        }
    }

    $sql_values = "(" . implode(",", escape_string(array_keys($values))).") VALUES (".implode(",", $values).")";

    $result = db_query("INSERT INTO $table $sql_values");
    return $result['rows'];
}


/**
 * db_update
 * Action: Updates a specified table
 * Call: db_update (string table, string where_col, string where_value, array values [, array timestamp])
 * @param string $table - table name
 * @param string $where_col - column of WHERE condition
 * @param string $where_value - value of WHERE condition
 * @param array $values - key/value map of data to insert into the table.
 * @param array $timestamp (optional) - array of fields to set to now() - default: array('modified')
 * @return int - number of updated rows
 */
function db_update($table, $where_col, $where_value, $values, $timestamp = array('modified')) {
    $where = $where_col . " = '" . escape_string($where_value) . "'";
    return db_update_q($table, $where, $values, $timestamp);
}

/**
 * db_update_q
 * Action: Updates a specified table
 * Call: db_update_q (string table, string where, array values [, array timestamp])
 * @param string $table - table name
 * @param string $where - WHERE condition (as SQL)
 * @param array $values - key/value map of data to insert into the table.
 * @param array $timestamp (optional) - array of fields to set to now() - default: array('modified')
 * @return int - number of updated rows
 */
function db_update_q($table, $where, $values, $timestamp = array('modified')) {
    $table = table_by_key($table);

    foreach ($values as $key => $value) {
        $sql_values[$key] = $key . "='" . escape_string($value) . "'";
    }

    foreach ($timestamp as $key) {
        if (db_sqlite()) {
            $sql_values[$key] = escape_string($key) . "=datetime('now')";
        } else {
            $sql_values[$key] = escape_string($key) . "=now()";
        }
    }

    $sql="UPDATE $table SET " . implode(",", $sql_values) . " WHERE $where";

    $result = db_query($sql);
    return $result['rows'];
}


/**
 * db_log
 * Action: Logs actions from admin
 * Call: db_log (string domain, string action, string data)
 * Possible actions are defined in $LANG["pViewlog_action_$action"]
 */
function db_log($domain, $action, $data) {
    if (!Config::bool('logging')) {
        return true;
    }

    $REMOTE_ADDR = getRemoteAddr();

    $username = authentication_get_username();

    if (Config::Lang("pViewlog_action_$action") == '') {
        die("Invalid log action : $action");   // could do with something better?
    }


    $logdata = array(
        'username'  => "$username ($REMOTE_ADDR)",
        'domain'    => $domain,
        'action'    => $action,
        'data'      => $data,
    );
    $result = db_insert('log', $logdata, array('timestamp'));
    if ($result != 1) {
        return false;
    } else {
        return true;
    }
}

/**
 * db_in_clause
 * Action: builds and returns the "field in(x, y)" clause for database queries
 * Call: db_in_clause (string field, array values)
 * @param string $field
 * @param array $values
 */
function db_in_clause($field, $values) {
    return " $field IN ('"
    . implode("','", escape_string(array_values($values)))
    . "') ";
}

/**
 * db_where_clause
 * Action: builds and returns a WHERE clause for database queries. All given conditions will be AND'ed.
 * Call: db_where_clause (array $conditions, array $struct)
 * @param array $condition - array('field' => 'value', 'field2' => 'value2, ...)
 * @param array $struct - field structure, used for automatic bool conversion
 * @param string $additional_raw_where - raw sniplet to include in the WHERE part - typically needs to start with AND
 * @param array $searchmode - operators to use (=, <, > etc.) - defaults to = if not specified for a field (see
 *                           $allowed_operators for available operators)
 *                           Note: the $searchmode operator will only be used if a $condition for that field is set.
 *                                 This also means you'll need to set a (dummy) condition for NULL and NOTNULL.
 */
function db_where_clause($condition, $struct, $additional_raw_where = '', $searchmode = array()) {
    if (!is_array($condition)) {
        die('db_where_cond: parameter $cond is not an array!');
    } elseif (!is_array($searchmode)) {
        die('db_where_cond: parameter $searchmode is not an array!');
    } elseif (count($condition) == 0 && trim($additional_raw_where) == '') {
        die("db_where_cond: parameter is an empty array!"); # die() might sound harsh, but can prevent information leaks
    } elseif (!is_array($struct)) {
        die('db_where_cond: parameter $struct is not an array!');
    }

    $allowed_operators = array('<', '>', '>=', '<=', '=', '!=', '<>', 'CONT', 'LIKE', 'NULL', 'NOTNULL');
    $where_parts = array();
    $having_parts = array();

    foreach ($condition as $field => $value) {
        if (isset($struct[$field]) && $struct[$field]['type'] == 'bool') {
            $value = db_get_boolean($value);
        }
        $operator = '=';
        if (isset($searchmode[$field])) {
            if (in_array($searchmode[$field], $allowed_operators)) {
                $operator = $searchmode[$field];

                if ($operator == 'CONT') { # CONT - as in "contains"
                    $operator = ' LIKE '; # add spaces
                    $value = '%' . $value . '%';
                } elseif ($operator == 'LIKE') { # LIKE -without adding % wildcards (the search value can contain %)
                    $operator = ' LIKE '; # add spaces
                }
            } else {
                die('db_where_clause: Invalid searchmode for ' . $field);
            }
        }

        if ($operator == "NULL") {
            $querypart = $field . ' IS NULL';
        } elseif ($operator == "NOTNULL") {
            $querypart = $field . ' IS NOT NULL';
        } else {
            $querypart = $field . $operator . "'" . escape_string($value) . "'";
        }

        if (!empty($struct[$field]['select'])) {
            $having_parts[$field] = $querypart;
        } else {
            $where_parts[$field] = $querypart;
        }
    }
    $query = ' WHERE 1=1 ';
    $query .= " $additional_raw_where ";
    if (count($where_parts)  > 0) {
        $query .= " AND    ( " . join(" AND ", $where_parts)  . " ) ";
    }
    if (count($having_parts) > 0) {
        $query .= " HAVING ( " . join(" AND ", $having_parts) . " ) ";
    }

    return $query;
}

/**
 * Convert a programmatic db table name into what may be the actual name.
 *
 * Takes into consideration any CONF database_prefix or database_tables map
 *
 * If it's a MySQL database, then we return the name with backticks around it (`).
 *
 * @param string database table name.
 * @return string - database table name with appropriate prefix (and quoting if MySQL)
 */
function table_by_key($table_key) {
    global $CONF;

    $table = $table_key;

    if (!empty($CONF['database_tables'][$table_key])) {
        $table = $CONF['database_tables'][$table_key];
    }

    $table = $CONF['database_prefix'] . $table;

    if (db_mysql()) {
        return "`" . $table . "`";
    }

    return $table;
}


/*
 * check if the database layout is up to date
 * returns the current 'version' value from the config table
 * if $error_out is True (default), die() with a message that recommends to run setup.php.
 */
function check_db_version($error_out = true) {
    global $min_db_version;

    $table = table_by_key('config');

    $sql = "SELECT value FROM $table WHERE name = 'version'";
    $r = db_query($sql);

    if ($r['rows'] == 1) {
        $row = db_assoc($r['result']);
        $dbversion = $row['value'];
    } else {
        $dbversion = 0;
        db_query("INSERT INTO $table (name, value) VALUES ('version', '0')", 0, '');
    }

    if (($dbversion < $min_db_version) && $error_out == true) {
        echo "ERROR: The PostfixAdmin database layout is outdated (you have r$dbversion, but r$min_db_version is expected).\nPlease run setup.php to upgrade the database.\n";
        exit(1);
    }

    return $dbversion;
}

//
// gen_show_status
// Action: Return a string of colored &nbsp;'s that indicate
//         the if an alias goto has an error or is sent to
//         addresses list in show_custom_domains
// Call: gen_show_status (string alias_address)
//
function gen_show_status($show_alias) {
    global $CONF;
    $table_alias = table_by_key('alias');
    $stat_string = "";

    $show_alias = escape_string($show_alias);

    $stat_goto = "";
    $stat_result = db_query("SELECT goto FROM $table_alias WHERE address='$show_alias'");
    if ($stat_result['rows'] > 0) {
        $row = db_row($stat_result['result']);
        $stat_goto = $row[0];
    }

    if (!empty($CONF['recipient_delimiter'])) {
        $delimiter = preg_quote($CONF['recipient_delimiter'], "/");
        $delimiter_regex = '/' .$delimiter. '[^' .$delimiter. '@]*@/';
    }

    // UNDELIVERABLE CHECK
    if ($CONF['show_undeliverable'] == 'YES') {
        $gotos=array();
        $gotos=explode(',', $stat_goto);
        $undel_string="";

        //make sure this alias goes somewhere known
        $stat_ok = 1;
        foreach ($gotos as $g) {
            if (!$stat_ok) {
                break;
            }
            if (strpos($g, '@') === false) {
                continue;
            }

            list($local_part, $stat_domain) = explode('@', $g);

            $stat_delimiter = "";
            if (!empty($CONF['recipient_delimiter'])) {
                $stat_delimiter = "OR address = '" . escape_string(preg_replace($delimiter_regex, "@", $g)) . "'";
            }
            $stat_result = db_query("SELECT address FROM $table_alias WHERE address = '" . escape_string($g) . "' OR address = '@" . escape_string($stat_domain) . "' $stat_delimiter");
            if ($stat_result['rows'] == 0) {
                $stat_ok = 0;
            }
            if ($stat_ok == 0) {
                if ($stat_domain == $CONF['vacation_domain'] || in_array($stat_domain, $CONF['show_undeliverable_exceptions'])) {
                    $stat_ok = 1;
                }
            }
        } // while
        if ($stat_ok == 0) {
            $stat_string .= "<span style='background-color:" . $CONF['show_undeliverable_color'] . "'>" . $CONF['show_status_text'] . "</span>&nbsp;";
        } else {
            $stat_string .= $CONF['show_status_text'] . "&nbsp;";
        }
    }

    // POP/IMAP CHECK
    if ($CONF['show_popimap'] == 'YES') {
        $stat_delimiter = "";
        if (!empty($CONF['recipient_delimiter'])) {
            $stat_delimiter = ',' . preg_replace($delimiter_regex, "@", $stat_goto);
        }

        //if the address passed in appears in its own goto field, its POP/IMAP
        # TODO: or not (might also be an alias loop) -> check mailbox table!
        if (preg_match('/,' . $show_alias . ',/', ',' . $stat_goto . $stat_delimiter . ',')) {
            $stat_string .= "<span  style='background-color:" . $CONF['show_popimap_color'] .
                "'>" . $CONF['show_status_text'] . "</span>&nbsp;";
        } else {
            $stat_string .= $CONF['show_status_text'] . "&nbsp;";
        }
    }

    // CUSTOM DESTINATION CHECK
    if (count($CONF['show_custom_domains']) > 0) {
        for ($i = 0; $i < sizeof($CONF['show_custom_domains']); $i++) {
            if (preg_match('/^.*' . $CONF['show_custom_domains'][$i] . '.*$/', $stat_goto)) {
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

/**
 * @return string
 */
function getRemoteAddr() {
    $REMOTE_ADDR = 'localhost';
    if (isset($_SERVER['REMOTE_ADDR'])) {
        $REMOTE_ADDR = $_SERVER['REMOTE_ADDR'];
    }

    return $REMOTE_ADDR;
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
