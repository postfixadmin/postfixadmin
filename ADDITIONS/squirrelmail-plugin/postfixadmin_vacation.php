<?php

require_once(dirname(__FILE__) . '/common.php');

$xmlrpc = get_xmlrpc();
$vacation = $xmlrpc->getProxy('vacation');

$VACCONFTXT = _("I will be away from <date> until <date>. For urgent matters you can contact <contact person>.");
bindtextdomain('squirrelmail', SM_PATH . 'locale');
textdomain('squirrelmail');
$VACCONF = <<<EOM
$VACCONFTXT
EOM;

do_header();

$USERID_USERNAME = $username;
$tmp = preg_split('/@/', $USERID_USERNAME);
$USERID_DOMAIN = $tmp[1];

if ($_SERVER['REQUEST_METHOD'] == "GET") {
    $details = $vacation->getDetails();
    if ($vacation->checkVacation()) {
        bindtextdomain('postfixadmin', SM_PATH . 'plugins/postfixadmin/locale');
        textdomain('postfixadmin');
        $tMessage = _("You already have an auto response configured!");
        bindtextdomain('squirrelmail', SM_PATH . 'locale');
        textdomain('squirrelmail');
        bindtextdomain('postfixadmin', SM_PATH . 'plugins/postfixadmin/locale');
        textdomain('postfixadmin');
        echo "<table bgcolor=\"#b8cbdc\" align=\"center\" width=\"95%\" cellpadding=\"1\" cellspacing=\"0\" border=\"0\"><tr>
            <td align=\"center\"><b>". _("Options") ." - ". _("Auto Response") ."</b>
            <table align=\"center\" width=\"100%\" cellpadding=\"5\" cellspacing=\"0\" border=\"0\">
            <tr><td bgcolor=\"$color[4]\" align=\"center\"><br>
            <table align=\"center\" width=\"70%\" cellpadding=\"4\" cellspacing=\"0\" border=\"0\"><tr>
            <td bgcolor=\"$color[3]\" align=\"center\"><b>". _("Auto Response") ."\n
            </b></td></tr><tr>
            <td bgcolor=\"$color[0]\" align=\"center\"><form name=\"vacation\" method=\"post\">
            <table width=\"95%\" border=\"0\" cellspacing=\"0\" cellpadding=\"2\">
            <tr>
            <td><center>$tMessage<p></center></td>
            </tr>
            <tr>
            <td> <div align=\"center\">
            <input type=\"submit\" name=\"fBack\" value=\"" . _("Coming Back"). "\" />
            </div></td>
            </tr>
            </table>
            <TT></TT></FORM>
            </td>
            </tr><tr><td bgcolor=\"$color[4]\" align=\"left\">&nbsp;</td>
            </tr></table><BR></td></tr></table></td></tr></table>";
        bindtextdomain('squirrelmail', SM_PATH . 'locale');
        textdomain('squirrelmail');
    } else {
        $tSubject = "Out of Office";
        $tSubject = $details['subject'];
        $VACCONF = $details['body'];

        $tMessage = '';
        bindtextdomain('postfixadmin', SM_PATH . 'plugins/postfixadmin/locale');
        textdomain('postfixadmin');
        echo "<table bgcolor=\"$color[0]\" align=\"center\" width=\"95%\" cellpadding=\"1\" cellspacing=\"0\" border=\"0\">
            <tr>
            <td align=\"center\"><b>". _("Options") ." - ". _("Auto Response") ." </b>
            <table align=\"center\" width=\"100%\" cellpadding=\"5\" cellspacing=\"0\" border=\"0\">
            <tr><td bgcolor=\"$color[4]\" align=\"center\"><br>
            <table align=\"center\" width=\"70%\" cellpadding=\"4\" cellspacing=\"0\" border=\"0\"><tr>
            <td bgcolor=\"$color[3]\" align=\"center\"><b>" . _("Auto Response") ."\n
            </b></td></tr><tr>
            <td bgcolor=\"$color[0]\" align=\"center\"><form name=\"vacation\" method=\"post\">$tMessage
            <table width=\"95%\" border=\"0\" cellspacing=\"0\" cellpadding=\"2\"><tr>
            <td width=\"23%\">". _("Subject") .":\n</td>
            <td width=\"2%\">&nbsp;</td>
            <td width=\"69%\"><input type=\"text\" name=\"fSubject\" value=\"" . $tSubject . "\" /></td>
            <td width=\"2%\">&nbsp;</td>
            <td width=\"4%\">&nbsp;</td>
            </tr><tr>
            <td>". _("Body") .":\n</td>
            <td>&nbsp;</td>
            <td><textarea rows=\"10\" cols=\"80\" name=\"fBody\">$VACCONF\n
            </textarea></td><td>&nbsp;</td><td>&nbsp;</td></tr><tr><td>&nbsp;</td><td>&nbsp;</td>
            <td><input type=\"submit\" name=\"fAway\" value=\"" . _("Going Away") . "\" /></td>
            <td>&nbsp;</td><td>&nbsp;</td></tr>
            </table><TT></TT></FORM></td>
            </tr><tr><td bgcolor=\"$color[4]\" align=\"left\">&nbsp;</td>
            </tr></table><BR></td></tr></table></td></tr></table>";
        bindtextdomain('squirrelmail', SM_PATH . 'locale');
        textdomain('squirrelmail');
    }
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $fBack = null;
    $fAway = null;
    foreach (array('fBack', 'fAway', 'fSubject', 'fBody') as $key) {
        $$key = null;
        if (isset($_POST[$key])) {
            $$key = $_POST[$key];
        }
    }

    if (!empty($fBack)) {
        $success = $vacation->remove();

        if (!$success) {
            bindtextdomain('postfixadmin', SM_PATH . 'plugins/postfixadmin/locale');
            textdomain('postfixadmin');
            $tMessage = _("Unable to update your auto response settings!");
            echo "<p>This may signify an error; please contact support (1)</p>";
            bindtextdomain('squirrelmail', SM_PATH . 'locale');
            textdomain('squirrelmail');
        } else {
            bindtextdomain('postfixadmin', SM_PATH . 'plugins/postfixadmin/locale');
            textdomain('postfixadmin');
            echo "<p align=center><b>". _("Your auto response has been removed!") ."</b></p>";
            bindtextdomain('squirrelmail', SM_PATH . 'locale');
            textdomain('squirrelmail');
        }
    }

    if (!empty($fAway)) {
        // add record into vacation
        $success = $vacation->setAway($fSubject, $fBody);

        if (!$success) {
            $error = 1;
            bindtextdomain('postfixadmin', SM_PATH . 'plugins/postfixadmin/locale');
            textdomain('postfixadmin');
            $tMessage = _("Unable to update your auto response settings!");
            bindtextdomain('squirrelmail', SM_PATH . 'locale');
            textdomain('squirrelmail');
        } else {
            bindtextdomain('postfixadmin', SM_PATH . 'plugins/postfixadmin/locale');
            textdomain('postfixadmin');
            echo "<p align=center><b>". _("Your auto response has been set!") ."</b></p>";
            bindtextdomain('squirrelmail', SM_PATH . 'locale');
            textdomain('squirrelmail');
        }
    }
}
