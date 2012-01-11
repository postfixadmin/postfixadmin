<?php if( !defined('POSTFIXADMIN') ) die( "This file cannot be used standalone." ); ?>
<div id="overview">
<form name="overview" method="post">
<select name="fUsername" onChange="this.form.submit();">
<?php
if (!empty ($list_admins))
{
   for ($i = 0; $i < sizeof ($list_admins); $i++)
   {
      if ($fUsername == $list_admins[$i])
      {
         print "<option value=\"" . $list_admins[$i] . "\" selected>" . $list_admins[$i] . "</option>\n";
      }
      else
      {
         print "<option value=\"" . $list_admins[$i] . "\">" . $list_admins[$i] . "</option>\n";
      }
   }
}
?>
</select>
<input class="button" type="submit" name="go" value="<?php print $PALANG['pOverview_button']; ?>" />
</form>
<form name="search" method="post" action="search.php">
<input type="textbox" name="search" size="10" />
</form>
</div>

<?php 
if (sizeof ($domain_properties) > 0)
{
   print "<table id=\"admin_table\">\n";
   print "   <tr class=\"header\">\n";
   print "      <td>" . $PALANG['pAdminList_domain_domain'] . "</td>\n";
   print "      <td>" . $PALANG['pAdminList_domain_description'] . "</td>\n";
   print "      <td>" . $PALANG['pAdminList_domain_aliases'] . "</td>\n";
   print "      <td>" . $PALANG['pAdminList_domain_mailboxes'] . "</td>\n";
   if ($CONF['quota'] == 'YES') print "      <td>" . $PALANG['pAdminList_domain_maxquota'] . "</td>\n";
   if ($CONF['transport'] == 'YES') print "      <td>" . $PALANG['pAdminList_domain_transport'] . "</td>\n";
   print "      <td>" . $PALANG['pAdminList_domain_backupmx'] . "</td>\n";
   print "      <td>" . $PALANG['pAdminList_domain_modified'] . "</td>\n";
   print "      <td>" . $PALANG['pAdminList_domain_active'] . "</td>\n";
   print "      <td colspan=\"2\">&nbsp;</td>\n";
   print "   </tr>\n";

#   for ($i = 0; $i < sizeof ($domain_properties); $i++)
   foreach(array_keys($domain_properties) as $i)
   {
      if ((is_array ($domain_properties) and sizeof ($domain_properties) > 0))
      {
         print "   <tr class=\"hilightoff\" onMouseOver=\"className='hilighton';\" onMouseOut=\"className='hilightoff';\">\n";
         print "<td><a href=\"list-virtual.php?domain=" . $domain_properties[$i]['domain'] . "\">" . $domain_properties[$i]['domain'] . "</a></td>";
         print "<td>" . htmlentities($domain_properties[$i]['description']) . "</td>";
         print "<td>" . $domain_properties[$i]['alias_count'] . " / " . $domain_properties[$i]['aliases'] . "</td>";
         print "<td>" . $domain_properties[$i]['mailbox_count'] . " / " . $domain_properties[$i]['mailboxes'] . "</td>";
         if ($CONF['quota'] == 'YES')
         {
            print "      <td>";
            if ($domain_properties[$i]['maxquota'] == 0)
            {
               print $PALANG['pOverview_unlimited'];
            }
            elseif ($domain_properties[$i]['maxquota'] < 0)
            {
               print $PALANG['pOverview_disabled'];
            }
            else
            {
               print $domain_properties[$i]['maxquota'];
            }
            print "</td>\n";
         }
         if ($CONF['transport'] == 'YES') print "<td>" . $domain_properties[$i]['transport'] . "</td>";
         $backupmx = ($domain_properties[$i]['backupmx'] == db_get_boolean(true)) ? $PALANG['YES'] : $PALANG['NO'];
         print "<td>$backupmx</td>";
         print "<td>" . $domain_properties[$i]['modified'] . "</td>";
         $active = ($domain_properties[$i]['active'] == db_get_boolean(true)) ? $PALANG['YES'] : $PALANG['NO'];
         print "<td><a href=\"edit-active-domain.php?domain=" . $domain_properties[$i]['domain'] . "\">" . $active . "</a></td>";
         print "<td><a href=\"edit-domain.php?domain=" . $domain_properties[$i]['domain'] . "\">" . $PALANG['edit'] . "</a></td>";
         print "<td><a href=\"delete.php?table=domain&delete=" . $domain_properties[$i]['domain'] . "\" onclick=\"return confirm ('" . $PALANG['confirm_domain'] . $PALANG['pAdminList_admin_domain'] . ": " . $domain_properties[$i]['domain'] . "')\">" . $PALANG['del'] . "</a></td>";
         print "</tr>\n";
		}
   }

   print "</table>\n";
}
echo "<p><a href='create-domain.php'>{$PALANG['pAdminMenu_create_domain']}</a>";
?>
