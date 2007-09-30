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
 * File: vacation.php
 * Used by users to set/change their vacation settings.
 *
 * Template File: users_vacation.tpl
 *
 * Template Variables:
 *
 * tMessage
 * tSubject
 * tBody
 *
 * Form POST \ GET Variables:
 *
 * fSubject
 * fBody
 * fAway
 * fBack
 */

require_once('../common.php');

authentication_require_role('user');
$USERID_USERNAME = authentication_get_username();

// is vacation support enabled in $CONF ?
if($CONF['vacation'] == 'NO') {
    header("Location: " . $CONF['postfix_admin_url'] . "/users/main.php");
    exit(0);
}

$tmp = preg_split ('/@/', $USERID_USERNAME);     
$USERID_DOMAIN = $tmp[1];

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
    $result = db_query("SELECT * FROM $table_vacation WHERE email='$USERID_USERNAME'");
    if ($result['rows'] == 1)
    {
        $row = db_array($result['result']);
        $tMessage = $PALANG['pUsersVacation_welcome_text'];
        $tSubject = $row['subject'];
        $tBody = $row['body'];
    }

    if ($tSubject == '') { $tSubject = $PALANG['pUsersVacation_subject_text']; }
    if ($tBody == '') { $tBody = $PALANG['pUsersVacation_body_text']; }

    $template = "users_vacation.tpl";

    include ("../templates/header.tpl");
    include ("../templates/users_menu.tpl");
    include ("../templates/users_vacation.tpl");
    include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    if(isset($_POST['fCancel'])) {
        header("Location: main.php");
        exit(0);
    }

    // We store goto addresses in the form of roger#example.com@autoreply.example.com
    $vacation_domain = $CONF['vacation_domain'];
    $vacation_goto = preg_replace('/@/', '#', $USERID_USERNAME);
    $vacation_goto = "{$vacation_goto}@{$vacation_domain}";

    if (isset ($_POST['fSubject'])) $fSubject = escape_string ($_POST['fSubject']);
    if (isset ($_POST['fBody'])) $fBody = escape_string ($_POST['fBody']);
    if (isset ($_POST['fAway'])) $fAway = escape_string ($_POST['fAway']);
    if (isset ($_POST['fBack'])) $fBack = escape_string ($_POST['fBack']);

    //set a default, reset fields for coming back selection
    if ($tSubject == '') { $tSubject = $PALANG['pUsersVacation_subject_text']; }
    if ($tBody == '') { $tBody = $PALANG['pUsersVacation_body_text']; }

    // if they've set themselves away OR back, delete any record of vacation emails.
    if (!empty ($fBack) || !empty ($fAway))
    {
        $result = db_query ("DELETE FROM $table_vacation WHERE email='$USERID_USERNAME'");
        $result2 = db_query ("DELETE FROM $table_vacation_notification WHERE on_vacation='$USERID_USERNAME'");
        if ($result['rows'] != 1)
        {
            $error = 1;
            $tMessage = $PALANG['pUsersVacation_result_error'];
        }
        else
        {
            $tMessage = $PALANG['pUsersVacation_result_success'];
        }

        // We need to see whether there is already an alias record for the user, or not. 
        // If not, we create one, else update the existing one.
        $result = db_query ("SELECT * FROM $table_alias WHERE address='$USERID_USERNAME'");
        if ($result['rows'] == 1)
        {
            $row = db_array ($result['result']);
            $tGoto = $row['goto'];

            //only one of these will do something, first handles address at beginning and middle, second at end, third if it's the only alias record.
            $goto= preg_replace ( "/$vacation_goto,/", '', $tGoto);
            $goto= preg_replace ( "/,$vacation_goto/", '', $tGoto);
            $goto= preg_replace ( "/$vacation_goto/", '', $tGoto);
            $query = "UPDATE $table_alias SET goto='$goto',modified=NOW() WHERE address='$USERID_USERNAME'";

            if($goto == '') {
                // if there are no other goto records left, remove the alias record for this user.
                $query = "DELETE FROM $table_alias WHERE address = '$USERID_USERNAME'";
            }
            $result = db_query($query);
        }
        else {
            $goto = $vacation_goto;
            $boolean = db_get_boolean(True);

            $result = db_query("INSERT into $table_alias (address, goto, domain, created, active) 
                                VALUES ('$USERID_USERNAME', '', '$USERID_DOMAIN', NOW(), $boolean)");
        }
        if ($result['rows'] != 1)
        {
            $error = 1;
            $tMessage = $PALANG['pUsersVacation_result_error'];
        }
        else
        {
            $tMessage = $PALANG['pUsersVacation_result_success'];
        }

    }

    // the user is going away - set the goto alias and vacation table as necessary.
    if (!empty ($fAway))
    {
        // Can we ever have no alias records for a user?
        $result = db_query ("SELECT * FROM $table_alias WHERE address='$USERID_USERNAME'");
        if ($result['rows'] == 1)
        {
            $row = db_array ($result['result']);
            $tGoto = $row['goto'];
        }
        $Active = db_get_boolean(True);
        $result = db_query ("INSERT INTO $table_vacation (email,subject,body,domain,created,active) VALUES ('$USERID_USERNAME','$fSubject','$fBody','$USERID_DOMAIN',NOW(),$Active)");
        if ($result['rows'] != 1)
        {
            $error = 1;
            $tMessage = $PALANG['pUsersVacation_result_error'];
        }
        // add the goto record back in...
        $comma = '';
        if(strlen($tGoto) > 1) {
            $comma = ',';
        }
        $goto = $tGoto . $comma . $vacation_goto;

        $result = db_query ("UPDATE $table_alias SET goto='$goto',modified=NOW() WHERE address='$USERID_USERNAME'");
        if ($result['rows'] != 1)
        {
            $error = 1;
            $tMessage = $PALANG['pUsersVacation_result_error'];
        }
        else
        {
            header ("Location: main.php");
            exit;
        }
    }

    include ("../templates/header.tpl");
    include ("../templates/users_menu.tpl");
    include ("../templates/users_vacation.tpl");
    include ("../templates/footer.tpl");
}
?>
