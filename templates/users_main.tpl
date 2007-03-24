<center>
<table class="auto" border="0">
   <tr>
      <td colspan="2" nowrap>
         <?php print $LANG['pUsersMain_welcome'] . "\n"; ?>
         <p />
      </td>
   </tr>
   <?php
   if ($CONF['vacation'] == 'YES')
   {
      print "<tr>\n";
      print "   <td nowrap>\n";
      print "      <a target=\"_top\" href=\"vacation.php\">" . $LANG['pUsersMenu_vacation'] . "</a>\n";
      print "   </td>\n";
      print "   <td>\n";
      print "      " . $LANG['pUsersMain_vacation'] . "\n";
      print "   </td>\n";
      print "</tr>\n";
   }
   ?>
   <tr>
      <td nowrap>
         <a target="_top" href="edit-alias.php"><?php print $LANG['pUsersMenu_edit_alias']; ?></a>
      </td>
      <td>
         <?php print $LANG['pUsersMain_edit_alias'] . "\n"; ?>
      </td>
   </tr>
   <tr>
      <td nowrap>
         <a target="_top" href="password.php"><?php print $LANG['pUsersMenu_password']; ?></a>
      </td>
      <td>
         <?php print $LANG['pUsersMain_password'] . "\n"; ?>
      </td>
   </tr>
</table>
<hr />
