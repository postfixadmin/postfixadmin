<table class="auto">
   <tr>
      <?php
      if ($CONF['vacation'] == "YES")
      {
         print "   <td width=\"8\">\n";
         print "      &nbsp;\n";
         print "   </td>\n";
         print "   <td class=\"menu\">\n";
         print "      <a target=\"_top\" href=\"vacation.php\">" . $PALANG['pUsersMenu_vacation'] . "</a>\n";
         print "   </td>\n";
      }
      ?>
      <td width="8">
         &nbsp;
      </td>
      <td class="menu">
         <a target="_top" href="edit-alias.php"><?php print $PALANG['pUsersMenu_edit_alias']; ?></a>
      </td>
      <td width="8">
         &nbsp;
      </td>
      <td class="menu">
         <a target="_top" href="password.php"><?php print $PALANG['pUsersMenu_password']; ?></a>
      </td>
      <td width="8">
         &nbsp;
      </td>
      <td class="menu">
         <a target="_top" href="logout.php"><?php print $PALANG['pMenu_logout']; ?></a>
      </td>
      <td width="8">
         &nbsp;
      </td>
   </tr>
</table>
<hr />
