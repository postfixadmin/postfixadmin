<?php

# $Id$

/**
 * Handler for domain key signing table
 */
class DkimsigningHandler extends PFAHandler
{
    protected $db_table = 'dkim_signing';
    protected $id_field = 'id';
    protected $order_by = 'dkim_id, author';

    protected function initStruct()
    {

        // Get Domains as options for authors
        $domain_handler = new DomainHandler(0, $this->admin_username);
        $domain_handler->getList('1=1');

        // Get Mailboxes as options for authors
        $mail_handler = new MailboxHandler(0, $this->admin_username);
        $mail_handler->getList('1=1');

        // Get Domain Keys
        $dkim_handler = new DkimHandler(0, $this->admin_username);
        $dkim_handler->getList('1=1');

        $authors = array_merge(
            array_keys($domain_handler->result()),
            array_keys($mail_handler->result())
        );

        $this->struct = array(
            # field name                allow       display in...   type        $PALANG label           $PALANG description       default / options / ...
            #                           editing?    form    list
            'id'               => pacol(0,          0,      1,      'num'     , 'pFetchmail_field_id' , ''                         , '', array(), array('dont_write_to_db' => 1)),
            'dkim_id'          => pacol(1,          1,      1,      'enum'    , 'pDkim_field_dkim_id' , 'pDkim_field_dkim_id_desc' , '', array_keys($dkim_handler->result)),
            'author'           => pacol(1,          1,      1,      'enum'    , 'pDkim_field_author'  , 'pDkim_field_author_desc'  , '', $authors),
        );
    }

    protected function initMsg()
    {
        $this->msg['error_already_exists'] = 'dkim_signing_already_exists';
        $this->msg['error_does_not_exist'] = 'dkim_signing_does_not_exist';
        $this->msg['confirm_delete'] = 'confirm_delete_dkim';

        if ($this->new) {
            $this->msg['logname'] = 'create_dkim_signing_entry';
            $this->msg['store_error'] = 'pFetchmail_database_save_error';
            $this->msg['successmessage'] = 'pFetchmail_database_save_success';
        } else {
            $this->msg['logname'] = 'edit_dkim_entry';
            $this->msg['store_error'] = 'pFetchmail_database_save_error';
            $this->msg['successmessage'] = 'pFetchmail_database_save_success';
        }
    }

    public function webformConfig()
    {
        $required_role = 'global-admin';
        if (Config::bool('dkim_all_admins')) {
            $required_role = 'admin';
        }

        return array(
            # $PALANG labels
            'formtitle_create' => 'pDkim_new_sign',
            'formtitle_edit' => 'pDkim_edit_sign',
            'create_button' => 'pFetchmail_new_entry',

            # various settings
            'required_role' => $required_role,
            'listview' => 'list.php?table=dkimsigning',
            'early_init' => 0,
        );
    }
    protected function validate_new_id()
    {
        # auto_increment - any non-empty ID is an error
        if ($this->id != '') {
            $this->errormsg[$this->id_field] = 'auto_increment value, you must pass an empty string!';
            return false;
        }

        return true;
    }

    /**
     *  @return boolean
     */
    public function delete()
    {
        if (! $this->view()) {
            $this->errormsg[] = Config::Lang($this->msg['error_does_not_exist']);
            return false;
        }

        db_delete($this->db_table, $this->id_field, $this->id);

        db_log($this->result['author'], 'delete_dkim_signing_entry', $this->result['id']);
        $this->infomsg[] = Config::Lang_f('pDelete_delete_success', $this->result['author']);
        return true;
    }

    public function domain_from_id()
    {
        return '';
    }

    protected function no_domain_field()
    {
        $domain_handler = new DomainHandler(0, $this->admin_username);
        $domain_handler->getList('1=1');

        $this->allowed_domains = array_keys($domain_handler->result());
    }

    /**
     * Filters to only allowed domains, as author can be either a mailbox or a domain
     * @param $db_result
     * @return array
     */
    protected function read_from_db_postprocess($db_result)
    {
        return array_filter($db_result, function ($row) {
            $domain = $row['author'];
            $at_pos = strpos($domain, '@');

            if ($at_pos) {
                $domain = preg_split('/@/', $domain)[1];
            }

            return in_array($domain, $this->allowed_domains);
        });
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
