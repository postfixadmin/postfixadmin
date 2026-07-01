<?php

/**
 * Postfix Admin
 *
 * LICENSE
 * This source file is subject to the GPL license that is bundled with
 * this package in the file LICENSE.TXT.
 *
 * Further details on the project are available at https://github.com/postfixadmin/postfixadmin
 *
 * @version $Id$
 * @license GNU GPL v2 or later.
 *
 * File: settings.php
 * Per-admin settings. Currently lets the logged-in admin configure how the
 * "To" (goto) field of the add-alias form is prefilled.
 * Template File: settings.tpl
 *
 * Stored in the admin_preferences table via get_admin_pref() / set_admin_pref():
 *   alias_goto_prefill_mode  - one of 'none', 'login', 'custom'
 *   alias_goto_prefill_value - the custom value (only relevant for mode 'custom')
 */

require_once('common.php');

$username = authentication_get_username(); # enforce login
authentication_require_role('admin');

$CONF = Config::getInstance()->getAll();
$smarty = PFASmarty::getInstance();
$PALANG = $CONF['__LANG'];

$valid_modes = array('none', 'login', 'custom');

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    if (safepost('token') != $_SESSION['PFA_token']) {
        die('Invalid token!');
    }

    $mode = safepost('alias_goto_prefill_mode', 'none');
    if (!in_array($mode, $valid_modes, true)) {
        $mode = 'none';
    }

    $value = trim(safepost('alias_goto_prefill_value', ''));

    if ($mode === 'custom' && $value === '') {
        # custom mode requires a value - block saving (do not persist)
        flash_error($PALANG['settings_alias_prefill_custom_empty']);
    } else {
        if ($mode === 'custom') {
            # warn (but do not block) if the custom value does not match an existing mail user
            $hit = db_query_all(
                "SELECT 1 FROM " . table_by_key('mailbox') . " WHERE username = :username",
                array('username' => $value)
            );
            if (count($hit) === 0) {
                flash_error(Config::lang_f('settings_alias_prefill_warning_no_mailbox', $value));
            }
        }

        set_admin_pref($username, 'alias_goto_prefill_mode', $mode);
        set_admin_pref($username, 'alias_goto_prefill_value', $mode === 'custom' ? $value : '');

        flash_info($PALANG['settings_saved']);
    }
}

$smarty->assign('login_username', $username);
$smarty->assign('alias_goto_prefill_mode', get_admin_pref($username, 'alias_goto_prefill_mode', 'none'));
$smarty->assign('alias_goto_prefill_value', get_admin_pref($username, 'alias_goto_prefill_value', ''));
$smarty->assign('smarty_template', 'settings');
$smarty->display('index.tpl');

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
