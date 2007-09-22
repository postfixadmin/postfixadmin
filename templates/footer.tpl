<div id="footer">
<a target="_blank" href="http://postfixadmin.com/">Postfix Admin <?php print $version; ?></a>
&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;
<?php 
if(isset($_SESSION['sessid']['username'])) {
    echo "Logged as " . authentication_get_username();
}
?> 
&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;
<a target="_blank" href="http://postfixadmin.com/?version=<?php print $version; ?>"><?php print $PALANG['check_update']; ?></a>
<?php
if (($CONF['show_footer_text'] == "YES") and ($CONF['footer_link']))
{
   print "&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;";
   print "<a href=\"" . $CONF['footer_link'] . "\">" . $CONF['footer_text'] . "</a>\n";
}

?>
</div>
</body>
</html>
