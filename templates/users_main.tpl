<center>
<table class="auto" border="0">
   <tr>
      <td colspan="2" nowrap>
         <?php print $PALANG['pUsersMain_welcome'] . "\n"; ?>
         <p />
      </td>
   </tr>
   <?php
   if ($CONF['vacation'] == 'YES')
   {
      print "<tr>\n";
      print "   <td nowrap>\n";
      print "      <a target=\"_top\" href=\"vacation.php\">" . $PALANG['pUsersMenu_vacation'] . "</a>\n";
      print "   </td>\n";
      print "   <td>\n";
      print "      " . $PALANG['pUsersMain_vacation'] . "\n";
      print "   </td>\n";
      print "</tr>\n";
   }
   ?>
   <tr>
      <td nowrap>
         <a target="_top" href="edit-alias.php"><?php print $PALANG['pUsersMenu_edit_alias']; ?></a>
      </td>
      <td>
         <?php print $PALANG['pUsersMain_edit_alias'] . "\n"; ?>
      </td>
   </tr>
   <tr>
      <td nowrap>
         <a target="_top" href="password.php"><?php print $PALANG['pUsersMenu_password']; ?></a>
      </td>
      <td>
         <?php print $PALANG['pUsersMain_password'] . "\n"; ?>
      </td>
   </tr>
</table>
<hr />
