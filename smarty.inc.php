<?php
require_once ("$incpath/smarty/libs/Smarty.class.php");

/**
 * Turn on sanitisation of all data by default so it's not possible for XSS flaws to occur in PFA
 */
class PFASmarty extends Smarty {
    public function assign($key, $value, $sanitise = true) {
        if($sanitise == false) {
            return parent::assign($key, $value);
        }
        $clean = $this->sanitise($value);
        /* we won't run the key through sanitise() here... some might argue we should */
        return parent::assign($key, $clean);
    }

    /**
     * Recursive cleaning of data, using htmlentities - this assumes we only ever output to HTML and we're outputting in UTF-8 charset 
     *
     * @param mixed $data - array or primitive type; objects not supported.
     * @return mixed $data
     * */
    public function sanitise($data) {
        if(!is_array($data)) {
            return htmlentities($data, ENT_QUOTES, 'UTF-8');
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

//$smarty->debugging = true;

$smarty->template_dir	= $incpath.'/templates';
$smarty->compile_dir	= $incpath.'/templates_c';
$smarty->config_dir	= $incpath.'/'.$smarty->config_dir;

$CONF['theme_css']	= $CONF['postfix_admin_url'].'/'.htmlentities($CONF['theme_css']);
$CONF['theme_logo']	= $CONF['postfix_admin_url'].'/'.htmlentities($CONF['theme_logo']);

$smarty->assign ('CONF', $CONF);
$smarty->assign ('PALANG', $PALANG);

//*** footer.tpl
$smarty->assign ('version', $version);

//*** menu.tpl
$smarty->assign ('boolconf_alias_domain', boolconf('alias_domain'));
$smarty->assign ('authentication_has_role', array ('global_admin' => authentication_has_role ('global-admin'), 'admin' => authentication_has_role ('admin'), 'user' => authentication_has_role ('user')));

if (authentication_has_role('global-admin'))
{
	$motd_file = "motd-admin.txt";
}
else
{
	$motd_file = "motd.txt";
}
if (file_exists ($CONF ['postfix_admin_path'].'/templates/'.$motd_file)) {
    $smarty->assign ('motd_file', $motd_file);
}

function select_options($aValues, $aSelected)
{
	$ret_val = '';
	foreach ($aValues as $val)
	{
		$ret_val .= '<option value="'.$val.'"';
		if (in_array ($val, $aSelected))
			$ret_val .= ' selected="selected"';
		$ret_val .= '>'.$val.'</option>';
	}
	return $ret_val;
}
function eval_size ($aSize)
{
	if ($aSize == 0)	{$ret_val = $GLOBALS ['PALANG']['pOverview_unlimited'];	}
	elseif ($aSize < 0)	{$ret_val = $GLOBALS ['PALANG']['pOverview_disabled'];	}
	else 				{$ret_val = $aSize;	}
	return $ret_val;
}
?>
