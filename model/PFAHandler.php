<?php
class PFAHandler {

    /**
     * @return return value of previously called method
     */
    public function result() {
        return $this->return;
    }


    /**
      * functions for basic input validation
      */
    function _inp_num($field, $val) {
        $valid = is_numeric($val);
        if ($val < -1) $valid = false;
        if (!$valid) $this->errormsg[] = "$field must be numeric";
        return $valid;
        # return (int)($val);
    }

    function _inp_bool($field, $val) {
        if ($val == "0" || $val == "1") return true;
        $this->errormsg[] = "$field must be boolean";
        return false;
        # return $val ? db_get_boolean(true): db_get_boolean(false);
    }

    function _inp_enum($field, $val) {
        if(in_array($val, $this->struct[$field]['options'])) return true;
        $this->errormsg[] = "Invalid parameter given for $field";
        return false;
    }

    function _inp_password($field, $val){
        # TODO: fetchmail specific. Not suited for mailbox/admin passwords.
        $this->errormsg[] = "_inp_password not implemented yet";
        return false;
        # return base64_encode($val);
    }

}
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
