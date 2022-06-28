<?php

require_once(dirname(__FILE__) . '/common.php');

$xmlrpc = get_xmlrpc();
$user = $xmlrpc->getProxy('user');

global $username;

do_header();

$USERID_USERNAME = $username;
$tmp = preg_split('/@/', $USERID_USERNAME);
$USERID_DOMAIN = $tmp[1];


$stMessage = '';
$tMessage = '';
$pPassword_admin_text = '';
$pPassword_password_current_text = '';
$pPassword_password_text = '';
$error = 0;

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    //$pPassword_password_text = _("pPassword_password_text");
    $fPassword_current = $_POST['fPassword_current'];
    $fPassword = $_POST['fPassword'];
    $fPassword2 = $_POST['fPassword2'];
    $username = $USERID_USERNAME;

    if (!$user->login($_SESSION['username'], $_POST['fPassword_current'])) {
        $error = 1;
        bindtextdomain('postfixadmin', SM_PATH . 'plugins/postfixadmin/locale');
        textdomain('postfixadmin');
        $pPassword_password_current_text = _("You didn't supply your current password!");
        bindtextdomain('squirrelmail', SM_PATH . 'locale');
        textdomain('squirrelmail');
    }
    $min_length = 0;
    if (isset($CONF['min_password_length'])) {
        $min_length = $CONF['min_password_length'];
    }
    if (empty($fPassword) or ($fPassword != $fPassword2) or ($min_length > 0 && strlen($fPassword) < $min_length)) {
        $error = 1;
        bindtextdomain('postfixadmin', SM_PATH . 'plugins/postfixadmin/locale');
        textdomain('postfixadmin');
        if (empty($fPassword)) {
            $pPassword_password_text .= _("The passwords that you supplied are empty!");
        }
        if ($fPassword != $fPassword2) {
            $pPassword_password_text .= _("The passwords that you supplied don't match!");
        }
        if ($min_length > 0 && strlen($fPassword) < $min_length) {
            $pPassword_password_text .= _("The password you supplied is too short!");
        }
        bindtextdomain('squirrelmail', SM_PATH . 'locale');
        textdomain('squirrelmail');
    }

    if ($error != 1) {
        $success = $user->changePassword($fPassword_current, $fPassword);

        if ($success) {
            bindtextdomain('postfixadmin', SM_PATH . 'plugins/postfixadmin/locale');
            textdomain('postfixadmin');
            $tMessage = _("Your password has been changed!");
            $stMessage = _("Please sign out and log back again with your new password!");
            bindtextdomain('squirrelmail', SM_PATH . 'locale');
            textdomain('squirrelmail');
        } else {
            bindtextdomain('postfixadmin', SM_PATH . 'plugins/postfixadmin/locale');
            textdomain('postfixadmin');
            $tMessage = _("Unable to change your password!");
            bindtextdomain('squirrelmail', SM_PATH . 'locale');
            textdomain('squirrelmail');
        }
    }
}
bindtextdomain('postfixadmin', SM_PATH . 'plugins/postfixadmin/locale');
textdomain('postfixadmin');
echo "<table bgcolor=\"$color[0]\" align=\"center\" width=\"95%\" cellpadding=\"1\" cellspacing=\"0\" border=\"0\">
    <tr>
    <td align=\"center\"><b>". _("Options") ." - ". _("Change Password")." </b>
    <table align=\"center\" width=\"100%\" cellpadding=\"5\" cellspacing=\"0\" border=\"0\">
    <tr><td bgcolor=\"$color[4]\" align=\"center\"><br>
    <table align=\"center\" width=\"95%\" cellpadding=\"4\" cellspacing=\"0\" border=\"0\"><tr>
    <td bgcolor=\"$color[3]\" align=\"center\"><b>" ._("Change your login password") ."\n
    </b></td>
    </tr>
    <tr>
    <td bgcolor=\"$color[0]\" align=\"center\"><form name=\"mailbox\" method=\"post\">
    <b>$tMessage<b><font color=red><br>
    <a href=\"../../src/signout.php\" target=\"_top\">$stMessage</a>
    ".$pPassword_admin_text."\n
    ".$pPassword_password_current_text."\n
    ".$pPassword_password_text."\n
    </b><table width=\"95%\" border=\"0\" cellspacing=\"0\" cellpadding=\"2\">
    <tr>
    <td width=\"37%\"><b>". _("Alias") . ":\n</td>
    <td width=\"63%\">{$_SESSION['username']}</td>
    </tr>
    <tr>
    <td><b>". _("Password current"). ":\n</td>
    <td><input type=\"password\" name=\"fPassword_current\" size=\"30\" /></td>
    </tr>
    <tr>
    <td><b>". _("Password new"). ":\n</td>
    <td><input type=\"password\" name=\"fPassword\" size=\"30\" /></td>
    </tr>
    <tr>
    <td><b>". _("Password new again"). ":\n</td>
    <td><input type=\"password\" name=\"fPassword2\" size=\"30\" /></td>
    </tr>
    <tr>
    <td>&nbsp;</td>
    <td><input type=\"submit\" name=\"submit\" value=\"" ._("Change Password") . "\" /></td>
    <td>&nbsp;</td>
    </tr>
    </table>
    <TT></TT></FORM></td>
    </tr><tr><td bgcolor=\"$color[4]\" align=\"left\">&nbsp;</td>
    </tr></table><BR>
    </td>
    </tr></table></td></tr></table>";
bindtextdomain('squirrelmail', SM_PATH . 'locale');
textdomain('squirrelmail');
