<?php if( !defined('POSTFIXADMIN') ) die( "This file cannot be used standalone." ); ?>
<div id="overview">
<form name="overview" method="get">
<select class="flat" name="domain" onChange="this.form.submit();">
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
<input class="button" type="submit" name="go" value="<?php print $PALANG['pOverview_button']; ?>" />
</form>
<form name="search" method="post" action="search.php">
<input type="textbox" name="search" size="10">
</form>
</div>

<?php
   print "<table id=\"overview_table\">\n";
   print "   <tr>\n";
   print "      <td colspan=\"5\"><h3>".$PALANG['pOverview_title']."</h3></td>";
   print "   </tr>";
   print "   <tr class=\"header\">\n";
   print "      <td>" . $PALANG['pOverview_get_domain'] . "</td>\n";
   print "      <td>" . $PALANG['pOverview_get_aliases'] . "</td>\n";
   print "      <td>" . $PALANG['pOverview_get_mailboxes'] . "</td>\n";
   if ($CONF['quota'] == 'YES') print "      <td>" . $PALANG['pOverview_get_quota'] . "</td>\n";
   print "   </tr>\n";

   for ($i = 0; $i < sizeof ($list_domains); $i++)
   {
      if ((is_array ($list_domains) and sizeof ($list_domains) > 0))
      {
         $limit = get_domain_properties ($list_domains[$i]);

         if ($limit['aliases'] == 0) $limit['aliases'] = $PALANG['pOverview_unlimited'];
         if ($limit['mailboxes'] == 0) $limit['mailboxes'] = $PALANG['pOverview_unlimited'];
         if ($limit['maxquota'] == 0) $limit['maxquota'] = $PALANG['pOverview_unlimited'];
         if ($limit['aliases'] < 0) $limit['aliases'] = $PALANG['pOverview_disabled'];
         if ($limit['mailboxes'] < 0) $limit['mailboxes'] = $PALANG['pOverview_disabled'];
         if ($limit['maxquota'] < 0) $limit['maxquota'] = $PALANG['pOverview_disabled'];

         print "   <tr class=\"hilightoff\" onMouseOver=\"className='hilighton';\" onMouseOut=\"className='hilightoff';\">\n";
         print "      <td><a href=\"list-virtual.php?domain=" . $list_domains[$i] . "\">" . $list_domains[$i] . "</a></td>\n";
         print "      <td>" . $limit['alias_count'] . " / " . $limit['aliases'] . "</td>\n";
         print "      <td>" . $limit['mailbox_count'] . " / " . $limit['mailboxes'] . "</td>\n";
         if ($CONF['quota'] == 'YES') print "      <td>" . $limit['maxquota'] . "</td>\n";
         print "   </tr>\n";
      }
   }
   print "</table>\n";
?>
