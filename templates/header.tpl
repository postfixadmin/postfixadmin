<?php
@header ("Expires: Sun, 16 Mar 2003 05:00:00 GMT");
@header ("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
@header ("Cache-Control: no-store, no-cache, must-revalidate");
@header ("Cache-Control: post-check=0, pre-check=0", false);
@header ("Pragma: no-cache");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<?php
if (file_exists (realpath ("./stylesheet.css"))) print "<link rel=\"stylesheet\" href=\"stylesheet.css\">\n";
if (file_exists (realpath ("../stylesheet.css"))) print "<link rel=\"stylesheet\" href=\"../stylesheet.css\">\n";
?>
<title>Postfix Admin</title>
</head>
<body>
<center>
