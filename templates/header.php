<?php
if( !defined('POSTFIXADMIN') ) die( "This file cannot be used standalone." );

if( !isset($CONF) || !is_array($CONF) ) {
    die("Configuration not loaded. Check " . __FILE__);
}
@header ("Expires: Sun, 16 Mar 2003 05:00:00 GMT");
@header ("Last-Modified: " . gmdate ("D, d M Y H:i:s") . " GMT");
@header ("Cache-Control: no-store, no-cache, must-revalidate");
@header ("Cache-Control: post-check=0, pre-check=0", false);
@header ("Pragma: no-cache");
@header ("Content-Type: text/html; charset=UTF-8");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<?php
if (file_exists (realpath ("../".$CONF['theme_favicon']))) {
    print "<link rel=\"shortcut icon\" href=\"../".htmlentities($CONF['theme_favicon'])."\" />\n";
} else {
    print "<link rel=\"shortcut icon\" href=\"".htmlentities($CONF['theme_favicon'])."\" />\n";
}
if (file_exists (realpath ("../".$CONF['theme_css']))) {
    print "<link rel=\"stylesheet\" type=\"text/css\" href=\"../".htmlentities($CONF['theme_css'])."\" />\n";
} else {
    print "<link rel=\"stylesheet\" type=\"text/css\" href=\"".htmlentities($CONF['theme_css'])."\" />\n";
}
?>
<title>Postfix Admin - <?php print $_SERVER['HTTP_HOST']; ?></title>
</head>
<body>
<div id="login_header">
<?php
if (file_exists (realpath ("../".$CONF['theme_logo'])))
{
    print "<img id=\"login_header_logo\" src=\"../".htmlentities($CONF['theme_logo'])."\" />\n";
} else {
    print "<img id=\"login_header_logo\" src=\"".htmlentities($CONF['theme_logo'])."\" />\n";
}

if (($CONF['show_header_text'] == "YES") and ($CONF['header_text']))
{
    print "<h2>" . $CONF['header_text'] . "</h2>\n";
}
?>
</div>

<?php
if(isset($_SESSION['flash'])) {
    if(isset($_SESSION['flash']['info'])) {
        echo '<ul class="flash-info">';
        foreach($_SESSION['flash']['info'] as $msg) {
            echo "<li>$msg</li>";
        }
        echo '</ul>';
    }
    if(isset($_SESSION['flash']['error'])) {
        echo '<ul class="flash-error">';
        foreach($_SESSION['flash']['error'] as $msg) {
            echo "<li>$msg</li>";
        }
        echo '</ul>';
    }
    /* nuke it from orbit. It's the only way to be sure. */
    $_SESSION['flash'] = array(); 
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
