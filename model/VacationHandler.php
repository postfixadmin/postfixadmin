<?php
# $Id$

class VacationHandler extends PFAHandler {
    protected $db_table = 'vacation';
    protected $id_field = 'email';
    protected $domain_field = 'domain';

    public function init($id) {
        die('VacationHandler is not yet ready to be used with *Handler methods'); # obvious TODO: remove when it's ready ;-)
    }

    protected function initStruct() {
        $this->struct=array(
            # field name                allow       display in...   type    $PALANG label                     $PALANG description                 default / options / ...
            #                           editing?    form    list
            'email'         => pacol($this->new, 1, 1, 'text', 'pLogin_username', '', ''),
            'domain'        => pacol(1, 0, 0, 'text', '', '', ''),
            'subject'       => pacol(1, 1, 0, 'text', 'pUsersVacation_subject', '', ''),
            'body'          => pacol(1, 1, 0, 'text', 'pUsersVacation_body', '', ''),
            'activefrom'    => pacol(1, 1, 1, 'text', 'pUsersVacation_activefrom', '', ''),
            'activeuntil'   => pacol(1, 1, 1, 'text', 'pUsersVacation_activeuntil', '', ''),
            'active'        => pacol(1, 1, 1, 'bool', 'active', '', 1),
            'created'       => pacol(0, 0, 1, 'ts', 'created', ''),
            'modified'      => pacol(0, 0, 1, 'ts', 'last_modified', ''),
            # TODO: add virtual 'notified' column and allow to display who received a vacation response?
        );

        if (! db_pgsql()) {
            $this->struct['cache'] = pacol(0, 0, 0, 'text', '', '', '');  # leftover from 2.2
        }
    }

    protected function initMsg() {
        $this->msg['error_already_exists'] = 'pCreate_mailbox_username_text_error1'; # TODO: better error message
        $this->msg['error_does_not_exist'] = 'pCreate_mailbox_username_text_error1'; # TODO: better error message
        $this->msg['confirm_delete'] = 'confirm_delete_vacation'; # unused?

        if ($this->new) {
            $this->msg['logname'] = 'edit_vacation';
            $this->msg['store_error'] = 'pVacation_result_error';
            $this->msg['successmessage'] = 'pVacation_result_removed'; # TODO: or pVacation_result_added - depends on 'active'... -> we probably need a new message
        } else {
            $this->msg['logname'] = 'edit_vacation';
            $this->msg['store_error'] = 'pVacation_result_error';
            $this->msg['successmessage'] = 'pVacation_result_removed'; # TODO: or pVacation_result_added - depends on 'active'... -> we probably need a new message
        }
    }

    public function webformConfig() {
        return array(
            # $PALANG labels
            'formtitle_create' => 'pUsersVacation_welcome',
            'formtitle_edit' => 'pUsersVacation_welcome',
            'create_button' => 'save',

            # various settings
            'required_role' => 'admin',
            'listview' => 'list-virtual.php',
            'early_init' => 1, # 0 for create-domain
        );
    }

    protected function validate_new_id() {
        # vacation can only be enabled if a mailbox with this name exists
        $handler = new MailboxHandler();
        return $handler->init($address);
    }

    public function delete() {
        $this->errormsg[] = '*** deletion not implemented yet ***';
        return false; # XXX function aborts here! XXX
    }




    protected $username = null;
    public function __construct($username) {
        $this->username = $username;
        $this->id = $username;
    }

    /**
     * Removes the autoreply alias etc for this user; namely, if they're away we remove their vacation alias and
     * set the vacation table record to false.
     * @return boolean true on success.
     */
    public function remove() {
        if (!$this->updateAlias(0)) {
            return false;
        }

        // tidy up vacation table.
        $vacation_data = array(
            'active' => db_get_boolean(false),
        );
        $result = db_update('vacation', 'email', $this->username, $vacation_data);
        $result = db_delete('vacation_notification', 'on_vacation', $this->username);
        # TODO db_log() call (maybe except if called from set_away?)
        /* crap error handling; oh for exceptions... */
        return true;
    }

    /**
     * @return boolean true indicates this server supports vacation messages, and users are able to change their own.
     */
    public function vacation_supported() {
        return Config::bool('vacation') && Config::bool('vacation_control');
    }

    /**
     * @return boolean true if on vacation, otherwise false
     * Why do we bother storing true/false in the vacation table if the alias dictates it anyway?
     */
    public function check_vacation() {
        $handler = new AliasHandler();

        if (!$handler->init($this->id)) {
            # print_r($handler->errormsg); # TODO: error handling
            return false;
        }

        if (!$handler->view()) {
            # print_r($handler->errormsg); # TODO: error handling
            return false;
        }

        $result = $handler->result();

        if ($result['on_vacation']) {
            return true;
        }
        return false;
    }

    /**
     * Retrieve information on someone who is on vacation
     * @return struct|boolean stored information on vacation - array(subject - string, message - string, active - boolean, activeFrom - date, activeUntil - date)
     * will return false if no existing data
     */
    public function get_details() {
        $table_vacation = table_by_key('vacation');
        $E_username = escape_string($this->username);

        $sql = "SELECT * FROM $table_vacation WHERE email = '$E_username'";
        $result = db_query($sql);
        if ($result['rows'] != 1) {
            return false;
        }

        $row = db_array($result['result']);
        $boolean = ($row['active'] == db_get_boolean(true));
        # TODO: only return true and store the db result array in $this->whatever for consistency with the other classes
        return array(
            'subject' => $row['subject'],
            'body' => $row['body'],
            'active'  => $boolean ,
            'interval_time' => $row['interval_time'],
            'activeFrom' => $row['activefrom'],
            'activeUntil' => $row['activeuntil'],
        );
    }
    /**
     * @param string $subject
     * @param string $body
     * @param string $interval_time
     * @param date $activeFrom
     * @param date $activeUntil
     */
    public function set_away($subject, $body, $interval_time, $activeFrom, $activeUntil) {
        $this->remove(); // clean out any notifications that might already have been sent.

        $E_username = escape_string($this->username);
        $activeFrom = date("Y-m-d 00:00:00", strtotime($activeFrom)); # TODO check if result looks like a valid date
        $activeUntil = date("Y-m-d 23:59:59", strtotime($activeUntil)); # TODO check if result looks like a valid date
        list(/*NULL*/, $domain) = explode('@', $this->username);

        $vacation_data = array(
            'email' => $this->username,
            'domain' => $domain,
            'subject' => $subject,
            'body' => $body,
            'interval_time' => $interval_time,
            'active' => db_get_boolean(true),
            'activefrom' => $activeFrom,
            'activeuntil' => $activeUntil,
        );

        if (! db_pgsql()) {
            $vacation_data['cache'] = '';  # leftover from 2.2
        }

        // is there an entry in the vacaton table for the user, or do we need to insert?
        $table_vacation = table_by_key('vacation');
        $result = db_query("SELECT * FROM $table_vacation WHERE email = '$E_username'");
        if ($result['rows'] == 1) {
            $result = db_update('vacation', 'email', $this->username, $vacation_data);
        } else {
            $result = db_insert('vacation', $vacation_data);
        }
        # TODO error check
        # TODO wrap whole function in db_begin / db_commit (or rollback)?

        return $this->updateAlias(1);
    }

    /**
     * add/remove the vacation alias
     * @param int $vacationActive
     */
    protected function updateAlias($vacationActive) {
        $handler = new AliasHandler();

        if (!$handler->init($this->id)) {
            # print_r($handler->errormsg); # TODO: error handling
            return false;
        }

        $values = array(
            'on_vacation' => $vacationActive,
        );

        if (!$handler->set($values)) {
            # print_r($handler->errormsg); # TODO: error handling
            return false;
        }

        # TODO: supress logging in AliasHandler if called from VacationHandler (VacationHandler should log itsself)

        if (!$handler->store()) {
            print_r($handler->errormsg); # TODO: error handling
            return false;
        }

        # still here? then everything worked
        return true;
    }
}
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
