<?php if( !defined('POSTFIXADMIN') ) die( "This file cannot be used standalone." ); ?>
<?php 
if (sizeof ($list_admins) > 0)
{
   print "<table id=\"admin_table\">\n";
   print "   <tr class=\"header\">\n";
   print "      <td>" . $PALANG['pAdminList_admin_username'] . "</td>\n";
   print "      <td>" . $PALANG['pAdminList_admin_count'] . "</td>\n";
   print "      <td>" . $PALANG['pAdminList_admin_modified'] . "</td>\n";
   print "      <td>" . $PALANG['pAdminList_admin_active'] . "</td>\n";
   print "      <td colspan=\"2\">&nbsp;</td>\n";
   print "   </tr>\n";

   for ($i = 0; $i < sizeof ($list_admins); $i++)
   {
      if ((is_array ($list_admins) and sizeof ($list_admins) > 0))
      {
         print "   <tr class=\"hilightoff\" onMouseOver=\"className='hilighton';\" onMouseOut=\"className='hilightoff';\">\n";
      	print "      <td><a href=\"list-domain.php?username=" . $list_admins[$i] . "\">" . $list_admins[$i] . "</a></td>";
         if ($admin_properties[$i]['domain_count'] == 'ALL') $admin_properties[$i]['domain_count'] = $PALANG['pAdminEdit_admin_super_admin'];
      	print "      <td>" . $admin_properties[$i]['domain_count'] . "</td>";
   		print "      <td>" . $admin_properties[$i]['modified'] . "</td>";
         $active = ($admin_properties[$i]['active'] == 1) ? $PALANG['YES'] : $PALANG['NO'];
   		print "      <td><a href=\"edit-active-admin.php?username=" . $list_admins[$i] . "\">" . $active . "</a></td>";
   		print "      <td><a href=\"edit-admin.php?username=" . $list_admins[$i] . "\">" . $PALANG['edit'] . "</a></td>";
   		print "      <td><a href=\"delete.php?table=admin&delete=" . $list_admins[$i] . "\" onclick=\"return confirm ('" . $PALANG['confirm'] . $PALANG['pAdminList_admin_username'] . ": " . $list_admins[$i] . "')\">" . $PALANG['del'] . "</a></td>";
   		print "   </tr>\n";
		}
   }

   print "</table>\n";
   print "<p><a href=\"create-admin.php\">" . $PALANG['pAdminMenu_create_admin'] . "</a>\n";
}

/* vim: set ft=php expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
