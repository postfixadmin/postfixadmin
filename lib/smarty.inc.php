<?php

require_once(dirname(__FILE__) . '/smarty/libs/Autoloader.php');
Smarty_Autoloader::register();

/**
 * Turn on sanitisation of all data by default so it's not possible for XSS flaws to occur in PFA
 */
class PFASmarty {
    protected $template = null;
    public function __construct() {
        $this->template = new Smarty();

        //$this->template->debugging = true;
        $this->template->setTemplateDir(dirname(__FILE__) . '/../templates');

        // if it's not present or writeable, smarty should just not cache.
        $templates_c = dirname(__FILE__) . '/../templates_c';
        if (is_dir($templates_c) && is_writeable($templates_c)) {
            $this->template->setCompileDir($templates_c);
        } else {
            # unfortunately there's no sane way to just disable compiling of templates
            clearstatcache(); // just incase someone just fixed it; on their next refresh it should work.
            error_log("ERROR: directory $templates_c doesn't exist or isn't writeable for the webserver");
            die("ERROR: the templates_c directory doesn't exist or isn't writeable for the webserver");
        }

        $this->template->setConfigDir(dirname(__FILE__) . '/../configs');
    }

    public function assign($key, $value, $sanitise = true) {
        $this->template->assign("RAW_$key", $value);
        if ($sanitise == false) {
            return $this->template->assign($key, $value);
        }
        $clean = $this->sanitise($value);
        /* we won't run the key through sanitise() here... some might argue we should */
        return $this->template->assign($key, $clean);
    }

    public function display($template) {
        header("Expires: Sun, 16 Mar 2003 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        header("Content-Type: text/html; charset=UTF-8");

        $this->template->display($template);
        unset($_SESSION['flash']); # cleanup flash messages
    }
    /**
     * Recursive cleaning of data, using htmlentities - this assumes we only ever output to HTML and we're outputting in UTF-8 charset
     *
     * @param mixed $data - array or primitive type; objects not supported.
     * @return mixed $data
     * */
    public function sanitise($data) {
        if (!is_array($data)) {
            return htmlentities($data, ENT_QUOTES, 'UTF-8', false);
        }
        if (is_array($data)) {
            $clean = array();
            foreach ($data as $key => $value) {
                /* as this is a nested data structure it's more likely we'll output the key too (at least in my opinion, so we'll sanitise it too */
                $clean[$this->sanitise($key)] = $this->sanitise($value);
            }
            return $clean;
        }
    }
}
$smarty = new PFASmarty();

if (!isset($rel_path)) {
    $rel_path = '';
} # users/* sets this to '../'

$CONF['theme_css']  = $rel_path . htmlentities($CONF['theme_css']);
if (!empty($CONF['theme_custom_css'])) {
    $CONF['theme_custom_css']  = $rel_path . htmlentities($CONF['theme_custom_css']);
}
$CONF['theme_logo'] = $rel_path . htmlentities($CONF['theme_logo']);

$smarty->assign('CONF', $CONF);
$smarty->assign('PALANG', $PALANG);
$smarty->assign('url_domain', '');
//*** footer.tpl
$smarty->assign('version', $version);

//*** menu.tpl
$smarty->assign('boolconf_alias_domain', Config::bool('alias_domain'));
$smarty->assign('authentication_has_role', array('global_admin' => authentication_has_role('global-admin'), 'admin' => authentication_has_role('admin'), 'user' => authentication_has_role('user')));

function eval_size($aSize) {
    if ($aSize == 0) {
        $ret_val = Config::Lang('pOverview_unlimited');
    } elseif ($aSize < 0) {
        $ret_val = Config::Lang('pOverview_disabled');
    } else {
        $ret_val = $aSize;
    }
    return $ret_val;
}
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
