<?php
# $Id$ 

class VacationHandler {
    protected $username = null;
    function __construct($username) {
        $this->username = $username;
        $this->id = $username;
    }

    /**
     * Removes the autoreply alias etc for this user; namely, if they're away we remove their vacation alias and 
     * set the vacation table record to false.
     * @return boolean true on success.
     */
    function remove() {
        if (!$this->updateAlias(0)) return false;

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
     * @global array $CONF
     */
    function vacation_supported() {
        global $CONF;
        return $CONF['vacation'] == 'YES' && $CONF['vacation_control'] == 'YES';
    }

    /**
     * @return boolean true if on vacation, otherwise false
     * Why do we bother storing true/false in the vacation table if the alias dictates it anyway?
     */
    function check_vacation() {
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

        if ($result['on_vacation']) return true;
        return false;
    }

    /**
     * Retrieve information on someone who is on vacation
     * @return struct|boolean stored information on vacation - array(subject - string, message - string, active - boolean, activeFrom - date, activeUntil - date) 
     * will return false if no existing data 
     */
    function get_details() {
        $table_vacation = table_by_key('vacation');
        $E_username = escape_string($this->username);

        $sql = "SELECT * FROM $table_vacation WHERE email = '$E_username'";
        $result = db_query($sql);
        if($result['rows'] != 1) {
            return false;
        }

        $row = db_array($result['result']);
        $boolean = ($row['active'] == db_get_boolean(true));
        # TODO: only return true and store the db result array in $this->whatever for consistency with the other classes
        return array( 
            'subject' => $row['subject'],
            'body' => $row['body'],
            'active'  => $boolean ,
            'activeFrom' => $row['activefrom'],
            'activeUntil' => $row['activeuntil'],
        );
    }
    /**
     * @param string $subject
     * @param string $body
     * @param date $activeFrom
     * @param date $activeUntil
     */
    function set_away($subject, $body, $activeFrom, $activeUntil) {
        $this->remove(); // clean out any notifications that might already have been sent.

        $E_username = escape_string($this->username);
        $activeFrom = date ("Y-m-d 00:00:00", strtotime ($activeFrom)); # TODO check if result looks like a valid date
        $activeUntil = date ("Y-m-d 23:59:59", strtotime ($activeUntil)); # TODO check if result looks like a valid date
        list(/*NULL*/,$domain) = explode('@', $this->username);

        $vacation_data = array(
            'email' => $this->username,
            'domain' => $domain,
            'subject' => $subject,
            'body' => $body,
            'active' => db_get_boolean(true),
            'activefrom' => $activeFrom,
            'activeuntil' => $activeUntil,
        );

        // is there an entry in the vacaton table for the user, or do we need to insert?
        $table_vacation = table_by_key('vacation');
        $result = db_query("SELECT * FROM $table_vacation WHERE email = '$E_username'");
        if($result['rows'] == 1) {
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

        $values = array (
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

    /**
     * Returns the vacation alias for this user. 
     * i.e. if this user's username was roger@example.com, and the autoreply domain was set to
     * autoreply.fish.net in config.inc.php we'd return roger#example.com@autoreply.fish.net
     * @return string an email alias.
     */
    public function getVacationAlias() {
        global $CONF;
        $vacation_domain = $CONF['vacation_domain']; 
        $vacation_goto = str_replace('@', '#', $this->username); 
        $vacation_goto = "{$vacation_goto}@{$vacation_domain}"; 
        return $vacation_goto;
    }
}
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
