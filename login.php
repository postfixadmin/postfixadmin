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
 * File: login.php
 * Authenticates a user, and populates their $_SESSION as appropriate.
 * Template File: login.php
 *
 * Template Variables:
 *
 *  tMessage
 *  tUsername
 *
 * Form POST \ GET Variables:
 *
 *  fUsername
 *  fPassword
 *  lang
 */

require_once('common.php');

# force user to delete setup.php (allows creation of superadmins!)
if (file_exists (realpath ("./setup.php"))) {
   if (is_string($CONF['configured']) && $CONF['configured'] == 'I_know_the_risk_of_not_deleting_setup.php')
   {
   }
   else
   {
      print "Please delete setup.php before using Postfix Admin!";
      exit;
   }
}

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
    include ("./templates/header.php");
    include ("./templates/login.php");
    include ("./templates/footer.php");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    $fUsername = '';
    $fPassword = '';
    if (isset ($_POST['fUsername'])) $fUsername = escape_string ($_POST['fUsername']);
    if (isset ($_POST['fPassword'])) $fPassword = escape_string ($_POST['fPassword']);
	$lang = safepost('lang');

   if ( $lang != check_language(0) ) { # only set cookie if language selection was changed
		setcookie('lang', $lang, time() + 60*60*24*30); # language cookie, lifetime 30 days
		# (language preference cookie is processed even if username and/or password are invalid)
	}

    $result = db_query ("SELECT password FROM $table_admin WHERE username='$fUsername' AND active='1'");
    if ($result['rows'] == 1)
    {
        $row = db_array ($result['result']);
        $password = pacrypt ($fPassword, $row['password']);
        $result = db_query ("SELECT * FROM $table_admin WHERE username='$fUsername' AND password='$password' AND active='1'");
        if ($result['rows'] != 1)
        {
            $error = 1;
            $tMessage = $PALANG['pLogin_failed'];
            $tUsername = $fUsername;
        }
    }
    else
    {
        $error = 1;
        $tMessage = $PALANG['pLogin_failed'];
    }

    if ($error != 1)
    {
        session_regenerate_id();
        $_SESSION['sessid'] = array();
        $_SESSION['sessid']['username'] = $fUsername;
        $_SESSION['sessid']['roles'] = array();
        $_SESSION['sessid']['roles'][] = 'admin';

        // they've logged in, so see if they are a domain admin, as well.
        $result = db_query ("SELECT * FROM $table_domain_admins WHERE username='$fUsername' AND domain='ALL' AND active='1'");
        if ($result['rows'] == 1)
        {
            $_SESSION['sessid']['roles'][] = 'global-admin';
#            header("Location: admin/list-admin.php");
#            exit(0);
        }
        header("Location: main.php");
        exit(0);
    }

    include ("./templates/header.php");
    include ("./templates/login.php");
    include ("./templates/footer.php");
}
?>
