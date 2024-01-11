<?php

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use OTPHP\TOTP;

class TotpPf
{
    private string $key_table;
    private string $table;
    private Login $login;

    public function __construct(string $tableName)
    {
        $ok = ['mailbox', 'admin'];

        if (!in_array($tableName, $ok)) {
            throw new \InvalidArgumentException("Unsupported tableName for TOTP: " . $tableName);
        }
        $this->table = $tableName;
        $this->key_table = table_by_key($tableName);
        $this->login = new Login($tableName);
    }

    /**
     * @param string username to generate a code for
     *
     * @return Array
     *      string TOTP_secret empty if NULL,
     *      string &$qr_code for returning base64-encoded qr-code
     *
     * @throws \Exception if invalid user, or db update fails.
     */
    public function generate(string $username): array
    {
        $totp = TOTP::create();
        $totp->setLabel($username);
        $totp->setIssuer('Postfix Admin');
        if (Config::read('logo_url')) {
            $totp->setParameter('image', Config::read('logo_url'));
        }
        $QR_content = $totp->getProvisioningUri();
        $pTOTP_secret = $totp->getSecret();
        unset($totp);
        $QRresult = Builder::create()
            ->writer(new PngWriter())
            ->writerOptions([])
            ->data($QR_content)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
            ->size(300)
            ->margin(10)
            ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
            ->validateResult(false)
            ->build();
        $qr_code = base64_encode($QRresult->getString());
        return array($pTOTP_secret, $qr_code);
    }

    /**
     * @param string $username
     *
     * @return boolean
     */
    public function usesTOTP(string $username): bool
    {
        if (!(Config::read('totp') == 'YES')) {
            return false;
        }

        $sql = "SELECT totp_secret FROM {$this->table} WHERE username = :username AND active = :active";

        $active = db_get_boolean(true);

        $values = [
            'username' => $username,
            'active' => $active,
        ];

        $result = db_query_one($sql, $values);

        if (is_array($result) && isset($result['totp_secret']) && !empty($result['totp_secret'])) {
            return true;
        }
        return false;
    }

    /**
     * @param string $username
     * @param string $code
     *
     * @return boolean
     */
    public function checkUserTOTP(string $username, string $code): bool
    {
        $sql = "SELECT totp_secret FROM {$this->table} WHERE username = :username AND active = :active";

        $active = db_get_boolean(true);

        $values = [
            'username' => $username,
            'active' => $active,
        ];

        $result = db_query_one($sql, $values);
        if (!is_array($result) || !isset($result['totp_secret'])) {
            return false;
        }

        return $this->checkTOTP($result['totp_secret'], $username, $code);
    }

    /**
     * @param string $secret
     * @param string $username
     * @param string $code
     *
     * @return boolean
     */
    public function checkTOTP(string $secret, string $username, string $code): bool
    {
        $totp = TOTP::create($secret);

        if ($totp->now() == $code) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param string $username
     * @param string $password
     *
     * @return string TOTP_secret, empty if NULL
     * @throws \Exception if invalid user, or db update fails.
     */
    public function getTOTP_secret(string $username, string $password): string
    {
        if (!$this->login->login($username, $password)) {
            throw new \Exception(Config::Lang('pPassword_password_current_text_error'));
        }

        $sql = "SELECT totp_secret FROM {$this->table} WHERE username = :username AND active = :active";

        $active = db_get_boolean(true);

        $values = [
            'username' => $username,
            'active' => $active,
        ];

        $result = db_query_one($sql, $values);
        if (is_array($result) && isset($result['totp_secret'])) {
            return $result['totp_secret'];
        } else {
            return '';
        }
    }

    /**
     * @param string $username
     * @param string $TOTP_secret
     * @param string $password
     *
     * @return boolean true on success; false on failure
     * @throws \Exception if invalid user, or db update fails.
     */
    public function changeTOTP_secret(string $username, string $TOTP_secret, string $password): bool
    {
        list(/*NULL*/, $domain) = explode('@', $username);

        if (!$this->login->login($username, $password)) {
            throw new \Exception(Config::Lang('pPassword_password_current_text_error'));
        }

        $set = array(
            'totp_secret' => $TOTP_secret,
        );

        $result = db_update($this->table, 'username', $username, $set);

        if ($result != 1) {
            db_log($domain, 'edit_password', "FAILURE: " . $username);
            throw new \Exception(Config::lang('pEdit_mailbox_result_error'));
        }


        $cmd_pw = Config::read('mailbox_post_TOTP_change_secret_script');

        if (empty($cmd_pw)) {
            return true;
        }

        $warnmsg_pw = Config::Lang('mailbox_post_TOTP_change_failed');

        // If we have a mailbox_postpassword_script (dovecot only?)

        // Use proc_open call to avoid safe_mode problems and to prevent showing plain password in process table
        $spec = array(
            0 => array("pipe", "r"), // stdin
            1 => array("pipe", "w"), // stdout
        );

        $cmdarg1 = escapeshellarg($username);
        $cmdarg2 = escapeshellarg($domain);
        $command = "$cmd_pw $cmdarg1 $cmdarg2 2>&1";

        $proc = proc_open($command, $spec, $pipes);

        if (!$proc) {
            throw new \Exception("can't proc_open $cmd_pw");
        }

        // Write secret through pipe to command stdin.
        fwrite($pipes[0], $TOTP_secret . "\0", 1 + strlen($TOTP_secret));
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[0]);
        fclose($pipes[1]);

        $retval = proc_close($proc);

        if (0 != $retval) {
            error_log("Running $command yielded return value=$retval, output was: " . json_encode($output));
            throw new \Exception($warnmsg_pw);
        }

        return true;
    }

    /**
     * @param string $username
     * @param string $password
     * @param string $Exception_ip
     * @param string $Exception_user
     * @param string $Exception_desc
     *
     * @return boolean true on success; false on failure
     * @throws \Exception if invalid user, or db update fails.
     */
    public function addException(string $username, string $password, string $Exception_ip, string $Exception_user, string $Exception_desc): bool
    {
        $error = 0;

        list($local_part, $domain) = explode('@', $username);

        if (!$this->login->login($username, $password)) {
            throw new \Exception(Config::Lang('pPassword_password_current_text_error'));
        }

        if (authentication_has_role('admin')) {
            $admin = 1;
        } elseif (authentication_has_role('global-admin')) {
            $admin = 2;
        } else {
            $admin = 0;
        }

        if (empty($Exception_ip)) {
            $error += 1;
            flash_error(Config::Lang('pException_ip_empty_error'));
        }

        if (empty($Exception_desc)) {
            $error += 1;
            flash_error(Config::Lang('pException_desc_empty_error'));
        }

        if (!$admin && strpos($Exception_user, '@') == false) {
            $error += 1;
            flash_error(Config::Lang('pException_user_entire_domain_error'));
        }

        if (!($admin == 2) && $Exception_user == null) {
            $error += 1;
            flash_error(Config::Lang('pException_user_global_error'));
        }


        $values = ['ip' => $Exception_ip, 'username' => $Exception_user, 'description' => $Exception_desc];

        if (!$error) {
            // OK to insert/replace.
            // As PostgeSQL lacks REPLACE we first check and delete any previous rows matching this ip and user
            $exists = db_query_all('SELECT id FROM totp_exception_address WHERE ip = :ip AND username = :username',
                ['ip' => $Exception_ip, 'username' => $Exception_user]);
            if (isset($exists[0])) {
                foreach ($exists as $x) {
                    db_delete('totp_exception_address', 'id', $x['id']);
                }
            }
            $result = db_insert('totp_exception_address', $values, array());
        }

        if ($result != 1) {
            db_log($domain, 'add_totp_exception', "FAILURE: " . $username);
            throw new \Exception(Config::lang('pEdit_totp_exception_result_error'));
        }


        $cmd_pw = Config::read('mailbox_post_totp_exception_add_script');
        if (empty($cmd_pw)) {
            return true;
        }
        $warnmsg_pw = Config::Lang('mailbox_post_totp_exception_add_failed');
        // If we have a mailbox_postpassword_script (dovecot only?)
        // Use proc_open call to avoid safe_mode problems and to prevent showing plain password in process table
        $spec = array(
            0 => array("pipe", "r"), // stdin
            1 => array("pipe", "w"), // stdout
        );
        $cmdarg1 = escapeshellarg($username);
        $cmdarg2 = escapeshellarg($Exception_ip);
        $command = "$cmd_pw $cmdarg1 $cmdarg2 2>&1";
        $proc = proc_open($command, $spec, $pipes);
        if (!$proc) {
            throw new \Exception("can't proc_open $cmd_pw");
        }
        fclose($pipes[0]);
        fclose($pipes[1]);
        $retval = proc_close($proc);
        if (0 != $retval) {
            error_log("Running $command yielded return value=$retval, output was: " . json_encode($retval));
            throw new \Exception($warnmsg_pw);
        }

        return true;
    }

    /**
     * @param string $username
     * @param int $Exception_id
     *
     * @return boolean true on success; false on failure
     * @throws \Exception if invalid user, or db update fails.
     */
    public function deleteException(string $username, int $id): bool
    {
        $exception = $this->getException($id);
        $error = 0;

        if (!is_array($exception)) {
            throw new \InvalidArgumentException("Invalid exception - does id: $id exist?");
        }

        if (strpos($exception['username'], '@')) {
            list($Exception_local_part, $Exception_domain) = explode('@', $exception['username']);
        } else {
            $Exception_domain = $exception['username'];
        }

        $admin = 0;
        if (authentication_has_role('global-admin')) {
            $admin = 2;
        } elseif (authentication_has_role('admin')) {
            $admin = 1;
        }

        /**
         * @todo rewrite these checks so it's more obvious which is being applied for a global admin, a domain admin or a 'normal' user.
         *       having $admin = 0|1|2 isn't intuitive, is it?
         */
        if (!$admin && strpos($exception['username'], '@') !== false) {
            throw new \Exception(Config::Lang('pException_user_entire_domain_error'));
        }

        if (!($admin == 2) && $exception['username'] == null) {
            throw new \Exception(Config::Lang('pException_user_global_error'));
        }

        /**
         * @todo Check we are only allowing someone to delete their own exception, and not someone else's.
         */
        $result = db_delete('totp_exception_address', 'id', $exception['id']);

        if ($result != 1) {
            db_log($Exception_domain, 'pViewlog_action_delete_totp_exception', "FAILURE: " . $username);
            throw new \Exception(Config::lang('pEdit_totp_exception_result_error'));
        }


        $cmd_pw = Config::read('mailbox_post_totp_exception_delete_script');
        if (empty($cmd_pw)) {
            return true;
        }
        $warnmsg_pw = Config::Lang('mailbox_post_totp_exception_delete_failed');
        // If we have a mailbox_postpassword_script (dovecot only?)
        // Use proc_open call to avoid safe_mode problems and to prevent showing plain password in process table
        $spec = array(
            0 => array("pipe", "r"), // stdin
            1 => array("pipe", "w"), // stdout
        );
        $cmdarg1 = escapeshellarg($username);
        $cmdarg2 = escapeshellarg($exception['ip']);
        $command = "$cmd_pw $cmdarg1 $cmdarg2 2>&1";
        $proc = proc_open($command, $spec, $pipes);
        if (!$proc) {
            throw new \Exception("can't proc_open $cmd_pw");
        }
        // Write secret through pipe to command stdin.
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[0]);
        fclose($pipes[1]);
        $retval = proc_close($proc);
        if (0 != $retval) {
            error_log("Running $command yielded return value=$retval, output was: " . json_encode($output));
            throw new \Exception($warnmsg_pw);
        }

        return true;
    }

    /**
     * @return array of all exceptions
     */
    public function getAllExceptions(): array
    {
        return db_query_all("SELECT * FROM totp_exception_address");
    }

    /**
     * @param string $username
     *
     * @return array of exceptions acting on this username
     */
    public function getExceptionsFor(string $username): array
    {
        list($local_part, $domain) = explode('@', $username);
        return db_query_all("SELECT * FROM totp_exception_address WHERE username = :username OR username = :domain OR username IS NULL", ['username' => $username, 'domain' => $domain]);
    }

    /**
     * @param int $id
     *
     * @return array the exception with this id
     */
    public function getException(int $id): ?array
    {
        return db_query_one("SELECT * FROM totp_exception_address WHERE id=:id", ['id' => $id]);
    }
}
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
