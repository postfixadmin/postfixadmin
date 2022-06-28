<?php

/**
 * Turn on sanitisation of all data by default so it's not possible for XSS flaws to occur in PFA
 */
class PFASmarty {
    public static $instance = null;
    /**
     * @var Smarty
     */
    protected $template;

    public static function getInstance() {
        if (self::$instance) {
            return self::$instance;
        }

        self::$instance = new PFASmarty();
        return self::$instance;
    }


    private function __construct() {
        $CONF = Config::getInstance()->getAll();

        $theme = '';
        if (isset($CONF['theme']) && is_dir(dirname(__FILE__) . "/../templates/" . $CONF['theme'])) {
            $theme = $CONF['theme'];
        }

        $this->template = new Smarty();


        $template_dir = __DIR__ . '/../templates/' . $theme;

        if (!is_dir($template_dir)) {
            $template_dir = __DIR__ . '/../templates/';
        }

        $this->template->setTemplateDir($template_dir);

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

        $this->configureTheme('');// default to something.
    }

    /**
     * @param string $rel_path - relative path for referenced css etc dependencies - e.g. users/edit.php needs '../' else, it's ''.
     */
    public function configureTheme(string $rel_path = '') {
        $CONF = Config::getInstance()->getAll();

        // see: https://github.com/postfixadmin/postfixadmin/issues/410
        // ignore $CONF['theme_css'] if it points to css/default.css and we have css/bootstrap.css.
        if ($CONF['theme_css'] == 'css/default.css' && is_file(__DIR__ . '/../public/css/bootstrap.css')) {
            // silently upgrade to bootstrap, css/default.css does not exist.
            $CONF['theme_css'] = 'css/bootstrap.css';
        }

        $CONF['theme_css'] = $rel_path . htmlentities($CONF['theme_css']);
        if (!empty($CONF['theme_custom_css'])) {
            $CONF['theme_custom_css'] = $rel_path . htmlentities($CONF['theme_custom_css']);
        }
        if (array_key_exists('theme_favicon', $CONF)) {
            $CONF['theme_favicon'] = $rel_path . htmlentities($CONF['theme_favicon']);
        }

        $CONF['theme_logo'] = $rel_path . htmlentities($CONF['theme_logo']);

        $this->assign('rel_path', $rel_path);
        $this->assign('CONF', $CONF);
    }


    /**
     * @param string $key
     * @param mixed $value
     * @param bool $sanitise
     */
    public function assign($key, $value, $sanitise = true) {
        $this->template->assign("RAW_$key", $value);
        if ($sanitise == false) {
            return $this->template->assign($key, $value);
        }
        $clean = $this->sanitise($value);
        /* we won't run the key through sanitise() here... some might argue we should */
        return $this->template->assign($key, $clean);
    }

    /**
     * @param string $template
     * @return void
     */
    public function display($template) {
        $CONF = Config::getInstance()->getAll();

        $this->assign('PALANG', $CONF['__LANG'] ?? []);
        $this->assign('url_domain', '');
        $this->assign('version', $CONF['version'] ?? 'unknown');
        $this->assign('boolconf_alias_domain', Config::bool('alias_domain'));
        $this->assign('authentication_has_role', array('global_admin' => authentication_has_role('global-admin'), 'admin' => authentication_has_role('admin'), 'user' => authentication_has_role('user')));

        header("Expires: Sun, 16 Mar 2003 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        header("Content-Type: text/html; charset=UTF-8");

        $this->template->setConfigDir(__DIR__ . '/../configs');
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
        if (is_object($data) || is_null($data)) {
            return $data; // can't handle
        }

        if (!is_array($data)) {
            return htmlentities($data, ENT_QUOTES, 'UTF-8', false);
        }

        $clean = array();
        foreach ($data as $key => $value) {
            /* as this is a nested data structure it's more likely we'll output the key too (at least in my opinion, so we'll sanitise it too */
            $clean[$this->sanitise($key)] = $this->sanitise($value);
        }
        return $clean;
    }
}
