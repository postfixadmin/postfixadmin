<?php

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use OTPHP\TOTP;

class TotpPf
{
    private string $table;
    private Login $login;

    public function __construct(string $tableName, Login $login)
    {
        $ok = ['mailbox', 'admin'];

        if (!in_array($tableName, $ok)) {
            throw new \InvalidArgumentException("Unsupported tableName for TOTP: " . $tableName);
        }
        $this->table = $tableName;
        $this->login = $login;
    }

    /**
     * @param string username to generate a code for
     *
     * @return array{0: string, 1: string}
     *      string TOTP_secret empty if NULL,
     *      string $qr_code for returning base64-encoded qr-code
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

        return $this->checkTOTP($result['totp_secret'], $code);
    }

    /**
     * @param string $secret
     * @param string $code
     *
     * @return boolean
     */
    public function checkTOTP(string $secret, string $code): bool
    {
        $totp = TOTP::create($secret);

        if ($totp->now() == $code) {
            return true;
        } else {
            return false;
        }
    }

    public function removeTotpFromUser(string $username): void
    {
        $sql = "UPDATE {$this->table} SET totp_secret = NULL WHERE username = :username";
        db_execute($sql, ['username' => $username]);
    }

    /**
     * @param string $username
     * @param string $password
     *
     * @return string TOTP_secret or null if no secret set?
     * @throws \Exception if invalid user, or db update fails.
     */
    public function getTOTP_secret(string $username, string $password): ?string
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
        }
        return null;
    }

    /**
     * @param string $username
     * @param ?string $TOTP_secret
     * @param string $password
     *
     * @return boolean true on success; false on failure
     * @throws \Exception if invalid user, or db update fails.
     */
    public function changeTOTP_secret(string $username, ?string $TOTP_secret, string $password): bool
    {
        list(/*NULL*/, $domain) = explode('@', $username);

        if (!$this->login->login($username, $password)) {
            throw new \Exception(Config::Lang('pPassword_password_current_text_error'));
        }

        $set = [
            'totp_secret' => $TOTP_secret,
        ];

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
     * @param string $username - auth details for user adding the exception
     * @param string $password - auth details for the user adding the exception
     * @param string $ip_address - exception ip
     * @param string $exception_username - exception user (should == username if non-admin user)
     * @param string $exception_description - text from the end user
     *
     * @return boolean true on success; false on failure
     * @throws \Exception if invalid user, or db update fails.
     */
    public function addException(string $username, string $password, string $ip_address, string $exception_username, string $exception_description): bool
    {
        $error = 0;

        list($local_part, $domain) = explode('@', $username);

        if (!$this->login->login($username, $password)) {
            throw new \Exception(Config::Lang('pPassword_password_current_text_error'));
        }

        if (authentication_has_role('admin')) {
            $admin = 1;
            // @todo - ensure the current user is an admin for the domain belonging to @Exception_user
            // code already semi-duplicated in the below function deleteExemption()
            $domains = list_domains_for_admin($username);

            if (strpos($exception_username, '@')) {
                list($local_part, $Exception_domain) = explode('@', $exception_username);
            } else {
                $Exception_domain = $exception_username;
            }

            // if the exemption is not for the current user, then ensure it's for someone belonging to a domain they have access to.
            if ($exception_username != $username) {
                if (!in_array($Exception_domain, $domains)) {
                    throw new \Exception(Config::Lang('pException_user_entire_domain_error'));
                }
            }
        } elseif (authentication_has_role('global-admin')) {
            $admin = 2;
        // can do anything
        } else {
            // force the current user to also be the exemption username.
            $exception_username = $username;
            $admin = 0;
        }

        if (empty($ip_address)) {
            $error++;
            flash_error(Config::Lang('pException_ip_empty_error'));
        }

        if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
            $error++;
            flash_error(Config::Lang('pException_ip_error'));
        }

        if (empty($exception_description)) {
            $error++;
            flash_error(Config::Lang('pException_desc_empty_error'));
        }

        if (!$admin && strpos($exception_username, '@') == false) {
            $error++;
            flash_error(Config::Lang('pException_user_entire_domain_error'));
        }

        if (!($admin == 2) && $exception_username == null) {
            $error++;
            flash_error(Config::Lang('pException_user_global_error'));
        }


        $values = ['ip' => $ip_address, 'username' => $exception_username, 'description' => $exception_description];

        if ($error == 0) {
            // OK to insert/replace.
            // As PostgeSQL lacks REPLACE we first check and delete any previous rows matching this ip and user
            $exists = db_query_all('SELECT id FROM totp_exception_address WHERE ip = :ip AND username = :username',
                ['ip' => $ip_address, 'username' => $exception_username]);
            if (isset($exists[0])) {
                foreach ($exists as $x) {
                    db_delete('totp_exception_address', 'id', $x['id']);
                }
            }
            $result = db_insert('totp_exception_address', $values, []);
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
        $cmdarg2 = escapeshellarg($ip_address);
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
     * @param string $username - current user (not the totp_exception_Address.username value)
     * @param int $Exception_id
     *
     * @return boolean true on success; false on failure
     * @throws \Exception if invalid user, or db update fails.
     */
    public function deleteException(string $username, int $id): bool
    {
        $exception = $this->getException($id);

        if (!is_array($exception)) {
            throw new \InvalidArgumentException("Invalid exception - does id: $id exist?");
        }

        if (strpos($exception['username'], '@')) {
            list($Exception_local_part, $Exception_domain) = explode('@', $exception['username']);
        } else {
            $Exception_domain = $exception['username'];
        }

        if (authentication_has_role('global-admin')) {
            // no need to check, they can delete anything.
        } elseif (authentication_has_role('admin')) {
            $domains = list_domains_for_admin(authentication_get_username());

            if (strpos($exception['username'], '@')) {
                list($Exception_local_part, $Exception_domain) = explode('@', $exception['username']);
            } else {
                $Exception_domain = $exception['username'];
            }

            // if the exemption is not for the current user, then ensure it's for someone belonging to a domain they have access to.
            if ($exception['username'] != $username && !in_array($Exception_domain, $domains)) {
                throw new \Exception(Config::Lang('pException_user_entire_domain_error'));
            }
        } else {
            // i'm only a boring user, I cannot delete exceptions for a domain (no @) or for someone else,
            // so ensure the exception.username field matches my own username.
            if ($exception['username'] != $username) {
                throw new \Exception(Config::Lang('pException_user_entire_domain_error') . 'x');
            }

            // and now ensure that our current user owns the exception:
            if ($exception['username'] != $username) {
                throw new \Exception(Config::lang('pEdit_totp_exception_result_error') . 'y');
            }
        }


        $result = db_execute('DELETE FROM totp_exception_address WHERE id = :id', ['id' => $id]);

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
        return db_query_one("SELECT * FROM totp_exception_address WHERE id = :id", ['id' => $id]);
    }
}
