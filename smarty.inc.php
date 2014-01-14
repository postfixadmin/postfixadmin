<?php
require_once ("$incpath/smarty/libs/Smarty.class.php");

/**
 * Turn on sanitisation of all data by default so it's not possible for XSS flaws to occur in PFA
 */
class PFASmarty {
    protected $template = null;
    public function __construct() {
        $this->template = new Smarty();

        //$this->template->debugging = true;
        $incpath = dirname(__FILE__);
        $this->template->template_dir = $incpath.'/templates';
        $this->template->compile_dir  = $incpath.'/templates_c';
        $this->template->config_dir   = $incpath.'/'.$this->template->config_dir[0];
    }

    public function assign($key, $value, $sanitise = true) {
        if($sanitise == false) {
            return $this->template->assign($key, $value);
        }
        $clean = $this->sanitise($value);
        /* we won't run the key through sanitise() here... some might argue we should */
        return $this->template->assign($key, $clean);
    }

    public function display($template) {
        header ("Expires: Sun, 16 Mar 2003 05:00:00 GMT");
        header ("Last-Modified: " . gmdate ("D, d M Y H:i:s") . " GMT");
        header ("Cache-Control: no-store, no-cache, must-revalidate");
        header ("Cache-Control: post-check=0, pre-check=0", false);
        header ("Pragma: no-cache");
        header ("Content-Type: text/html; charset=UTF-8");

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
        if(!is_array($data)) {
            return htmlentities($data, ENT_QUOTES, 'UTF-8', false);
        }
        if(is_array($data)) {
            $clean = array();
            foreach($data as $key => $value) {
                /* as this is a nested data structure it's more likely we'll output the key too (at least in my opinion, so we'll sanitise it too */
                $clean[$this->sanitise($key)] = $this->sanitise($value);
            }
            return $clean;
        }
    }
}
$smarty = new PFASmarty();

if (!isset($rel_path)) $rel_path = ''; # users/* sets this to '../'

$CONF['theme_css']  = $rel_path . htmlentities($CONF['theme_css']);
if (!empty($CONF['theme_custom_css'])) $CONF['theme_custom_css']  = $rel_path . htmlentities($CONF['theme_custom_css']);
$CONF['theme_logo'] = $rel_path . htmlentities($CONF['theme_logo']);

$smarty->assign ('CONF', $CONF);
$smarty->assign ('PALANG', $PALANG);
$smarty->assign('url_domain', '');
//*** footer.tpl
$smarty->assign ('version', $version);

//*** menu.tpl
$smarty->assign ('boolconf_alias_domain', Config::bool('alias_domain'));
$smarty->assign ('authentication_has_role', array ('global_admin' => authentication_has_role ('global-admin'), 'admin' => authentication_has_role ('admin'), 'user' => authentication_has_role ('user')));

function select_options($aValues, $aSelected) {
    $ret_val = '';
    foreach ($aValues as $val) {
        $ret_val .= '<option value="'.htmlentities($val).'"';
        if (in_array ($val, $aSelected))
            $ret_val .= ' selected="selected"';
        $ret_val .= '>'.htmlentities($val).'</option>';
    }
    return $ret_val;
}
function eval_size ($aSize) {
	if ($aSize == 0)	{$ret_val = Config::Lang('pOverview_unlimited'); }
	elseif ($aSize < 0)	{$ret_val = Config::Lang('pOverview_disabled');  }
	else 				{$ret_val = $aSize;	}
	return $ret_val;
}
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
