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
 * File: sendmail.php
 * Used to send an email to a user.
 * Template File: sendmail.tpl
 *
 * Template Variables:
 *
 * tFrom
 * tSubject
 * tBody
 *
 * Form POST \ GET Variables:
 *
 * fTo
 * fSubject
 * fBody
 */

require_once('common.php');

authentication_require_role('admin');

$CONF = Config::getInstance()->getAll();
$smarty = PFASmarty::getInstance();
$PALANG = $CONF['__LANG'];

(($CONF['sendmail'] == 'NO') ? header("Location: main.php") && exit : '1');

$smtp_from_email = smtp_get_admin_email();


if ($_SERVER['REQUEST_METHOD'] == "POST") {
    if (safepost('token') != $_SESSION['PFA_token']) {
        die('Invalid token!');
    }

    $fTo = safepost('fTo');
    $fFrom = $smtp_from_email;
    $fSubject = safepost('fSubject');

    $tBody = $_POST['fBody'];

    $error = 0;
    $email_check = check_email($fTo);
    if (empty($fTo) or ($email_check != '')) {
        $error = 1;
        $tTo = escape_string($_POST['fTo']);
        $tSubject = escape_string($_POST['fSubject']);
        flash_error($PALANG['pSendmail_to_text_error']); # TODO: superfluous?
        flash_error($email_check);
    }

    if ($error != 1) {
        if (!smtp_mail($fTo, $fFrom, $fSubject, smtp_get_admin_password(), $tBody)) {
            flash_error(Config::lang_f('pSendmail_result_error', $fTo));
        } else {
            flash_info(Config::lang_f('pSendmail_result_success', $fTo));
        }
    }
}
$smarty->assign('smtp_from_email', $smtp_from_email);
$smarty->assign('smarty_template', 'sendmail');
$smarty->display('index.tpl');


/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
