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
 * File: password-recover.php
 * Used by users and admins to recover their forgotten login password.
 * Template File: password-recover.tpl
 *
 * Template Variables:
 *
 * none
 *
 * Form POST \ GET Variables:
 *
 * fUsername
 */


if (preg_match('/\/users\//', $_SERVER['REQUEST_URI'])) {
  $rel_path = '../';
  $context = 'users';
} else {
  $rel_path = './';
  $context = 'admin';
}
require_once($rel_path . 'common.php');

if ($context == 'admin' && !Config::read('forgotten_admin_password_reset') || $context == 'users' && !Config::read('forgotten_user_password_reset'))
{
    header('HTTP/1.0 403 Forbidden');
    exit(0);
}

function sendCodebyEmail($to, $username, $code)
{
    $fHeaders = "To: " . $to . PHP_EOL;
    $fHeaders .= "From: " . Config::read('admin_email') . PHP_EOL;
    $fHeaders .= "Subject: " . encode_header(Config::Lang('pPassword_welcome')) . PHP_EOL;
    $fHeaders .= "MIME-Version: 1.0" . PHP_EOL;
    $fHeaders .= "Content-Type: text/plain; charset=utf-8" . PHP_EOL;
    $fHeaders .= "Content-Transfer-Encoding: 8bit" . PHP_EOL . PHP_EOL;

    $url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/password-change.php?username=' . urlencode($username) . '&code=' . $code;
    $fHeaders .= Config::lang_f('pPassword_recovery_email_body', $url);

    return smtp_mail($to, Config::read('admin_email') , $fHeaders);
}

function sendCodebySMS($to, $username, $code)
{
    $text = Config::lang_f('pPassword_recovery_sms_body', $code);
    
    $url = 'https://api.clickatell.com/http/sendmsg?api_id=' . Config::read('clickatell_api_id') . '&user=' . Config::read('clickatell_user') . '&password=' . Config::read('clickatell_password') . "&to=$to" . '&from=' . Config::read('clickatell_sender') . '&text=' . urlencode($text);

    $result = file_get_contents($url);

    return $result !== false;
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    $tUsername = escape_string (safepost('fUsername'));
    $table = table_by_key($context == 'users' ? 'mailbox' : 'admin');
    $result = db_query("SELECT * FROM `$table` WHERE username='$tUsername'");
    $eMessage = '';
    if ($result['rows'] == 1)
    {
        $row = db_array($result['result']);
        $code = getPasswordRecoveryCode($tUsername);

        $email_other = trim($row['email_other']);
        $phone = trim($row['phone']);

        // An active session is required to propagate flash messages to redirected page
        if ($email_other)
        {
            // send email
            if (sendCodeByEmail($email_other, $tUsername, $code))
            {
                flash_info(Config::Lang('pPassword_recovery_email_sent') . ' ' . $email_other);
            }
        }
        
        if ($phone)
        {
            // send phone
            if (sendCodeBySMS($phone, $tUsername, $code))
            {
                flash_info(Config::Lang('pPassword_recovery_sms_sent') . ' ' . $phone);
            }
        }

        if ($email_other || $phone)
        {
            // session_regenerate_id();
            header("Location: password-change.php?username=" . $tUsername);
            exit(0);
        }
        else
        {
            flash_error(Config::Lang('pPassword_recovery_no_alternative'));
        }
    }
    else
    {
        flash_error(Config::Lang('pCreate_mailbox_username_text_error1'));
    }
}

$smarty->assign ('language_selector', language_selector(), false);
$smarty->assign ('smarty_template', 'password-recover');
$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
