<?php if( !defined('POSTFIXADMIN') ) die( "This file cannot be used standalone." ); ?>
<?php
require(dirname(__FILE__) . '/search.tpl');

/* old code, should be deleted after testing the merge with templates/search.php
?>

<div id="overview">
<form name="search" method="post" action="search.php">
<table width=750><tr>
<td>
  <h4><?php print $PALANG['pSearch_welcome'] . $fSearch; ?></h4>
</td>
<td>
    <?php print $PALANG['pSearch']; ?>:<input type="textbox" name="search">
</td>
<td align=right><select class="flat" name="fDomain" >
<?php
print "<option value=\"$list_domains[0]\" selected>$list_domains[0]</option>\n";
for ($i = 1; $i < sizeof ($list_domains); $i++)
{
    print "<option value=\"$list_domains[$i]\">$list_domains[$i]</option>\n";
}
?>
</select>
<input class="button" type="submit" name="fGo" value="Return to <?php print $PALANG['pAdminMenu_list_virtual']; ?>" /></td>
</tr></table>
</form>
</div>

<?php
if (sizeof ($tAlias) > 0)
{
   print "<table id=\"alias_table\">\n";
   print "   <tr>\n";
   print "      <td colspan=\"5\"><h3>".$PALANG['pOverview_alias_title']."</h3></td>";
   print "   </tr>";
   print "   <tr class=\"header\">\n";
   print "      <td>" . $PALANG['pOverview_alias_address'] . "</td>\n";
   print "      <td>" . $PALANG['pOverview_alias_goto'] . "</td>\n";
   print "      <td>" . $PALANG['pOverview_alias_modified'] . "</td>\n";
   print "      <td>" . $PALANG['pOverview_alias_active'] . "</td>\n";
   print "      <td colspan=\"2\">&nbsp;</td>\n";
   print "   </tr>\n";

   for ($i = 0; $i < sizeof ($tAlias); $i++)
   {
      if ((is_array ($tAlias) and sizeof ($tAlias) > 0))
      {
         print "   <tr class=\"hilightoff\" onMouseOver=\"className='hilighton';\" onMouseOut=\"className='hilightoff';\">\n";
         print "      <td>" . $tAlias[$i]['address'] . "</td>\n";
         print "      <td>" . ereg_replace (",", "<br>", $tAlias[$i]['goto']) . "</td>\n";
         print "      <td>" . $tAlias[$i]['modified'] . "</td>\n";
         $active = ($tAlias[$i]['active'] == 1) ? $PALANG['YES'] : $PALANG['NO'];
         print "      <td><a href=\"edit-active.php?alias=" . urlencode ($tAlias[$i]['address']) . "&domain=" . $tAlias[$i]['domain']. "&return=search.php?search=" . urlencode ($fSearch) . "\">" . $active . "</a></td>\n";
         print "      <td><a href=\"edit-alias.php?address=" . urlencode ($tAlias[$i]['address']) . "&domain=" . $tAlias[$i]['domain'] . "\">" . $PALANG['edit'] . "</a></td>\n";
         print "      <td><a href=\"delete.php?table=alias&delete=" . urlencode ($tAlias[$i]['address']) . "&domain=" . $tAlias[$i]['domain'] . "\"onclick=\"return confirm ('" . $PALANG['confirm'] . $PALANG['pOverview_get_aliases'] . ": ". $tAlias[$i]['address'] . "')\">" . $PALANG['del'] . "</a></td>\n";
         print "   </tr>\n";
      }
   }

   print "</table>\n";
}

if (sizeof ($tMailbox) > 0)
{
   print "<table id=\"mailbox_table\">\n";
   print "   <tr>\n";
   print "      <td colspan=\"7\"><h3>".$PALANG['pOverview_mailbox_title']."</h3></td>";
   print "   </tr>";
   print "   <tr class=\"header\">\n";
   print "      <td>" . $PALANG['pOverview_mailbox_username'] . "</td>\n";
   print "      <td>" . $PALANG['pOverview_mailbox_name'] . "</td>\n";
   if ($CONF['quota'] == 'YES') print "      <td>" . $PALANG['pOverview_mailbox_quota'] . "</td>\n";
   print "      <td>" . $PALANG['pOverview_mailbox_modified'] . "</td>\n";
   print "      <td>" . $PALANG['pOverview_mailbox_active'] . "</td>\n";
   if ($CONF['alias_control'] == 'YES')
   {
      print "      <td colspan=\"3\">&nbsp;</td>\n";
   }
   else
   {
      print "      <td colspan=\"2\">&nbsp;</td>\n";
   }
   print "   </tr>\n";

   for ($i = 0; $i < sizeof ($tMailbox); $i++)
   {
      if ((is_array ($tMailbox) and sizeof ($tMailbox) > 0))
      {
         print "   <tr class=\"hilightoff\" onMouseOver=\"className='hilighton';\" onMouseOut=\"className='hilightoff';\">\n";
         print "      <td>" . $tMailbox[$i]['username'] . "</td>\n";
         print "      <td>" . $tMailbox[$i]['name'] . "</td>\n";
         if ($CONF['quota'] == 'YES') print "      <td>" . divide_quota ($tMailbox[$i]['quota']) . "</td>\n";
         print "      <td>" . $tMailbox[$i]['modified'] . "</td>\n";
         $active = ($tMailbox[$i]['active'] == 1) ? $PALANG['YES'] : $PALANG['NO'];
         print "      <td><a href=\"edit-active.php?username=" . urlencode ($tMailbox[$i]['username']) . "&domain=" . $tMailbox[$i]['domain'] . "&return=search.php?search=" . urlencode ($fSearch) . "\">" . $active . "</a></td>\n";
         if ($CONF['alias_control'] == 'YES')
         {
            print "      <td><a href=\"edit-alias.php?address=" . urlencode ($tMailbox[$i]['username']) . "&domain=" . $tMailbox[$i]['domain'] . "\">" . $PALANG['pOverview_alias_edit'] . "</a></td>\n";
         }
         print "      <td><a href=\"edit-mailbox.php?username=" . urlencode ($tMailbox[$i]['username']) . "&domain=" . $tMailbox[$i]['domain'] . "\">" . $PALANG['edit'] . "</a></td>\n";
         print "      <td><a href=\"delete.php?table=mailbox&delete=" . urlencode ($tMailbox[$i]['username']) . "&domain=" . $tMailbox[$i]['domain'] . "\"onclick=\"return confirm ('" . $PALANG['confirm'] . $PALANG['pOverview_get_mailboxes'] . ": ". $tMailbox[$i]['username'] . "')\">" . $PALANG['del'] . "</a></td>\n";
         print "   </tr>\n";
      }
   }
   print "</table>\n";
}

/* */
?>
