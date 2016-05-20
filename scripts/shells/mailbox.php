<?php


class PasswordTask extends Shell {
    /**
     * Execution method always used for tasks
     *
     * @access public
     */
    function execute() {
        $random = false;
        if (empty($this->args)) {
            $this->__interactive();
        }

        if (!empty($this->args[0])) {

            $address = $this->args[0];

            if (isset($this->params['g']) && $this->params['g'] == true ) {
                $random = true;
                $password = NULL;
            } elseif  (isset($this->args[1]) && strlen($this->args[1]) > 8) { # TODO use validate_password()
                $password = $this->args[1];
            } else {

                $this->Dispatch->stderr('Missing <newpw> or -g. Falling back to interactive mode.');
                $this->__interactive();
            }
            $this->__handle($address, $password, $random);


        }
    }

    /**
     * Interactive
     */
    private function __interactive() {

        while(true) {
            $question = "Which address' password do you want to change?";
            $address = $this->in($question);

            if(filter_var($address, FILTER_VALIDATE_EMAIL)) {
                break;
            }
            $this->err("Invalid emailaddress");
        }


        $question2[] = "Do you want to change the password?";
        $question2[] = "Are you really sure?";
        $sure = $this->in(join("\n", $question2), array('y','n'));


        if ($sure == 'n' ) {
            $this->out('You\'re not sure.');
            $this->_stop();
        }

        $question = "Do you want to generate a random password?";
        $random = $this->in($question, array('y','n'));

        $random == 'y' ? $random = true : $random = false;


        $password = NULL;
        if ($random == false) {
            $question = "Pleas enter the new password?";
            $password = $this->in($question);
        }

        $this->__handle($address, $password, $random);

    }

    /**
     * @param string $address email adress
     * @param string $password optional
     * @param boolean $random optional - true to generate random pw.
     */
    private function __handle($address, $password = NULL, $random = false) {

        if ($random == true) {
            $password = generate_password();
        }
        if ($password != NULL) {
            $handler =  new MailboxHandler();

            if (!$handler->init($address)) {
                $this->error("Change Password",join("\n", $handler->errormsg));
            }

            if ( ! $handler->change_pw($password, NULL, false) ){
                $this->error("Change Password",join("\n", $handler->errormsg));
            }
        }

        $this->out("");
        $this->out("Password updated.");
        $this->hr();
        $this->out(sprintf('The Mail address is  %20s', $address));
        $this->out(sprintf('The new password is %20s',$password));
        $this->hr();

        return ;
    }

    /**
     * Displays help contents
     *
     * @access public
     */
    public function help() {
        $this->out("");
        $this->hr();
        $this->out("Usage: postfixadmin-cli mailbox password <address> [<newpw>] [-g]");
        $this->hr();
        $this->out('Commands:');
        $this->out("\n\tpassword\n\t\tchanges the password in interactive mode.");
        $this->out("\n\tpassword <address> [<newpw>] [-g]\n\t\tchanges the password to <newpw> or if -g genereate a new pw for <address>");
        $this->out("");
        $this->_stop();
    }
}
