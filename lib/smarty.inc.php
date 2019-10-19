<?php

if (!isset($CONF) || !isset($PALANG)) {
    die("environment not setup correctly");
}

require_once(dirname(__FILE__) . '/smarty/libs/Autoloader.php');

require_once(dirname(__FILE__) . '/PFASmarty.php');

Smarty_Autoloader::register();

if (isset($CONF['theme']) && is_dir(dirname(__FILE__) . "/../templates/" . $CONF['theme'])) {
    $smarty = new PFASmarty($CONF['theme']);
} else {
    $smarty = new PFASmarty();
}

if (!isset($rel_path)) {
    $rel_path = '';
} # users/* sets this to '../'

$CONF['theme_css'] = $rel_path . htmlentities($CONF['theme_css']);
if (!empty($CONF['theme_custom_css'])) {
    $CONF['theme_custom_css'] = $rel_path . htmlentities($CONF['theme_custom_css']);
}
$CONF['theme_favicon']  = $rel_path . htmlentities($CONF['theme_favicon']);
$CONF['theme_logo'] = $rel_path . htmlentities($CONF['theme_logo']);

$smarty->assign('CONF', $CONF);
$smarty->assign('PALANG', $PALANG);
$smarty->assign('url_domain', '');
//*** footer.tpl
if (!isset($version)) {
    $version = 'dev/unknown';
}
$smarty->assign('version', $version);

//*** menu.tpl
$smarty->assign('boolconf_alias_domain', Config::bool('alias_domain'));
$smarty->assign('authentication_has_role', array('global_admin' => authentication_has_role('global-admin'), 'admin' => authentication_has_role('admin'), 'user' => authentication_has_role('user')));

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
