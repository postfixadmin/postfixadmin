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
<input type="submit" name="submit" value="<?php print $PALANG['pOverview_button']; ?>" />
</form>
<p />
<?php 

print "<b>". $PALANG['pOverview_welcome'] . $fDomain . "</b><br />\n";
print $PALANG['pOverview_alias_alias_count'] . ": " . $limit['alias_count'] . " / " . $limit['aliases'] . " &nbsp; ";
print $PALANG['pOverview_alias_mailbox_count'] . ": " . $limit['mailbox_count'] . " / " . $limit['mailboxes'] . "<br />\n";
print "<p />\n";

if (sizeof ($tAlias) > 0)
{
   print "<center>\n";
   print "<table border=\"1\">\n";
   print "   <tr class=\"header\">\n";
   print "      <td>" . $PALANG['pOverview_alias_address'] . "</td>\n";
   print "      <td>" . $PALANG['pOverview_alias_goto'] . "</td>\n";
   print "      <td>" . $PALANG['pOverview_alias_modified'] . "</td>\n";
   print "      <td colspan=\"2\">&nbsp;</td>\n";
   print "   </tr>\n";

   for ($i = 0; $i < sizeof ($tAlias); $i++)
   {
      print "   <tr onMouseOver=\"this.bgColor='#dfdfdf'\" onMouseOut =\"this.bgColor ='#ffffff'\" bgcolor=\"#ffffff\">\n";
      print "      <td>" . $tAlias[$i]['address'] . "</td>\n";
      print "      <td>" . ereg_replace (",", "<br>", $tAlias[$i]['goto']) . "</td>\n";
      print "      <td>" . $tAlias[$i]['modified'] . "</td>\n";
      print "      <td><a href=\"edit-alias.php?address=" . urlencode ($tAlias[$i]['address']) . "&domain=$fDomain" . "\">" . $PALANG['edit'] . "</a></td>\n";
      print "      <td><a href=\"delete.php?delete=" . urlencode ($tAlias[$i]['address']) . "&domain=$fDomain" . "\"onclick=\"return confirm ('" . $PALANG['confirm'] . "')\">" . $PALANG['del'] . "</a></td>\n";
      print "   </tr>\n";
   }

   print "</table>\n";
   print "</center>\n";
   print "<p />\n";
}

if (sizeof ($tMailbox) > 0)
{
   print "<center>\n";
   print "<table border=\"1\">\n";
   print "   <tr class=\"header\">\n";
   print "      <td>" . $PALANG['pOverview_mailbox_username'] . "</td>\n";
   print "      <td>" . $PALANG['pOverview_mailbox_name'] . "</td>\n";
   if ($CONF['quota'] == 'YES') print "      <td>" . $PALANG['pOverview_mailbox_quota'] . "</td>\n";
   print "      <td>" . $PALANG['pOverview_mailbox_modified'] . "</td>\n";
   print "      <td>" . $PALANG['pOverview_mailbox_active'] . "</td>\n";
   print "      <td colspan=\"2\">&nbsp;</td>\n";
   print "   </tr>\n";
      
   for ($i = 0; $i < sizeof ($tMailbox); $i++)
   {
      print "   <tr onMouseOver=\"this.bgColor='#dfdfdf'\" onMouseOut =\"this.bgColor='#ffffff'\" bgcolor=\"#ffffff\">\n";
      print "      <td>" . $tMailbox[$i]['username'] . "</td>\n";
      print "      <td>" . $tMailbox[$i]['name'] . "</td>\n";
      if ($CONF['quota'] == 'YES') print "      <td>" . substr ($tMailbox[$i]['quota'], 0, -6) . "</td>\n";
      print "      <td>" . $tMailbox[$i]['modified'] . "</td>\n";
      $active = ($tMailbox[$i]['active'] == 1) ? $PALANG['YES'] : $PALANG['NO'];
      print "      <td><a href=\"edit-active.php?username=" . urlencode ($tMailbox[$i]['username']) . "&domain=$fDomain" . "\">" . $active . "</a></td>\n";
      print "      <td><a href=\"edit-mailbox.php?username=" . urlencode ($tMailbox[$i]['username']) . "&domain=$fDomain" . "\">" . $PALANG['edit'] . "</a></td>\n";
      print "      <td><a href=\"delete.php?delete=" . urlencode ($tMailbox[$i]['username']) . "&domain=$fDomain" . "\"onclick=\"return confirm ('" . $PALANG['confirm'] . "')\">" . $PALANG['del'] . "</a></td>\n";
      print "   </tr>\n";
   }
   print "</table>\n";
   print "</center>\n";
   print "<p />\n";
}
?>
