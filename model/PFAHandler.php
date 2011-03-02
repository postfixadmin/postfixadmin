<?php
class PFAHandler {
    function _inp_num($val) {
        return (int)($val);
    }

    function _inp_bool($val) {
        return $val ? db_get_boolean(true): db_get_boolean(false);
    }

    function _inp_password($val){
        # TODO: fetchmail specific. Not suited for mailbox/admin passwords.
        return base64_encode($val);
    }
}
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
