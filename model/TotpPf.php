<?php

/**
 * Class TotpPf
 *
 * Handles Time-based One-Time Password (TOTP) functionality for Postfix Admin
 * including generation, verification, and exception management.
 */

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;
use OTPHP\TOTP;

class TotpPf
{
    /**
     * @var 'mailbox'|'admin' - The table to operate on
     */
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
     * Generate a new TOTP secret and QR code for a user
     * @return array{0: string, 1: string} Array containing [TOTP secret, base64-encoded QR code]
     */
    public function generate(string $username): array
    {
        $totp = TOTP::create();
        $totp->setLabel($username);
        $totp->setIssuer('Postfix Admin');

        if (Config::has('logo_url') && is_string(Config::read('logo_url'))) {
            $totp->setParameter('image', (string)Config::read('logo_url'));
        }

        $QR_content = $totp->getProvisioningUri();
        $pTOTP_secret = $totp->getSecret();
        unset($totp);

        // endroid/qr-code
        // - v4.6 supports PHP 7.4 || 8.0
        // - v5 supports PHP ^8.1 and introduces enums ...
        // Hopefully this code will allow PHP 8.2 to use the newer library (and suppress deprecation warnings)
        if (class_exists('\Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh')) {
            /**
             * @psalm-suppress UndefinedClass
             */
            $level = new \Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh();
        } elseif (class_exists('\Endroid\QrCode\ErrorCorrectionLevel')) {
            /**
             * @psalm-suppress UndefinedClass
             */
            $level = \Endroid\QrCode\ErrorCorrectionLevel::High;
        } else {
            throw new \InvalidArgumentException("Endroid QR Code library issue - can't figure out ErrorCorrectionLevel.");
        }

        if (class_exists('\Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin')) {
            /**
             * @psalm-suppress UndefinedClass
             */
            $margin = new \Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin();
        } elseif (class_exists('\Endroid\QrCode\RoundBlockSizeMode')) {
            /**
             * @psalm-suppress UndefinedClass
             */
            $margin = \Endroid\QrCode\RoundBlockSizeMode::Margin;
        } else {
            throw new \InvalidArgumentException("Endroid QR Code library issue - can't figure out Margin.");
        }

        /**
         * @psalm-suppress TooManyArguments
         */
        $encoding = new Encoding('UTF-8');

        $QRresult = Builder::create()
            ->writer(new PngWriter())
            ->writerOptions([])
            ->data($QR_content)
            ->encoding($encoding)
            ->errorCorrectionLevel($level)
            ->size(300)
            ->margin(10)
            ->roundBlockSizeMode($margin)
            ->validateResult(false)
            ->build();

        $qr_code = base64_encode($QRresult->getString());

        return [$pTOTP_secret, $qr_code];
    }

    /**
     * Check if a user has TOTP enabled
     *
     * @param string $username
     *
     * @return bool True if the user has TOTP enabled, false otherwise
     */
    public function usesTOTP(string $username): bool
    {
        // Check if TOTP is globally enabled
        if (!(Config::read('totp') == 'YES')) {
            return false;
        }

        // Get the table name and prepare the query
        $table_name = table_by_key($this->table);
        $sql = "SELECT totp_secret FROM $table_name WHERE username = :username AND active = :active";

        $active = db_get_boolean(true);

        $values = [
            'username' => $username,
            'active' => $active,
        ];

        // Execute the query and check if the user has a TOTP secret
        $result = db_query_one($sql, $values);
        return (is_array($result) && isset($result['totp_secret']) && !empty($result['totp_secret']));
    }

    /**
     * Check if a TOTP code is valid for a user
     */
    public function checkUserTOTP(string $username, string $code): bool
    {
        $table_name = table_by_key($this->table);
        $sql = "SELECT totp_secret FROM $table_name WHERE username = :username AND active = :active";

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
     * Check if a TOTP code is valid for a given secret
     *
     * @param string $secret TOTP secret
     * @param string $code TOTP code to verify
     *
     * @return bool True if the code is valid, false otherwise
     */
    public function checkTOTP(string $secret, string $code): bool
    {
        $totp = TOTP::create($secret);
        return $totp->now() == $code;
    }

    public function removeTotpFromUser(string $username): void
    {
        $table_name = table_by_key($this->table);
        $sql = "UPDATE $table_name SET totp_secret = NULL WHERE username = :username";
        db_execute($sql, ['username' => $username]);
    }


    /**
     * Change the TOTP secret for a user after verifying their password
     *
     * @param string $username Username to change the TOTP secret for
     * @param string|null $TOTP_secret New TOTP secret or null to disable TOTP
     * @param string $password User's password for verification
     *
     * @return bool True on success
     * @throws \Exception If the password is incorrect, database update fails, or post-change script fails
     */
    public function changeTOTP_secret(string $username, ?string $TOTP_secret, string $password): bool
    {
        list(/*NULL*/, $domain) = explode('@', $username);

        /* should we need to do a login check here, if it's an admin removing the TOTP requirement */
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

        // Check if a post-change script is configured
        $cmd_pw = Config::read('mailbox_post_TOTP_change_secret_script');

        if (empty($cmd_pw)) {
            return true;
        }

        $warnmsg_pw = Config::Lang('mailbox_post_TOTP_change_failed');

        // Execute the post-change script
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

        // Write secret through pipe to command stdin
        if ($TOTP_secret !== null) {
            fwrite($pipes[0], $TOTP_secret . "\0", 1 + strlen($TOTP_secret));
        } else {
            fwrite($pipes[0], "\0", 1);
        }

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
     * Add a TOTP exception for a specific IP address
     *
     * This allows a user to bypass TOTP verification when connecting from the specified IP address.
     * Different user roles have different permissions:
     * - Regular users can only add exceptions for themselves
     * - Admins can add exceptions for users in domains they manage
     * - Global admins can add exceptions for any user
     *
     * @param string $username Username of the authenticated user adding the exception
     * @param string $password Password of the authenticated user
     * @param string $ip_address IP address to exempt from TOTP verification
     * @param string $exception_username Username or domain to apply the exception to
     * @param string $exception_description Description of why this exception exists
     *
     * @return bool True on success
     * @throws \Exception If authentication fails, validation fails, or database update fails
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
            // Get domains the admin has access to
            $domains = list_domains_for_admin($username);

            if (strpos($exception_username, '@')) {
                list($local_part, $Exception_domain) = explode('@', $exception_username);
            } else {
                // assume domain
                $Exception_domain = $exception_username;
            }

            // Ensure admin has access to the domain of the exception
            if ($exception_username != $username && !in_array($Exception_domain, $domains)) {
                throw new \Exception(Config::Lang('pException_user_entire_domain_error'));
            }
        } elseif (authentication_has_role('global-admin')) {
            $admin = 2;
            // Global admins can do anything
        } else {
            // Regular users can only add exceptions for themselves
            $exception_username = $username;
            $admin = 0;
        }

        // Validate inputs
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

        // Regular users can only add exceptions within a domain
        if ($admin === 0 && strpos($exception_username, '@') == false) {
            $error++;
            flash_error(Config::Lang('pException_user_entire_domain_error'));
        }

        // Only global admins can add an exception for any one
        if (!($admin === 2) && $exception_username == null) {
            $error++;
            flash_error(Config::Lang('pException_user_global_error'));
        }

        // Prepare values for database
        $values = [
            'ip' => $ip_address,
            'username' => $exception_username,
            'description' => $exception_description
        ];

        // Only proceed if there are no validation errors
        if ($error == 0) {
            $totp_exception_address = table_by_key('totp_exception_address');

            // As PostgreSQL lacks REPLACE, first delete any existing exceptions with the same IP and username
            $exists = db_query_all(
                "SELECT id FROM $totp_exception_address WHERE ip = :ip AND username = :username",
                ['ip' => $ip_address, 'username' => $exception_username]
            );

            if (isset($exists[0])) {
                foreach ($exists as $x) {
                    db_delete('totp_exception_address', 'id', $x['id']);
                }
            }

            // Insert the new exception
            $result = db_insert('totp_exception_address', $values, []);
        }

        // Check if the insert was successful
        if (!isset($result) || $result != 1) {
            db_log($domain, 'add_totp_exception', "FAILURE: " . $username);
            throw new \Exception(Config::lang('pEdit_totp_exception_result_error'));
        }

        // Run post-exception-add script if configured
        $cmd_pw = Config::read('mailbox_post_totp_exception_add_script');
        if (empty($cmd_pw)) {
            return true;
        }

        $warnmsg_pw = Config::Lang('mailbox_post_totp_exception_add_failed');

        // Use proc_open call to avoid safe_mode problems and to prevent showing sensitive data in process table
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
     * Delete a TOTP exception
     *
     * Different user roles have different permissions:
     * - Regular users can only delete their own exceptions
     * - Admins can delete exceptions for users in domains they manage, or delete a domain wide on for their domain.
     * - Global admins can delete any exception
     *
     * @param string $username Username of the authenticated user deleting the exception
     * @param int $id ID of the exception to delete
     *
     * @return bool True on success
     * @throws \InvalidArgumentException If the exception doesn't exist
     * @throws \Exception If the user doesn't have permission or the deletion fails
     */
    public function deleteException(string $username, int $id): bool
    {
        // Get the exception details
        $exception = $this->getException($id);

        if (!is_array($exception)) {
            throw new \InvalidArgumentException("Invalid exception - does id: $id exist?");
        }

        // Extract domain from exception username
        if (strpos($exception['username'], '@')) {
            list($Exception_local_part, $Exception_domain) = explode('@', $exception['username']);
        } else {
            $Exception_domain = $exception['username'];
        }

        // Check permissions based on user role
        if (authentication_has_role('global-admin')) {
            // Global admins can delete any exception
        } elseif (authentication_has_role('admin')) {
            // Admins can delete exceptions for users in domains they manage
            $domains = list_domains_for_admin($username);

            // If the exception is not for the current user, ensure it's for a domain they manage
            if ($exception['username'] != $username && !in_array($Exception_domain, $domains)) {
                throw new \Exception(Config::Lang('pException_user_entire_domain_error'));
            }
        } else {
            // Regular users can only delete their own exceptions
            if ($exception['username'] != $username) {
                throw new \Exception(Config::lang('pEdit_totp_exception_result_error'));
            }
        }

        // Delete the exception from the database
        $totp_exception_address = table_by_key('totp_exception_address');
        $result = db_execute("DELETE FROM $totp_exception_address WHERE id = :id", ['id' => $id]);

        if ($result != 1) {
            db_log($Exception_domain, 'pViewlog_action_delete_totp_exception', "FAILURE: " . $username);
            throw new \Exception(Config::lang('pEdit_totp_exception_result_error'));
        }

        // Run post-exception-delete script if configured
        $cmd_pw = Config::read('mailbox_post_totp_exception_delete_script');
        if (empty($cmd_pw)) {
            return true;
        }

        $warnmsg_pw = Config::Lang('mailbox_post_totp_exception_delete_failed');

        // Use proc_open call to avoid safe_mode problems and to prevent showing sensitive data in process table
        $spec = [
            0 => ["pipe", "r"], // stdin
            1 => ["pipe", "w"], // stdout
        ];

        $cmdarg1 = escapeshellarg($username);
        $cmdarg2 = escapeshellarg($exception['ip']);
        $command = "$cmd_pw $cmdarg1 $cmdarg2 2>&1";

        $proc = proc_open($command, $spec, $pipes);
        if (!$proc) {
            throw new \Exception("can't proc_open $cmd_pw");
        }

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
     * Get all TOTP exceptions in the system
     *
     * @return array List of all TOTP exceptions
     */
    public function getAllExceptions(): array
    {
        $totp_exception_address = table_by_key('totp_exception_address');
        return db_query_all("SELECT * FROM $totp_exception_address");
    }

    /**
     * Get all TOTP exceptions that apply to a specific username
     *
     * This includes exceptions for:
     * - The specific username
     * - The domain part of the username
     * - Global exceptions (where username is NULL)
     *
     * @param string $username Username to get exceptions for
     *
     * @return array List of exceptions that apply to the username
     */
    public function getExceptionsFor(string $username): array
    {
        list($local_part, $domain) = explode('@', $username);
        $totp_exception_address = table_by_key('totp_exception_address');

        return db_query_all(
            "SELECT * FROM $totp_exception_address WHERE username = :username OR username = :domain OR username IS NULL",
            ['username' => $username, 'domain' => $domain]
        );
    }

    /**
     * Get a specific TOTP exception by ID
     *
     * @param int $id ID of the exception to retrieve
     *
     * @return array|null The exception data or null if not found
     */
    public function getException(int $id): ?array
    {
        $totp_exception_address = table_by_key('totp_exception_address');
        return db_query_one("SELECT * FROM $totp_exception_address WHERE id = :id", ['id' => $id]);
    }
}
