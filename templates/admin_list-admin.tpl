<center>
<?php 
if (sizeof ($list_admins) > 0)
{
   print "<center>\n";
   print "<table border=\"1\">\n";
   print "   <tr class=\"header\">\n";
   print "      <td>" . $LANG['pAdminList_admin_username'] . "</td>\n";
   print "      <td>" . $LANG['pAdminList_admin_count'] . "</td>\n";
   print "      <td>" . $LANG['pAdminList_admin_modified'] . "</td>\n";
   print "      <td>" . $LANG['pAdminList_admin_active'] . "</td>\n";
   print "      <td colspan=\"2\">&nbsp;</td>\n";
   print "   </tr>\n";

   for ($i = 0; $i < sizeof ($list_admins); $i++)
   {
   	print "   <tr onMouseOver=\"this.bgColor = '#dfdfdf'\" onMouseOut =\"this.bgColor = '#ffffff'\" bgcolor=\"#ffffff\">";
   	print "      <td><a href=\"list-domain.php?username=" . $list_admins[$i] . "\">" . $list_admins[$i] . "</a></td>";
   	print "      <td>" . $admin_properties[$i]['domain_count'] . "</td>";
		print "      <td>" . $admin_properties[$i]['modified'] . "</td>";
      $active = ($admin_properties[$i]['active'] == 1) ? $LANG['YES'] : $LANG['NO'];
		print "      <td>" . $active . "</td>";
		print "      <td><a href=\"edit-admin.php?username=" . $list_admins[$i] . "\">" . $LANG['edit'] . "</a></td>";
		print "      <td><a href=\"delete.php?table=admin&where=username&delete=" . $list_admins[$i] . "\" onclick=\"return confirm ('" . $LANG['confirm'] . "')\">" . $LANG['del'] . "</a></td>";
		print "   </tr>\n";
   }

   print "</table>\n";
   print "</center>\n";
   print "<p />\n";
}
?>
