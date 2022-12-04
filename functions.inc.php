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
 * @license GNU GPL v2 or later.
 *
 * File: functions.inc.php
 * Contains re-usable code.
 */


$min_db_version = 1844;  # update (at least) before a release with the latest function numbrer in upgrade.php

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
 * @return string|bool admin or user or (boolean) false.
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
 * @param string $role role-name. (E.g. admin, global-admin or user)
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
 * @param string $role
 * @return bool
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

    $_SESSION['PFA_token'] = md5(random_bytes(8) . uniqid('pfa', true));

    return $status;
}

/**
 * Add an error message for display on the next page that is rendered.
 * @param string|array $string message(s) to show.
 *
 * Stores string in session. Flushed through header template.
 * @see _flash_string()
 * @return void
 */
function flash_error($string) {
    _flash_string('error', $string);
}

/**
 * Used to display an info message on successful update.
 * @param string|array $string message(s) to show.
 * Stores data in session.
 * @see _flash_string()
 * @return void
 */
function flash_info($string) {
    _flash_string('info', $string);
}
/**
 * 'Private' method used for flash_info() and flash_error().
 * @param string $type
 * @param array|string $string
 * @retrn void
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

/**
 * @param bool $use_post - set to 0 if $_POST should NOT be read
 * @return string e.g en
 * Try to figure out what language the user wants based on browser / cookie
 */
function check_language($use_post = true) {
    global $supported_languages; # from languages/languages.php

    // prefer a $_POST['lang'] if present
    if ($use_post && safepost('lang')) {
        $lang = safepost('lang');
        if (is_string($lang) && array_key_exists($lang, $supported_languages)) {
            return $lang;
        }
    }

    // Failing that, is there a $_COOKIE['lang'] ?
    if (safecookie('lang')) {
        $lang = safecookie('lang');
        if (is_string($lang) && array_key_exists($lang, $supported_languages)) {
            return $lang;
        }
    }

    $lang = Config::read_string('default_language');

    // If not, did the browser give us any hint(s)?
    if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $lang_array = preg_split('/(\s*,\s*)/', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        foreach ($lang_array as $value) {
            $lang_next = strtolower(trim($value));
            $lang_next = preg_replace('/;.*$/', '', $lang_next); # remove things like ";q=0.8"
            if (array_key_exists($lang_next, $supported_languages)) {
                return $lang_next;
            }
        }
    }
    return $lang;
}

/**
 * Action: returns a language selector dropdown with the browser (or cookie) language preselected
 * @return string
 *
 *
 */
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
 * @return string empty if the domain is valid, otherwise string with the errormessage
 *
 * @todo make check_domain able to handle as example .local domains
 * @todo skip DNS check if the domain exists in PostfixAdmin?
 */
function check_domain($domain) {
    if (!preg_match('/^([-0-9A-Z]+\.)+' . '([-0-9A-Z]){1,13}$/i', ($domain))) {
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
            } elseif (checkdnsrr($domain, 'NS')) {
                error_log("DNS is not correctly configured for $domain to send or receive email");
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
 * Get password expiration value for a domain
 * @param string $domain - a string that may be a domain
 * @return int password expiration value for this domain (DAYS, or zero if not enabled)
 */
function get_password_expiration_value($domain) {
    $table_domain = table_by_key('domain');
    $query = "SELECT password_expiry FROM $table_domain WHERE domain= :domain";

    $result = db_query_one($query, array('domain' => $domain));
    if (is_array($result) && isset($result['password_expiry'])) {
        return $result['password_expiry'];
    }
    return 0;
}

/**
 * check_email
 * Checks if an email is valid - if it is, return true, else false.
 * @todo make check_email able to handle already added domains
 * @param string $email - a string that may be an email address.
 * @return string empty if it's a valid email address, otherwise string with the errormessage
 */
function check_email($email) {
    $ce_email=$email;

    //strip the vacation domain out if we are using it
    //and change from blah#foo.com@autoreply.foo.com to blah@foo.com
    if (Config::bool('vacation')) {
        $vacation_domain = Config::read_string('vacation_domain');
        $ce_email = preg_replace("/@$vacation_domain\$/", '', $ce_email);
        $ce_email = preg_replace("/#/", '@', $ce_email);
    }

    // Perform non-domain-part sanity checks
    if (!preg_match('/^[-!#$%&\'*+\\.\/0-9=?A-Z^_{|}~]+' . '@' . '[^@]+$/i', $ce_email)) {
        return "" . Config::lang_f('pInvalidMailRegex', $email);
    }

    if (function_exists('filter_var')) {
        $check = filter_var($email, FILTER_VALIDATE_EMAIL);
        if (!$check) {
            return "" . Config::lang_f('pInvalidMailRegex', $email);
        }
    }
    // Determine domain name
    $matches = array();
    if (preg_match('|@(.+)$|', $ce_email, $matches)) {
        $domain=$matches[1];
        # check domain name
        return "" . check_domain($domain);
    }

    return "" . Config::lang_f('pInvalidMailRegex', $email);
}



/**
 * Clean a string, escaping any meta characters that could be
 * used to disrupt an SQL string. The method of the escaping is dependent on the underlying DB
 * and MAY NOT be just \' ing. (e.g. sqlite and PgSQL change "it's" to "it''s".
 *
 * The PDO quote function surrounds what you pass in with quote marks; for legacy reasons we remove these,
 * but assume the caller will actually add them back in (!).
 *
 * e.g. caller code looks like :
 *
 * <code>
 * $sql = "SELECT * FROM foo WHERE x = '" . escape_string('fish') . "'";
 * </code>
 *
 * @param int|string $string_or_int parameters to escape
 * @return string cleaned data, suitable for use within an SQL statement.
 */
function escape_string($string_or_int) {
    $link = db_connect();
    $string_or_int = (string) $string_or_int;
    $quoted = $link->quote($string_or_int);
    return trim($quoted, "'");
}


/**
 * safeget
 * Action: get value from $_GET[$param], or $default if $_GET[$param] is not set
 * Call: $param = safeget('param')   # replaces $param = $_GET['param']
 *       - or -
 *  $param = safeget('param', 'default')
 *
 * @param string $param parameter name.
 * @param string $default (optional) - default value if key is not set.
 * @return string
 */
function safeget($param, $default = "") {
    $retval = $default;
    if (isset($_GET[$param]) && is_string($_GET[$param])) {
        $retval = $_GET[$param];
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
function safepost($param, $default = "") {
    $retval = $default;
    if (isset($_POST[$param]) && is_string($_POST[$param])) {
        $retval = $_POST[$param];
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
function safeserver($param, $default = "") {
    $retval = $default;
    if (isset($_SERVER[$param])) {
        $retval = $_SERVER[$param];
    }

    if (!is_string($retval)) {
        throw new \InvalidArgumentException("\%_SERVER should only contain string value(s)");
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
function safecookie($param, $default = "") {
    $retval = $default;
    if (isset($_COOKIE[$param])) {
        $retval = $_COOKIE[$param];
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
function safesession($param, $default = "") {
    $retval = $default;
    if (isset($_SESSION[$param]) && is_string($_SESSION[$param])) {
        $retval = $_SESSION[$param];
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

/**
 * Action: Get all the properties of a domain.
 * @param string $domain
 * @return array
 */
function get_domain_properties($domain) {
    $handler = new DomainHandler();
    if (!$handler->init($domain)) {
        throw new Exception("Error: " . join("\n", $handler->errormsg));
    }

    if (!$handler->view()) {
        throw new Exception("Error: " . join("\n", $handler->errormsg));
    }

    $result = $handler->result();
    return $result;
}


/**
 * create_page_browser
 * Action: Get page browser for a long list of mailboxes, aliases etc.
 *
 * @param string $idxfield - database field name to use as title e.g. alias.address
 * @param string $querypart - core part of the query (starting at "FROM") e.g. FROM alias WHERE address like ...
 * @return array
 */
function create_page_browser($idxfield, $querypart, $sql_params = []) {
    global $CONF;
    $page_size = (int) $CONF['page_size'];
    $label_len = 2;
    $pagebrowser = array();

    $count_results = 0;

    if ($page_size < 2) { # will break the page browser
        throw new Exception('$CONF[\'page_size\'] must be 2 or more!');
    }

    # get number of rows
    $query = "SELECT count(*) as counter FROM (SELECT $idxfield $querypart) AS tmp";
    $result = db_query_one($query, $sql_params);
    if ($result && isset($result['counter'])) {
        $count_results = $result['counter'] -1; # we start counting at 0, not 1
    }

    if ($count_results < $page_size) {
        return array(); # only one page - no pagebrowser required
    }

    # init row counter
    $initcount = "SET @r=-1";
    if (db_pgsql()) {
        $initcount = "CREATE TEMPORARY SEQUENCE rowcount MINVALUE 0";
    }
    if (!db_sqlite()) {
        db_execute($initcount);
    }

    # get labels for relevant rows (first and last of each page)
    $page_size_zerobase = $page_size - 1;
    $query = "
        SELECT * FROM (
            SELECT $idxfield AS label, @r := @r + 1 AS 'r' $querypart
        ) idx WHERE MOD(idx.r, $page_size) IN (0,$page_size_zerobase) OR idx.r = $count_results
    ";

    if (db_pgsql()) {
        $query = "
            SELECT * FROM (
                SELECT $idxfield AS label, nextval('rowcount') AS r $querypart
            ) idx WHERE MOD(idx.r, $page_size) IN (0,$page_size_zerobase) OR idx.r = $count_results
        ";
    }

    if (db_sqlite()) {
        $end = $idxfield;
        if (strpos($idxfield, '.') !== false) {
            $bits = explode('.', $idxfield);
            $end = $bits[1];
        }
        $query = "
            WITH idx AS (SELECT * $querypart)
                SELECT $end AS label, (SELECT (COUNT(*) - 1) FROM idx t1 WHERE t1.$end <= t2.$end ) AS r
                FROM idx t2
                WHERE (r % $page_size) IN (0,$page_size_zerobase) OR r = $count_results";
    }

    # PostgreSQL:
    # http://www.postgresql.org/docs/8.1/static/sql-createsequence.html
    # http://www.postgresonline.com/journal/archives/79-Simulating-Row-Number-in-PostgreSQL-Pre-8.4.html
    # http://www.pg-forum.de/sql/1518-nummerierung-der-abfrageergebnisse.html
    # CREATE TEMPORARY SEQUENCE foo MINVALUE 0 MAXVALUE $page_size_zerobase CYCLE
    # afterwards: DROP SEQUENCE foo

    $result = db_query_all($query, $sql_params);
    for ($k = 0; $k < count($result); $k+=2) {
        if (isset($result[$k + 1])) {
            $label = substr($result[$k]['label'], 0, $label_len) . '-' . substr($result[$k+1]['label'], 0, $label_len);
        } else {
            $label = substr($result[$k]['label'], 0, $label_len);
        }
        $pagebrowser[] = $label;
    }

    if (db_pgsql()) {
        db_execute("DROP SEQUENCE rowcount");
    }

    return $pagebrowser;
}


/**
 * Recalculates the quota from MBs to bytes (divide, /)
 * @param int $quota
 * @return float
 */
function divide_quota($quota) {
    if ($quota == -1) {
        return $quota;
    }
    $value = round($quota / (int) Config::read_string('quota_multiplier'), 2);
    return $value;
}


/**
 * Checks if the admin is the owner of the domain (or global-admin)
 * @param string $username
 * @param string $domain
 * @return bool
 */
function check_owner($username, $domain) {
    $table_domain_admins = table_by_key('domain_admins');

    $result = db_query_all(
        "SELECT 1 FROM $table_domain_admins WHERE username= ? AND (domain = ? OR domain = 'ALL') AND active = ?" ,
        array($username, $domain, db_get_boolean(true))
    );

    if (sizeof($result) == 1 || sizeof($result) == 2) { # "ALL" + specific domain permissions is possible
        # TODO: if superadmin, check if given domain exists in the database
        return true;
    } else {
        if (sizeof($result) > 2) { # more than 2 results means something really strange happened...
            flash_error("Permission check returned multiple results. Please go to 'edit admin' for your username and press the save "
             . "button once to fix the database. If this doesn't help, open a bugreport.");
        }
        return false;
    }
}



/**
 * List domains for an admin user.
 * @param string $username
 * @return array of domain names.
 */
function list_domains_for_admin($username) {
    $table_domain = table_by_key('domain');
    $table_domain_admins = table_by_key('domain_admins');

    $condition = array();

    $E_username = escape_string($username);

    $query = "SELECT $table_domain.domain FROM $table_domain ";
    $condition[] = "$table_domain.domain != 'ALL'";

    $pvalues = array();

    $result = db_query_one("SELECT username FROM $table_domain_admins WHERE username= :username AND domain='ALL'", array('username' => $username));
    if (empty($result)) { # not a superadmin
        $pvalues['username'] = $username;
        $pvalues['active'] = db_get_boolean(true);
        $pvalues['backupmx'] = db_get_boolean(false);

        $query .= " LEFT JOIN $table_domain_admins ON $table_domain.domain=$table_domain_admins.domain ";
        $condition[] = "$table_domain_admins.username = :username  ";
        $condition[] = "$table_domain.active = :active "; # TODO: does it really make sense to exclude inactive...
        $condition[] = "$table_domain.backupmx = :backupmx" ; # TODO: ... and backupmx domains for non-superadmins?
    }

    $query .= " WHERE " . join(' AND ', $condition);
    $query .= " ORDER BY $table_domain.domain";

    $result = db_query_all($query, $pvalues);

    return array_column($result, 'domain');
}

/**
 * List all available domains.
 *
 * @return array
 */
function list_domains() {
    $list = array();

    $table_domain = table_by_key('domain');
    $result = db_query_all("SELECT domain FROM $table_domain WHERE domain!='ALL' ORDER BY domain");
    $i = 0;
    foreach ($result as $row) {
        $list[$i] = $row['domain'];
        $i++;
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
        switch ($string[$i]) {
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
                    $ret .= sprintf("=%02X", ord($string[$i]));
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
                $k = ord($string[$i]);
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
                            $ret .= $string[$i];
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
    $result = array();
    $val_conf = Config::read_array('password_validation');

    if (Config::has('min_password_length')) {
        $minlen = (int)Config::read_string('min_password_length'); # used up to 2.3.x - check it for backward compatibility
        if ($minlen > 0) {
            $val_conf['/.{' . $minlen . '}/'] = "password_too_short $minlen";
        }
    }

    foreach ($val_conf as $regex => $message) {
        if (is_callable($message)) {
            $ret = $message($password);
            if (!empty($ret)) {
                $result[] = $ret;
            }
            continue;
        }

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

/**
 * @param string $pw
 * @param string $pw_db - encrypted hash
 * @return string crypt'ed password, should equal $pw_db if $pw matches the original
 */
function _pacrypt_md5crypt($pw, $pw_db = '') {
    if ($pw_db) {
        $split_salt = preg_split('/\$/', $pw_db);
        if (isset($split_salt[2])) {
            $salt = $split_salt[2];
            return md5crypt($pw, $salt);
        }
    }

    return md5crypt($pw);
}

/**
 * @todo fix this to not throw an E_NOTICE or deprecate/remove.
 */
function _pacrypt_crypt($pw, $pw_db = '') {
    if ($pw_db) {
        return crypt($pw, $pw_db);
    }
    // PHP8 - we have to specify a salt here....
    $salt = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 2);

    return crypt($pw, $salt);
}

/**
 * Crypt with MySQL's ENCRYPT function
 *
 * @param string $pw
 * @param string $pw_db (hashed password)
 * @return string if $pw_db and the return value match then $pw matches the original password.
 */
function _pacrypt_mysql_encrypt($pw, $pw_db = '') {
    // See https://sourceforge.net/tracker/?func=detail&atid=937966&aid=1793352&group_id=191583
    // this is apparently useful for pam_mysql etc.

    if ( $pw_db ) {
        $res = db_query_one("SELECT ENCRYPT(:pw,:pw_db) as result", ['pw' => $pw, 'pw_db' => $pw_db]);
    } else {
        // see https://security.stackexchange.com/questions/150687/is-it-safe-to-use-the-encrypt-function-in-mysql-to-hash-passwords
        // if no existing password, use a random SHA512 salt.
        $salt = _php_crypt_generate_crypt_salt();
        $res= db_query_one("SELECT ENCRYPT(:pw, CONCAT('$6$', '$salt')) as result", ['pw' => $pw]);
    }

    return $res['result'];
}

/**
 * Create/Validate courier authlib style crypt'ed passwords. (md5, md5raw, crypt, sha1)
 *
 * @param string $pw
 * @param string $pw_db (optional)
 * @return string crypted password - contains {xxx} prefix to identify mechanism.
 */
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
        throw new Exception("authlib_default_flavor '" . $flavor . "' unknown. Valid flavors are 'md5raw', 'md5', 'SHA' and 'crypt'");
    }
    return $password;
}

/**
 * Uses the doveadm pw command, crypted passwords have a {...} prefix to identify type.
 *
 * @param string $pw - plain text password
 * @param string $pw_db - encrypted password, or '' for generation.
 * @return string crypted password
 */
function _pacrypt_dovecot($pw, $pw_db = '') {
    global $CONF;

    $split_method = preg_split('/:/', $CONF['encrypt']);
    $method       = strtoupper($split_method[1]);
    # If $pw_db starts with {method}, change $method accordingly
    if (!empty($pw_db) && preg_match('/^\{([A-Z0-9.-]+)\}.+/', $pw_db, $method_matches)) {
        $method = $method_matches[1];
    }
    if (! preg_match("/^[A-Z0-9.-]+$/", $method)) {
        throw new Exception("invalid dovecot encryption method");
    }

    # digest-md5 hashes include the username - until someone implements it, let's declare it as unsupported
    if (strtolower($method) == 'digest-md5') {
        throw new Exception("Sorry, \$CONF['encrypt'] = 'dovecot:digest-md5' is not supported by PostfixAdmin.");
    }
    # TODO: add -u option for those hashes, or for everything that is salted (-u was available before dovecot 2.1 -> no problem with backward compatibility )

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

    $pipes = [];

    $pipe = proc_open("$dovecotpw '-s' $method$dovepasstest", $spec, $pipes);

    if (!$pipe) {
        throw new Exception("can't proc_open $dovecotpw");
    }

    // use dovecot's stdin, it uses getpass() twice (except when using -t)
    // Write pass in pipe stdin
    if (empty($dovepasstest)) {
        fwrite($pipes[0], $pw . "\n", 1+strlen($pw));
        usleep(1000);
    }

    fwrite($pipes[0], $pw . "\n", 1+strlen($pw));
    fclose($pipes[0]);

    $stderr_output = stream_get_contents($pipes[2]);

    // Read hash from pipe stdout
    $password = fread($pipes[1], 200);

    if (!empty($stderr_output) || empty($password)) {
        error_log("Failed to read password from $dovecotpw ... stderr: $stderr_output, password: $password ");
        throw new Exception("$dovecotpw failed, see error log for details");
    }

    if (empty($dovepasstest)) {
        if (!preg_match('/^\{' . $method . '\}/', $password)) {
            error_log("dovecotpw password encryption failed (method: $method) . stderr: $stderr_output");
            throw new Exception("can't encrypt password with dovecotpw, see error log for details");
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
 * Supports DES, MD5, BLOWFISH, SHA256, SHA512 methods.
 *
 * Via config we support an optional prefix (e.g. if you need hashes to start with {SHA256-CRYPT} and optional rounds (hardness) setting.
 *
 * @param string $pw
 * @param string $pw_db (can be empty if setting a new password)
 * @return string crypt'ed password; if it matches $pw_db then $pw is the original password.
 */
function _pacrypt_php_crypt($pw, $pw_db) {
    $configEncrypt = Config::read_string('encrypt');

    // use PHPs crypt(), which uses the system's crypt()
    // same algorithms as used in /etc/shadow
    // you can have mixed hash types in the database for authentication, changed passwords get specified hash type
    // the algorithm for a new hash is chosen by feeding a salt with correct magic to crypt()
    // set $CONF['encrypt'] to 'php_crypt' to use the default SHA512 crypt method
    // set $CONF['encrypt'] to 'php_crypt:METHOD' to use another method; methods supported: DES, MD5, BLOWFISH, SHA256, SHA512
    // set $CONF['encrypt'] to 'php_crypt:METHOD:difficulty' where difficulty is between 1000-999999999
    // set $CONF['encrypt'] to 'php_crypt:METHOD:difficulty:PREFIX' to prefix the hash with the {PREFIX} etc.
    // tested on linux

    $prefix = '';

    if (strlen($pw_db) > 0) {
        // existing pw provided. send entire password hash as salt for crypt() to figure out
        $salt = $pw_db;

        // if there was a prefix in the password, use this (override anything given in the config).

        if (preg_match('/^\{([-A-Z0-9]+)\}(.+)$/', $pw_db, $method_matches)) {
            $salt = $method_matches[2];
            $prefix = "{" . $method_matches[1] . "}";
        }
    } else {
        $salt_method = 'SHA512'; // hopefully a reasonable default (better than MD5)
        $hash_difficulty = '';
        // no pw provided. create new password hash
        if (strpos($configEncrypt, ':') !== false) {
            // use specified hash method
            $spec = explode(':', $configEncrypt);
            $salt_method = $spec[1];
            if (isset($spec[2])) {
                $hash_difficulty = $spec[2];
            }
            if (isset($spec[3])) {
                $prefix = $spec[3]; // hopefully something like {SHA256-CRYPT}
            }
        }
        // create appropriate salt for selected hash method
        $salt = _php_crypt_generate_crypt_salt($salt_method, $hash_difficulty);
    }

    $password = crypt($pw, $salt);

    return "{$prefix}{$password}";
}


/**
 * @param string $hash_type must be one of: MD5, DES, BLOWFISH, SHA256 or SHA512  (default)
 * @param int hash difficulty
 * @return string
 */
function _php_crypt_generate_crypt_salt($hash_type='SHA512', $hash_difficulty=null) {
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
        if (empty($hash_difficulty)) {
            $cost = 10;
        } else {
            $cost = (int)$hash_difficulty;
            if ($cost < 4 || $cost > 31) {
                throw new Exception('invalid encrypt difficulty setting "' . $hash_difficulty . '" for ' . $hash_type . ', the valid range is 4-31');
            }
        }
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
        if (empty($hash_difficulty)) {
            $rounds = '';
        } else {
            $rounds = (int)$hash_difficulty;
            if ($rounds < 1000 || $rounds > 999999999) {
                throw new Exception('invalid encrypt difficulty setting "' . $hash_difficulty . '" for ' . $hash_type . ', the valid range is 1000-999999999');
            }
        }
        $salt = _php_crypt_random_string($alphabet, $length);
        if (!empty($rounds)) {
            $rounds = sprintf('rounds=%d$', $rounds);
        }
        return sprintf('$%s$%s%s', $algorithm, $rounds, $salt);

    case 'SHA512':
        $length = 16;
        $algorithm = '6';
        if (empty($hash_difficulty)) {
            $rounds = '';
        } else {
            $rounds = (int)$hash_difficulty;
            if ($rounds < 1000 || $rounds > 999999999) {
                throw new Exception('invalid encrypt difficulty setting "' . $hash_difficulty . '" for ' . $hash_type . ', the valid range is 1000-999999999');
            }
        }
        $salt = _php_crypt_random_string($alphabet, $length);
        if (!empty($rounds)) {
            $rounds = sprintf('rounds=%d$', $rounds);
        }
        return sprintf('$%s$%s%s', $algorithm, $rounds, $salt);

    default:
        throw new Exception("unknown hash type: '$hash_type'");
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
 *
 * When wanting to compare one pw to another, it's necessary to provide the salt used - hence
 * the second parameter ($pw_db), which is the existing hash from the DB.
 *
 * @param string $pw
 * @param string $pw_db optional encrypted password
 * @return string encrypted password - if this matches $pw_db then the original password is $pw.
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
        case 'sha512.b64':
            return _pacrypt_sha512_b64($pw, $pw_db);
    }

    if (preg_match("/^dovecot:/", $CONF['encrypt'])) {
        return _pacrypt_dovecot($pw, $pw_db);
    }

    if (substr($CONF['encrypt'], 0, 9) === 'php_crypt') {
        return _pacrypt_php_crypt($pw, $pw_db);
    }

    throw new Exception('unknown/invalid $CONF["encrypt"] setting: ' . $CONF['encrypt']);
}

/**
 * @see https://github.com/postfixadmin/postfixadmin/issues/58
 */
function _pacrypt_sha512_b64($pw, $pw_db="") {
    if (!function_exists('random_bytes') || !function_exists('crypt') || !defined('CRYPT_SHA512') || !function_exists('mb_substr')) {
        throw new Exception("sha512.b64 not supported!");
    }
    if (!$pw_db) {
        $salt = mb_substr(rtrim(base64_encode(random_bytes(16)),'='),0,16,'8bit');
        return '{SHA512-CRYPT.B64}'.base64_encode(crypt($pw,'$6$'.$salt));
    }


    $password="#Thepasswordcannotbeverified";
    if (strncmp($pw_db,'{SHA512-CRYPT.B64}',18)==0) {
        $dcpwd = base64_decode(mb_substr($pw_db,18,null,'8bit'),true);
        if ($dcpwd !== false && !empty($dcpwd) && strncmp($dcpwd,'$6$',3)==0) {
            $password = '{SHA512-CRYPT.B64}'.base64_encode(crypt($pw,$dcpwd));
        }
    } elseif (strncmp($pw_db,'{MD5-CRYPT}',11)==0) {
        $dcpwd = mb_substr($pw_db,11,null,'8bit');
        if (!empty($dcpwd) && strncmp($dcpwd,'$1$',3)==0) {
            $password = '{MD5-CRYPT}'.crypt($pw,$dcpwd);
        }
    }
    return $password;
}

/**
 * Creates MD5 based crypt formatted password.
 * If salt is not provided we generate one.
 *
 * @param string $pw plain text password
 * @param string $salt (optional)
 * @param string $magic (optional)
 * @return string hashed password in crypt format.
 */
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

/**
 * @return string - should be random, 8 chars long
 */
function create_salt() {
    srand((int) microtime()*1000000);
    $salt = substr(md5("" . rand(0, 9999999)), 0, 8);
    return $salt;
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
 * @param string $to
 * @param string $from
 * @param string $subject  (if called with 4 parameters) or full mail body (if called with 3 parameters)
 * @param string $password (optional) - Password
 * @param string $body (optional, but recommended) - mail body
 * @return bool - true on success, otherwise false
 * TODO: Replace this with something decent like PEAR::Mail or Zend_Mail.
 */
function smtp_mail($to, $from, $data, $password = "", $body = "") {
    global $CONF;

    $smtpd_server = $CONF['smtp_server'];
    $smtpd_port = $CONF['smtp_port'];

    $smtp_server = php_uname('n');
    if (!empty($CONF['smtp_client'])) {
        $smtp_server = $CONF['smtp_client'];
    }
    $errno = 0;
    $errstr = "0";
    $timeout = 30;

    if ($body != "") {
        $maildata =
            "To: " . $to . "\n"
            . "From: " . $from . "\n"
            . "Subject: " . encode_header($data) . "\n"
            . "MIME-Version: 1.0\n"
            . "Date: " . date('r') . "\n"
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
        smtp_get_response($fh);

        if (Config::bool('smtp_sendmail_tls')) {
            fputs($fh, "STARTTLS\r\n");
            smtp_get_response($fh);

            stream_set_blocking($fh, true);
            stream_socket_enable_crypto($fh, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
            stream_set_blocking($fh, true);
        }

        fputs($fh, "EHLO $smtp_server\r\n");
        smtp_get_response($fh);

        if (!empty($password)) {
            fputs($fh,"AUTH LOGIN\r\n");
            smtp_get_response($fh);
            fputs($fh, base64_encode($from) . "\r\n");
            smtp_get_response($fh);
            fputs($fh, base64_encode($password) . "\r\n");
            smtp_get_response($fh);
        }

        fputs($fh, "MAIL FROM:<$from>\r\n");
        smtp_get_response($fh);
        fputs($fh, "RCPT TO:<$to>\r\n");
        smtp_get_response($fh);
        fputs($fh, "DATA\r\n");
        smtp_get_response($fh);
        fputs($fh, "$maildata\r\n.\r\n");
        smtp_get_response($fh);
        fputs($fh, "QUIT\r\n");
        smtp_get_response($fh);
        fclose($fh);
    }
    return true;
}

/**
 * smtp_get_admin_email
 * Action: Get configured email address or current user if nothing configured
 * Call: smtp_get_admin_email
 * @return string - username/mail address
 */
function smtp_get_admin_email() {
    $admin_email = Config::read_string('admin_email');
    if (!empty($admin_email)) {
        return $admin_email;
    } else {
        return authentication_get_username();
    }
}

/**
 * smtp_get_admin_password
 * Action: Get smtp password for admin email
 * Call: smtp_get_admin_password
 * @return string - admin smtp password
 */
function smtp_get_admin_password() {
    return Config::read_string('admin_smtp_password');
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



$DEBUG_TEXT = <<<EOF
    <p>Please check the documentation and website for more information.</p>
    <ul>
        <li><a href="http://postfixadmin.sf.net">PostfixAdmin - Project website</a></li>
        <li><a href='https://sourceforge.net/p/postfixadmin/discussion/676076'>Forums</a></li>
    </ul>
EOF;


/**
 * @return string - PDO DSN for PHP.
 * @throws Exception
 */
function db_connection_string() {
    global $CONF;
    $dsn = null;
    if (db_mysql()) {
        $socket = false;
        if (Config::has('database_socket')) {
            $socket = Config::read_string('database_socket');
        }

        $database_name = Config::read_string('database_name');

        $dsn = 'mysql:';
        if ($socket) {
            $dsn .= "unix_socket={$socket}";
        } else {
            $dsn .= "host={$CONF['database_host']}";
        }

        if (isset($CONF['database_port'])) {
            $dsn .= ";port={$CONF['database_port']}";
        }

        $dsn .= ";dbname={$database_name};charset=UTF8";
    } elseif (db_sqlite()) {
        $db = $CONF['database_name'];

        $dsn = "sqlite:{$db}";
    } elseif (db_pgsql()) {
        $dsn = "pgsql:dbname={$CONF['database_name']}";
        if (isset($CONF['database_host'])) {
            $dsn .= ";host={$CONF['database_host']}";
        }
        if (isset($CONF['database_port'])) {
            $dsn .= ";port={$CONF['database_port']}";
        }
        $dsn .= ";options='-c client_encoding=utf8'";
    } else {
        throw new Exception("<p style='color: red'>FATAL Error:<br />Invalid \$CONF['database_type'] <br/>'pgsql', 'mysql' or 'sqlite' supported. <br/> Please fix your config.inc.php!</p>");
    }

    return $dsn;
}

/**
 * db_connect
 * Action: Makes a connection to the database if it doesn't exist
 * Call: db_connect ()
 *
 * Return value:
 *
 * @return \PDO
 */
function db_connect() {
    global $CONF;

    /* some attempt at not reopening an existing connection */
    static $link;
    if (isset($link) && $link) {
        return $link;
    }

    $link = false;

    // throws.
    $dsn = db_connection_string();

    $options = array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    );
    $username_password = true;

    $queries = array();


    if (db_mysql()) {
        if (Config::bool('database_use_ssl')) {
            $options[PDO::MYSQL_ATTR_SSL_KEY] = Config::read_string('database_ssl_key');
            $options[PDO::MYSQL_ATTR_SSL_CA] = Config::read_string('database_ssl_ca');
            $options[PDO::MYSQL_ATTR_SSL_CAPATH] = Config::read_string('database_ssl_ca_path');
            $options[PDO::MYSQL_ATTR_SSL_CERT] = Config::read_string('database_ssl_cert');
            $options[PDO::MYSQL_ATTR_SSL_CIPHER] = Config::read_string('database_ssl_cipher');
            $options = array_filter($options); // remove empty settings.

            $verify = Config::read('database_ssl_verify_server_cert');
            if ($verify === null) { // undefined
                $verify = true;
            }

            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = (bool)$verify;
        }
        $queries[] = 'SET CHARACTER SET utf8';
        $queries[] = "SET COLLATION_CONNECTION='utf8_general_ci'";
    } elseif (db_sqlite()) {
        $db = $CONF['database_name'];

        if (!file_exists($db)) {
            $error_text = 'SQLite database missing: ' . $db;
            throw new Exception($error_text);
        }

        if (!is_writeable($db)) {
            $error_text = 'SQLite database not writeable: ' . $db;
            throw new Exception($error_text);
        }

        if (!is_writeable(dirname($db))) {
            $error_text = 'The directory the SQLite database is in is not writeable: ' . dirname($db);
            throw new Exception($error_text);
        }

        $username_password = false;
    } elseif (db_pgsql()) {
        // nothing to do.
    } else {
        throw new Exception("<p style='color: red'>FATAL Error:<br />Invalid \$CONF['database_type']! Please fix your config.inc.php!</p>");
    }

    if ($username_password) {
        $link = new PDO($dsn, Config::read_string('database_user'), Config::read_string('database_password'), $options);
    } else {
        $link = new PDO($dsn, null, null, $options);
    }

    if (!empty($queries)) {
        foreach ($queries as $q) {
            $link->exec($q);
        }
    }

    return $link;
}

/**
 * Returns the appropriate boolean value for the database.
 *
 * @param bool|string $bool
 * @return string|int as appropriate for underlying db platform
 */
function db_get_boolean($bool) {
    if (! (is_bool($bool) || $bool == '0' || $bool == '1')) {
        error_log("Invalid usage of 'db_get_boolean($bool)'");
        throw new Exception("Invalid usage of 'db_get_boolean($bool)'");
    }

    if (db_pgsql()) {
        // return either true or false (unquoted strings)
        if ($bool) {
            return 't';
        }
        return 'f';
    } elseif (db_mysql() || db_sqlite()) {
        if ($bool) {
            return 1;
        }
        return 0;
    } else {
        throw new Exception('Unknown value in $CONF[database_type]');
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
 * @return bool true if PostgreSQL is used, false otherwise
 */
function db_pgsql() {
    return Config::read_string('database_type') == 'pgsql';
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
 * @param string $sql
 * @param array $values
 * @return array
 */
function db_query_all($sql, array $values = []) {
    $r = db_query($sql, $values);
    return $r['result']->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * @param string $sql
 * @param array $values
 * @return array
 */
function db_query_one($sql, array $values = []) {
    $r = db_query($sql, $values);
    return $r['result']->fetch(PDO::FETCH_ASSOC);
}


/**
 * @param string $sql e.g. UPDATE foo SET bar = :baz
 * @param array $values - parameters for the prepared statement e.g. ['baz' => 1234]
 * @param bool $throw_exceptions
 * @return int number of rows affected by the query
 */
function db_execute($sql, array $values = [], $throw_exceptions = false) {
    $link = db_connect();

    try {
        $stmt = $link->prepare($sql);
        $stmt->execute($values);
    } catch (PDOException $e) {
        $error_text = "Invalid query: " . $e->getMessage() .  " caused by " . $sql ;
        error_log($error_text);
        if ($throw_exceptions) {
            throw $e;
        }

        return 0;
    }

    return $stmt->rowCount();
}

/**
 * @param string $sql
 * @param array $values
 * @param bool $ignore_errors - set to true to ignore errors.
 * @return array e.g. ['result' => PDOStatement, 'error' => string ]
 */
function db_query($sql, array $values = array(), $ignore_errors = false) {
    $link = db_connect();
    $error_text = '';

    $stmt = null;
    try {
        $stmt = $link->prepare($sql);
        $stmt->execute($values);
    } catch (PDOException $e) {
        $error_text = "Invalid query: " . $e->getMessage() .  " caused by " . $sql ;
        error_log($error_text);
        if (defined('PHPUNIT_TEST')) {
            throw new Exception("SQL query failed: {{{$sql}}} with " . json_encode($values) . ". Error message: " . $e->getMessage());
        }
        if (!$ignore_errors) {
            throw new Exception("DEBUG INFORMATION: " . $e->getMessage() . "<br/> Check your error_log for the failed query");
        }
    }

    return array(
        "result" => $stmt,
        "error" => $error_text,
    );
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

    $query = "DELETE FROM $table WHERE $where = ? $additionalwhere";

    return db_execute($query, array($delete));
}



/**
 * db_insert
 * Action: Inserts a row from a specified table
 * Call: db_insert (string table, array values [, array timestamp])
 *
 * @param string - table name
 * @param array $values - key/value map of data to insert into the table.
 * @param array $timestamp (optional) - array of fields to set to now() - default: array('created', 'modified')
 * @param boolean $throw_exceptions
 * @return int - number of inserted rows
 */
function db_insert($table, array $values, $timestamp = array('created', 'modified'), $throw_exceptions = false) {
    $table = table_by_key($table);

    foreach ($timestamp as $key) {
        if (db_sqlite()) {
            $values[$key] = "datetime('now')";
        } else {
            $values[$key] = "now()";
        }
    }

    $value_string = '';
    $comma = '';
    $prepared_statment_values = $values;

    foreach ($values as $field => $value) {
        if (in_array($field, $timestamp)) {
            $value_string .= $comma . $value; // see above.
            unset($prepared_statment_values[$field]);
        } else {
            $value_string .= $comma . ":{$field}";
        }
        $comma = ',';
    }


    return db_execute(
        "INSERT INTO $table (" . implode(",", array_keys($values)) .") VALUES ($value_string)",
        $prepared_statment_values,
        $throw_exceptions);
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
function db_update(string $table, string $where_col, string $where_value, array $values, array $timestamp = array('modified'), bool $throw_exceptions = false): int {
    $table_key = table_by_key($table);

    $pvalues = array();

    $set = array();

    foreach ($timestamp as $k) {
        if (!isset($values[$k])) {
            $values[$k] = 'x'; // timestamp field not in the values list, add it in so we set it to now() see #469
        }
    }

    foreach ($values as $key => $value) {
        if (in_array($key, $timestamp)) {
            if (db_sqlite()) {
                $set[] = " $key = datetime('now') ";
            } else {
                $set[] = " $key = now() ";
            }
            continue;
        }

        $set[] = " $key = :$key ";
        $pvalues[$key] = $value;
    }

    $pvalues['where'] = $where_value;


    $sql="UPDATE $table_key SET " . implode(",", $set) . " WHERE $where_col = :where";

    return db_execute($sql, $pvalues, $throw_exceptions);
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
        throw new Exception("Invalid log action : $action");   // could do with something better?
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
 * @return string
 */
function db_in_clause($field, array $values) {
    $v = array_map('escape_string', array_values($values));
    return " $field IN ('" . implode("','", $v) . "') ";
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
function db_where_clause(array $condition, array $struct, $additional_raw_where = '', array $searchmode = array()) {
    if (count($condition) == 0 && trim($additional_raw_where) == '') {
        throw new Exception("db_where_cond: parameter is an empty array!");
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
                throw new Exception('db_where_clause: Invalid searchmode for ' . $field);
            }
        }

        if ($operator == "NULL") {
            $querypart = $field . ' IS NULL';
        } elseif ($operator == "NOTNULL") {
            $querypart = $field . ' IS NOT NULL';
        } else {
            $querypart = $field . $operator . "'" . escape_string($value) . "'";

            // might need other types adding here.
            if (db_pgsql() && isset($struct[$field]) && in_array($struct[$field]['type'], array('ts', 'num')) && $value === '') {
                $querypart = $field . $operator . " NULL";
            }
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


/**
 * check if the database layout is up to date
 * returns the current 'version' value from the config table
 * if $error_out is True (default), exit(1) with a message that recommends to run setup.php.
 * @param bool $error_out
 * @return int
 */
function check_db_version($error_out = true) {
    global $min_db_version;

    $table = table_by_key('config');

    $sql = "SELECT value FROM $table WHERE name = 'version'";
    $row = db_query_one($sql);
    if (isset($row['value'])) {
        $dbversion = (int) $row['value'];
    } else {
        db_execute("INSERT INTO $table (name, value) VALUES ('version', '0')");
        $dbversion = 0;
    }

    if (($dbversion < $min_db_version) && $error_out == true) {
        echo "ERROR: The PostfixAdmin database layout is outdated (you have r$dbversion, but r$min_db_version is expected).\nPlease run setup.php to upgrade the database.\n";
        exit(1);
    }

    return $dbversion;
}


/**
 *
 * Action: Return a string of colored &nbsp;'s that indicate
 *        the if an alias goto has an error or is sent to
 *        addresses list in show_custom_domains
 *
 * @param string $show_alias
 * @return string
 */
function gen_show_status($show_alias) {
    global $CONF;
    $table_alias = table_by_key('alias');
    $stat_string = "";

    $stat_goto = "";
    $stat_result = db_query_one("SELECT goto FROM $table_alias WHERE address=?", array($show_alias));

    if ($stat_result) {
        $stat_goto = $stat_result['goto'];
    }

    $delimiter_regex = null;

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

            $v = array();

            $stat_delimiter = "";

            $sql = "SELECT address FROM $table_alias WHERE address = ? OR address = ?";
            $v[] = $g;
            $v[] = '@' . $stat_domain;

            if (!empty($CONF['recipient_delimiter']) && isset($delimiter_regex)) {
                $v[] = preg_replace($delimiter_regex, "@", $g);
                $sql .= " OR address = ? ";
            }

            $stat_result = db_query_one($sql, $v);

            if (empty($stat_result)) {
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

    // Vacation CHECK
    if ( array_key_exists('show_vacation', $CONF) && $CONF['show_vacation'] == 'YES' ) {
        $stat_result = db_query_one("SELECT * FROM ". table_by_key('vacation') ." WHERE email = ? AND active = ? ", array($show_alias, db_get_boolean(true) )) ;
        if (!empty($stat_result)) {
            $stat_string .= "<span style='background-color:" . $CONF['show_vacation_color'] . "'>" . $CONF['show_status_text'] . "</span>&nbsp;";
        } else {
            $stat_string .= $CONF['show_status_text'] . "&nbsp;";
        }
    }

    // Disabled CHECK
    if ( array_key_exists('show_disabled', $CONF) &&  $CONF['show_disabled'] == 'YES' ) {
        $stat_result = db_query_one(
            "SELECT * FROM ". table_by_key('mailbox') ." WHERE username = ? AND active = ?",
            array($show_alias, db_get_boolean(false))
        );
        if (!empty($stat_result)) {
            $stat_string .= "<span style='background-color:" . $CONF['show_disabled_color'] . "'>" . $CONF['show_status_text'] . "</span>&nbsp;";
        } else {
            $stat_string .= $CONF['show_status_text'] . "&nbsp;";
        }
    }

    // Expired CHECK
    if (Config::has('password_expiration') && Config::bool('password_expiration') && Config::bool('show_expired')) {
        $now = 'now()';
        if (db_sqlite()) {
            $now = "datetime('now')";
        }

        $stat_result = db_query_one("SELECT * FROM " . table_by_key('mailbox') . " WHERE username = ? AND password_expiry <= $now AND active = ?", array($show_alias, db_get_boolean(true)));

        if (!empty($stat_result)) {
            $stat_string .= "<span style='background-color:" . $CONF['show_expired_color'] . "'>" . $CONF['show_status_text'] . "</span>&nbsp;";
        } else {
            $stat_string .= $CONF['show_status_text'] . "&nbsp;";
        }
    }

    // POP/IMAP CHECK
    if ($CONF['show_popimap'] == 'YES') {
        $stat_delimiter = "";
        if (!empty($CONF['recipient_delimiter']) && isset($delimiter_regex)) {
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
        $stat_string .= "&nbsp;";
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
