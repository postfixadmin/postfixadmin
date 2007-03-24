<p class="footer">
<a target="_blank" href="http://high5.net/"><font color="black">Postfix Admin <?php print $version; ?></font></a><br />
<?php
if (($CONF['show_footer_text'] == "YES") and ($CONF['footer_link']))
{
   print "<p />\n";
   print "<a href=\"" . $CONF['footer_link'] . "\">" . $CONF['footer_text'] . "</a>\n";
}
?>
</center>
</body>
</html>
