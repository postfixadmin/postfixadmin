<center>
<form name="overview" method="post">
<select name="fDomain">
<?php
for ($i = 0; $i < sizeof ($list_domains); $i++)
{
   if ($fDomain == $list_domains[$i])
   {
      print "<option value=\"$list_domains[$i]\" selected>$list_domains[$i]</option>\n";
   }
   else
   {
      print "<option value=\"$list_domains[$i]\">$list_domains[$i]</option>\n";
   }
}
?>
</select>
<input type="submit" name="submit" value="<?php print $PALANG['pViewlog_button']; ?>" />
</form>
<p />
<?php 

print "<b>". $PALANG['pViewlog_welcome'] . $fDomain . "</b><br />\n";
print "<p />\n";

if (sizeof ($tLog) > 0)
{
   print "<center>\n";
   print "<table class=\"auto\" border=\"1\">\n";
   print "   <tr class=\"header\">\n";
   print "      <td>" . $PALANG['pViewlog_timestamp'] . "</td>\n";
   print "      <td>" . $PALANG['pViewlog_username'] . "</td>\n";
   print "      <td>" . $PALANG['pViewlog_domain'] . "</td>\n";
   print "      <td>" . $PALANG['pViewlog_action'] . "</td>\n";
   print "      <td>" . $PALANG['pViewlog_data'] . "</td>\n";
   print "   </tr>\n";

   for ($i = 0; $i < sizeof ($tLog); $i++)
   {
      print "   <tr onMouseOver=\"this.bgColor='#dfdfdf'\" onMouseOut =\"this.bgColor ='#ffffff'\" bgcolor=\"#ffffff\">\n";
      print "      <td>" . $tLog[$i]['timestamp'] . "</td>\n";
      print "      <td>" . $tLog[$i]['username'] . "</td>\n";
      print "      <td>" . $tLog[$i]['domain'] . "</td>\n";
      print "      <td>" . $tLog[$i]['action'] . "</td>\n";
      print "      <td>" . $tLog[$i]['data'] . "</td>\n";
      print "   </tr>\n";
   }

   print "</table>\n";
   print "</center>\n";
   print "<p />\n";
}
?>
