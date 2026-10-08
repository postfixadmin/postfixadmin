<?php

/**
 * Edit only the authenticated mailbox user's display name.
 */
class MailboxnameHandler extends MailboxHandler
{
    protected ?string $user_field = 'username';

    public static function userCanEditName(string $username): bool
    {
        $policy = Config::read('edit_mailbox_name');
        $separator = strrpos($username, '@');
        if ($separator === false) {
            return false;
        }
        if ($policy === 'YES') {
            return true;
        }
        $domain = substr(strtolower($username), $separator + 1);
        return is_array($policy) && in_array($domain, $policy, true);
    }

    protected function initStruct()
    {
        $this->struct = [
            'username' => self::pacol(0, 1, 1, 'text', 'mailbox', ''),
            'name' => self::pacol(1, 1, 1, 'text', 'name', 'pCreate_mailbox_name_text', ''),
            '_can_delete' => self::pacol(0, 0, 1, 'vnum', '', '', '', [], 0, 1, '0 as _can_delete'),
        ];
    }

    protected function initMsg()
    {
        parent::initMsg();
        $this->msg['can_create'] = false;
    }

    public function webformConfig()
    {
        return [
            'formtitle_create' => 'pUsersMenu_edit_name',
            'formtitle_edit' => 'pUsersMenu_edit_name',
            'create_button' => 'save',
            'required_role' => 'user',
            'listview' => 'users/main.php',
            'early_init' => 0,
            'user_hardcoded_field' => 'username',
        ];
    }

    private function canEditName(string $id): bool
    {
        if ($this->is_admin || $this->new || strtolower($id) !== strtolower($this->username)
            || !self::userCanEditName($this->username)) {
            $this->errormsg[] = Config::lang_f('edit_not_allowed', $id);
            return false;
        }
        return true;
    }

    public function init(string $id): bool
    {
        if (!$this->canEditName($id)) {
            return false;
        }
        // A name-only form does not initialize quota editing metadata.
        return PFAHandler::init($id);
    }

    protected function validate_new_id()
    {
        $this->errormsg[] = Config::lang_f('edit_not_allowed', $this->id);
        return false;
    }

    public function set(array $values)
    {
        if (!$this->canEditName($this->id) || !array_key_exists('name', $values)) {
            return false;
        }
        // Never let submitted or hook-added fields expand this operation's scope.
        return parent::set(['name' => $values['name']]);
    }

    protected function _validate_name($field, $value)
    {
        if (!is_string($value) || preg_match('/\A[^\p{Cc}]{0,255}\z/u', $value) !== 1) {
            $this->errormsg[$field] = Config::lang('pUsers_name_invalid');
            return false;
        }
        return true;
    }

    protected function preSave(): bool
    {
        // No alias or quota update is needed for a display-name change.
        $this->values = array_intersect_key($this->values, ['name' => true]);
        return $this->canEditName($this->id);
    }

    protected function postSave(): bool
    {
        // The existing post-edit script expects the stored quota in bytes.
        $mailbox = db_query_one('SELECT quota FROM ' . table_by_key('mailbox') . ' WHERE username = ?', [$this->id]);
        if ($mailbox === null) {
            $this->errormsg[] = Config::lang('mailbox_update_failed');
            return true; // The name is already saved, as with other post-edit warnings.
        }
        $this->values['quota'] = $mailbox['quota'];
        return parent::postSave();
    }

    public function delete()
    {
        $this->errormsg[] = Config::lang_f('edit_not_allowed', $this->id);
        return false;
    }
}
