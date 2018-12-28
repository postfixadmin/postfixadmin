<?php

require_once(dirname(__FILE__) . '/common.php');



$USERID_USERNAME = $username;
$tmp = preg_split('/@/', $USERID_USERNAME);
$USERID_LOCALPART = $tmp[0];
$USERID_DOMAIN = $tmp[1];

$xmlrpc = get_xmlrpc();
$alias = $xmlrpc->getProxy('alias');
do_header();
// Normal page request (GET)
if ($_SERVER['REQUEST_METHOD'] == "GET") {
    $row = $alias->get();
    if ($row === false) {
        bindtextdomain('postfixadmin', SM_PATH . 'plugins/postfixadmin/locale');
        textdomain('postfixadmin');
        $tMessage = _("Unable to locate alias!");
        bindtextdomain('squirrelmail', SM_PATH . 'locale');
        textdomain('squirrelmail');
        exit(0);
    }
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $pEdit_alias_goto = _("To");

    $fGoto = $_POST['fGoto'];

    // reform string into a list...
    $goto = preg_replace('/\r\n/', ',', $fGoto);
    $goto = preg_replace('/[\s]+/i', '', $goto);
    $goto = preg_replace('/\,*$/', '', $goto);
    $array = preg_split('/,/', $goto);
    $error = 0;
    // check that we have valid addresses in the list

    foreach ($array as $key => $email_address) {
        if (empty($email_address)) {
            unset($array[$key]);
            continue;
        }
        if (check_email($email_address) != "") {
            $error = 1;
            $tGoto = $goto;
            bindtextdomain('postfixadmin', SM_PATH . 'plugins/postfixadmin/locale');
            textdomain('postfixadmin');
            $tMessage = _("The email address that you have entered is not valid:") . " $email_address</font>";
            bindtextdomain('squirrelmail', SM_PATH . 'locale');
            textdomain('squirrelmail');
        }
    }
    if ($error != 1) {
        $flag = 'forward_and_store'; // goto = $USERID_USERNAME;
        $success = $alias->update($array, $flag);
        if (!$success) {
            bindtextdomain('postfixadmin', SM_PATH . 'plugins/postfixadmin/locale');
            textdomain('postfixadmin');
            $tMessage = _("Unable to modify the alias!");
            bindtextdomain('squirrelmail', SM_PATH . 'locale');
            textdomain('squirrelmail');
        } else {
            bindtextdomain('postfixadmin', SM_PATH . 'plugins/postfixadmin/locale');
            textdomain('postfixadmin');
            echo "<p align=center><b>". _("Alias successfully changed!"). "\n</b></p>";
            bindtextdomain('squirrelmail', SM_PATH . 'locale');
            textdomain('squirrelmail');
            echo "<p align=center><a href=\"javascript:history.go(-1)\">". _("Click here to go back") ."</a></p>";
            exit;
        }
    }
}
bindtextdomain('postfixadmin', SM_PATH . 'plugins/postfixadmin/locale');
textdomain('postfixadmin');

if (!isset($tMessage)) {
    $tMessage = '';
}
echo "<table bgcolor=\"$color[0]\" align=\"center\" width=\"95%\" cellpadding=\"1\" cellspacing=\"0\" border=\"0\">
<tr>
<td align=\"center\" bgcolor=\"$color[0]\" colspan=\"2\">
<b>". _("Options") ." - ". _("Edit Alias"). " </b>
<table align=\"center\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\">
<tr>
<td bgcolor=\"$color[4]\" align=\"center\">
<table align=\"center\" width=\"100%\">
<tr>
<td align=\"left\">". _("Edit an alias* for your email address.<br />One entry per line."). " </td>
</tr>
<tr>
<td align=\"left\">". _("*Additional forward-aliases always receive messages BCC!"). "\n
</tr>
<tr>
<td align=\"left\">" . _("To remove an alias, simply delete its line from the text box.") . "</td>
</tr>
</table>
<table align=\"center\" width\"95%\" cellpadding=\"5\" cellspacing=\"1\">
<form name=\"mailbox\" method=\"post\">
<tr>
<td bgcolor=\"$color[3]\" align=\"center\"><b>". _("Edit Forwards"). "</b>
</td>
</tr>
<tr>
<td bgcolor=\"$color[5]\" align=\"center\">$tMessage
<table cellpadding=\"5\" cellspacing=\"1\">
<tr>
<th align=\"left\">". _("Alias"). ":\n
</th>
<td align=\"left\">" . $_SESSION['username'] . "</td>
</tr>
<tr>
<th>&nbsp;</th>
<td>&nbsp;</td>
</tr>
<tr>
<th align=\"left\" valign=\"top\">". _("To"). ":\n</th>
<td>
<textarea rows=\"8\" cols=\"50\" name=\"fGoto\">";
bindtextdomain('squirrelmail', SM_PATH . 'locale');
textdomain('squirrelmail');
$aliases = $alias->get();
foreach ($aliases as $address) {
    if ($address == "" || $address == null) {
        continue;
    }
    print "$address\n";
}
bindtextdomain('postfixadmin', SM_PATH . 'plugins/postfixadmin/locale');
textdomain('postfixadmin');
echo "
</textarea>
</td>
</tr>
<tr>
<th>&nbsp;</th>
<td>&nbsp;</td>
</tr>
<tr>
<th>&nbsp;</th>
<td align=\"left\"colspan=\"2\">
<input type=\"submit\" name=\"submit\" value=\"" . _("Edit Alias") . "\">
</td>
</tr>
</table>
</td>
</tr>
</table>
</form>
</td>
</tr>
</table>
</td></tr>
</table>
";
bindtextdomain('squirrelmail', SM_PATH . 'locale');
textdomain('squirrelmail');
