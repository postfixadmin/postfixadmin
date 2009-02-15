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
 * File: edit-alias.php
 * Users can use this to set forwards etc for their mailbox.
 *
 * Template File: users_edit-alias.php
 *
 * Template Variables:
 *
 * tMessage
 * tGoto
 *
 * Form POST \ GET Variables:
 *
 * fAddress
 * fDomain
 * fGoto
 */

require_once('../common.php');

authentication_require_role('user');
$USERID_USERNAME = authentication_get_username();

$tmp = preg_split ('/@/', $USERID_USERNAME);
$USERID_DOMAIN = $tmp[1];

$vacation_domain = $CONF['vacation_domain'];
$vacation_goto = preg_replace('/@/', '#', $USERID_USERNAME) . '@' . $vacation_domain;

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
    $vacation_domain = $CONF['vacation_domain'];

    $result = db_query ("SELECT * FROM $table_alias WHERE address='$USERID_USERNAME'");
    if ($result['rows'] == 1)
    {
        $row = db_array ($result['result']);
        $tGoto = $row['goto'];
    }
    else
    {
        $tMessage = $PALANG['pEdit_alias_address_error'];
    }

    include ("../templates/header.php");
    include ("../templates/users_menu.php");
    include ("../templates/users_edit-alias.php");
    include ("../templates/footer.php");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    // user clicked on cancel button
    if(isset($_POST['fCancel'])) {
        header("Location: main.php");
        exit(0);
    }

    $pEdit_alias_goto = $PALANG['pEdit_alias_goto'];

    if (isset ($_POST['fVacation'])) $fVacation = $_POST['fVacation'];   
    if (isset ($_POST['fGoto'])) $fGoto = escape_string (trim($_POST['fGoto']));
    if (isset ($_POST['fForward_and_store'])) $fForward_and_store = escape_string ($_POST['fForward_and_store']);

    $goto = strtolower ($fGoto);
    $goto = preg_replace ('/\\\r\\\n/', ',', $goto);
    $goto = preg_replace ('/\r\n/', ',', $goto);
    $goto = preg_replace ('/[\s]+/i', '', $goto);
    $goto = preg_replace ('/\,*$/', '', $goto);
    ( $fForward_and_store == "YES" ) ? $goto = $USERID_USERNAME . "," . $goto : '';
    $goto = explode(",",$goto);
    $goto = array_merge(array_unique($goto));
    $goto = implode(",",$goto);

    $array = preg_split ('/,/', $goto);

    for ($i = 0; $i < sizeof ($array); $i++) {
        if (in_array ("$array[$i]", $CONF['default_aliases'])) continue;
        if (empty ($array[$i]) && $fForward_and_store == "NO")
        {
            $error = 1;
            $tGoto = $goto;
            $tMessage = $PALANG['pEdit_alias_goto_text_error1'];
        }
        if (empty ($array[$i])) continue;
        if (!check_email ($array[$i]))
        {
            $error = 1;
            $tGoto = $goto;
            $tMessage = $PALANG['pEdit_alias_goto_text_error2'] . "$array[$i]</font>";
        }
    }

    if ($error != 1)
    {
        if (empty ($goto))
        {
            $goto = $USERID_USERNAME;
        }

        if ($fVacation == "YES")
        {
            $goto .= "," . $vacation_goto;
        }

        $result = db_query ("UPDATE $table_alias SET goto='$goto',modified=NOW() WHERE address='$USERID_USERNAME'");
        if ($result['rows'] != 1)
        {
            $tMessage = $PALANG['pEdit_alias_result_error'];
        }
        else
        {
            db_log ($USERID_USERNAME, $USERID_DOMAIN, 'edit_alias', "$USERID_USERNAME -> $goto");

            header ("Location: main.php");
            exit;
        }
    }

    include ("../templates/header.php");
    include ("../templates/users_menu.php");
    include ("../templates/users_edit-alias.php");
    include ("../templates/footer.php");
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
