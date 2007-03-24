<center>
<?php print $tMessage; ?>
<table class="form">
   <tr>
      <td align="center" colspan="3">
         <?php print $PALANG['pEdit_mailbox_welcome'] . "\n"; ?>
         <br />
         <br />
      </td>
   </tr>
   <tr>
      <td>
         <form name="mailbox" method="post">
         <?php print $PALANG['pEdit_mailbox_username'] . ":\n"; ?>
      </td>
      <td>
         <?php print $fUsername . "\n"; ?>
      </td>
      <td>
         <?php print $pEdit_mailbox_username_text . "\n"; ?>
      </td>
   </tr>
   <tr>
      <td>
         <?php print $PALANG['pEdit_mailbox_password'] . ":\n"; ?>
      </td>
      <td>
         <input type="password" name="fPassword" />
      </td>
      <td>
         <?php print $pEdit_mailbox_password_text . "\n"; ?>
      </td>
   </tr>
   <tr>
      <td>
         <?php print $PALANG['pEdit_mailbox_password2'] . ":\n"; ?>
      </td>
      <td>
         <input type="password" name="fPassword2" />
      </td>
      <td>
         &nbsp;
      </td>
   </tr>
   <tr>
      <td>
         <?php print $PALANG['pEdit_mailbox_name'] . ":\n"; ?>
      </td>
      <td>
         <input type="text" name="fName" value="<?php print htmlspecialchars ($tName, ENT_QUOTES); ?>" />
      </td>
      <td>
         &nbsp;
      </td>
   </tr>
<?php
if ($CONF['quota'] == 'YES')
{
   print "   <tr>\n";
   print "      <td>\n";
   print "         " . $PALANG['pEdit_mailbox_quota'] . ":\n";
   print "      </td>\n";
   print "      <td>\n";
   print "         <input type=\"text\" name=\"fQuota\" value=\"$tQuota\" />\n";
   print "      </td>\n";
   print "      <td>\n";
   print "         $pEdit_mailbox_quota_text\n";
   print "      </td>\n";
   print "   </tr>\n";
}
?>
   <tr>
      <td>
         <?php print $PALANG['pCreate_mailbox_active'] . ":\n"; ?>
       </td>
      <td>
         <?php $checked = (!empty ($tActive)) ? 'checked' : ''; ?>
         <input type="checkbox" name="fActive" <?php print $checked; ?> />
      </td>
      <td>
         &nbsp;
      </td>
   </tr>
   <tr>
      <td align="center" colspan="3">
         <input type="submit" name="submit" value="<?php print $PALANG['pEdit_mailbox_button']; ?>" />
         </form>
      </td>
   </tr>
</table>
