<?php

/**
 * Turn on sanitisation of all data by default so it's not possible for XSS flaws to occur in PFA
 */
class PFASmarty {

    /**
     * @var Smarty
     */
    protected $template;

    /**
     * @param string $template_theme 
     */
    public function __construct($template_theme = 'default') {
        $this->template = new Smarty();

        //$this->template->debugging = true;
        if($template_theme == 'default') {
            $this->template->setTemplateDir(dirname(__FILE__) . '/../templates');
        }
        else {
            $this->template->setTemplateDir(dirname(__FILE__) . '/../templates/'. $template_theme);
        }

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
     * @return void
     * @param string $template
     */
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
        $clean = array();
        foreach ($data as $key => $value) {
            /* as this is a nested data structure it's more likely we'll output the key too (at least in my opinion, so we'll sanitise it too */
            $clean[$this->sanitise($key)] = $this->sanitise($value);
        }
        return $clean;
    }
}

