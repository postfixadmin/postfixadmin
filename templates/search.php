<?php if( !defined('POSTFIXADMIN') ) die( "This file cannot be used standalone." ); ?>
<div id="overview">
<form name="search" method="post" action="search.php">
<table width=750><tr>
<td>
   <h4><?php print $PALANG['pSearch_welcome'] . $fSearch; ?></h4>
</td>
<td>
    <?php print $PALANG['pSearch']; ?>:<input type="textbox" name="search">
</td>

<?php
if (authentication_has_role('global-admin')) {
   print '<td></td>'; 
   print '<td align=right><select class="flat" name="fDomain" >';

} else {
   print '<td align=right><select class="flat" name="fDomain" >';
}

print "<option value=\"$list_domains[0]\" selected>$list_domains[0]</option>\n";
for ($i = 1; $i < sizeof ($list_domains); $i++)
{
    print "<option value=\"$list_domains[$i]\">$list_domains[$i]</option>\n";
}
?>
</select>
<?php
if (authentication_has_role('global-admin')) {
   ?>
   <input class="button" type="submit" name="fGo" value="<?php print $PALANG['pReturn_to'] . ' ' . $PALANG['pAdminMenu_list_virtual']; ?>" /></td>
   <?php 
} else { 
   ?>
   <input class="button" type="submit" name="fGo" value="<?php print $PALANG['pReturn_to'] . ' ' . $PALANG['pMenu_overview']; ?>" /></td>
   <?php
}
?>

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
         //highlight search string
         $tAlias[$i]['display_address'] = $tAlias[$i]['address'];
         if ($fSearch != "" && stristr($tAlias[$i]['display_address'],$fSearch))
         {
           $new_address = str_ireplace($fSearch, "<span style='background-color: lightgreen'>" .
               $fSearch . "</span>", $tAlias[$i]['display_address']);
           $tAlias[$i]['display_address'] = $new_address;
         }
         print "      <td>" . $tAlias[$i]['display_address'] . "</td>\n";
         if ($fSearch != "" && stristr($tAlias[$i]['goto'],$fSearch))
         {
           $new_goto = str_ireplace($fSearch, "<span style='background-color: lightgreen'>" .
               $fSearch . "</span>", $tAlias[$i]['goto']);
           $tAlias[$i]['goto'] = $new_goto;
         }
         print "      <td>" . preg_replace ("/,/", "<br>", $tAlias[$i]['goto']) . "</td>\n";
         print "      <td>" . $tAlias[$i]['modified'] . "</td>\n";
         if ($CONF['special_alias_control'] == 'YES' || authentication_has_role('global-admin'))
         {
            $active = ($tAlias[$i]['active'] == 1) ? $PALANG['YES'] : $PALANG['NO'];
            print "      <td><a href=\"edit-active.php?alias=" . urlencode ($tAlias[$i]['address']) . "&domain=" . $tAlias[$i]['domain'] . "&return=search.php?search=" . urlencode ($fSearch) . "\">" . $active . "</a></td>\n";
            print "      <td><a href=\"edit-alias.php?address=" . urlencode ($tAlias[$i]['address']) . "&domain=" . $tAlias[$i]['domain'] . "\">" . $PALANG['edit'] . "</a></td>\n";
            print "      <td><a href=\"delete.php?table=alias&"; 
         print "delete=" . urlencode ($tAlias[$i]['address']) . "&domain=" . $tAlias[$i]['domain'] . "\"onclick=\"return confirm ('" . $PALANG['confirm'] . $PALANG['pOverview_get_aliases'] . ": ". $tAlias[$i]['address'] . "')\">" . $PALANG['del'] . "</a></td>\n";
         }
         else
         {
            if (check_alias_owner ($SESSID_USERNAME, $tAlias[$i]['address']))
            {
               $active = ($tAlias[$i]['active'] == 1) ? $PALANG['YES'] : $PALANG['NO'];
               print "      <td><a href=\"edit-active.php?alias=" . urlencode ($tAlias[$i]['address']) . "&domain=" . $tAlias[$i]['domain'] . "&return=search.php?search=" . urlencode ($fSearch) . "\">" . $active . "</a></td>\n";
               print "      <td><a href=\"edit-alias.php?address=" . urlencode ($tAlias[$i]['address']) . "&domain=" . $tAlias[$i]['domain'] . "\">" . $PALANG['edit'] . "</a></td>\n";
               print "      <td><a href=\"delete.php?table=alias&delete=" . urlencode ($tAlias[$i]['address']) . "&domain=" . $tAlias[$i]['domain'] . "\"onclick=\"return confirm ('" . $PALANG['confirm'] . $PALANG['pOverview_get_aliases'] . ": ". $tAlias[$i]['address'] . "')\">" . $PALANG['del'] . "</a></td>\n";
            }
            else
            {
               print "      <td>&nbsp;</td>\n";
               print "      <td>&nbsp;</td>\n";
            }
         }
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
   print "      <td colspan=\"2\">&nbsp;</td>\n";
   $colspan=2;
   if ($CONF['vacation_control_admin'] == 'YES') $colspan=$colspan+1;
   if ($CONF['alias_control_admin'] == 'YES') $colspan=$colspan+1;

   if (authentication_has_role('global-admin') && $CONF['alias_control'] == 'YES') {
      $colspan = 3;
   }
   print "      <td colspan=\"$colspan\">&nbsp;</td>\n";
   print "   </tr>\n";

   for ($i = 0; $i < sizeof ($tMailbox); $i++)
   {
      if ((is_array ($tMailbox) and sizeof ($tMailbox) > 0))
      {
         print "   <tr class=\"hilightoff\" onMouseOver=\"className='hilighton';\" onMouseOut=\"className='hilightoff';\">\n";
         $tMailbox[$i]['display_username'] = $tMailbox[$i]['username'];
         if ($fSearch != "" && stristr($tMailbox[$i]['display_username'],$fSearch))
         {
           $new_name = str_ireplace($fSearch, "<span style='background-color: lightgreen'>" .
               $fSearch . "</span>", $tMailbox[$i]['display_username']);
           $tMailbox[$i]['display_username'] = $new_name;
         }
         print "      <td>" . $tMailbox[$i]['display_username'] . "</td>\n";
         if ($fSearch != "" && stristr($tMailbox[$i]['name'],$fSearch))
         {
           $new_name = str_ireplace($fSearch, "<span style='background-color: lightgreen'>" .
               $fSearch . "</span>", $tMailbox[$i]['name']);
           $tMailbox[$i]['name'] = $new_name;
         }
         print "      <td>" . $tMailbox[$i]['name'] . "</td>\n";
         if ($CONF['quota'] == 'YES') print "      <td>" . divide_quota ($tMailbox[$i]['quota']) . "</td>\n";
         print "      <td>" . $tMailbox[$i]['modified'] . "</td>\n";
         $active = ($tMailbox[$i]['active'] == 1) ? $PALANG['YES'] : $PALANG['NO'];
         print "      <td><a href=\"edit-active.php?username=" . urlencode ($tMailbox[$i]['username']) . "&domain=" . $tMailbox[$i]['domain'] . "&return=search.php?search=" . urlencode ($fSearch) . "\">" . $active . "</a></td>\n";

$has_alias_control = 0; # temporary variable to simplify admin vs. superadmin code
         if (authentication_has_role('global-admin')) {
            if ($CONF['alias_control'] == 'YES') $has_alias_control = 1;
         } else {
            if ($CONF['alias_control_admin'] == 'YES') $has_alias_control = 1;
         }
         if ($CONF['vacation_control_admin'] == 'YES') {
            $v_active = ($tMailbox[$i]['v_active'] == 1) ? $PALANG['pOverview_vacation_edit'] : $PALANG['pOverview_vacation_option'];
            print "      <td><a href=\"edit-vacation.php?username=" . urlencode ($tMailbox[$i]['username']) . "&domain=" . $tMailbox[$i]['domain'] . "\">" . $v_active . "</a></td>\n";
         }
         if ($has_alias_control == 1)
         {
            print "      <td><a href=\"edit-alias.php?address=" . urlencode ($tMailbox[$i]['username']) . "&domain=" . $tMailbox[$i]['domain'] . "\">" . $PALANG['pOverview_alias_edit'] . "</a></td>\n";
         }

         print "      <td><a href=\"edit-mailbox.php?username=" . urlencode ($tMailbox[$i]['username']) . "&domain=" . $tMailbox[$i]['domain'] . "\">" . $PALANG['edit'] . "</a></td>\n";
         print "      <td><a href=\"delete.php?table=mailbox&"; 
         print "delete=" . urlencode ($tMailbox[$i]['username']) . "&domain=" . $tMailbox[$i]['domain'] . "\"onclick=\"return confirm ('" . $PALANG['confirm'] . $PALANG['pOverview_get_mailboxes'] . ": ". $tMailbox[$i]['username'] . "')\">" . $PALANG['del'] . "</a></td>\n";
         print "   </tr>\n";
      }
   }
   print "</table>\n";
}
# vim: ts=3 expandtab ft=php
?>
