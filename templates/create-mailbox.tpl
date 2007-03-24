<center>
<?php print $tMessage . "\n"; ?>
<table class="form">
   <tr>
      <td align="center" colspan="3">
         <?php print $PALANG['pCreate_mailbox_welcome'] . "\n"; ?>
         <br />
         <br />
      </td>
   </tr>
   <tr>
      <td>
         <form name="mailbox" method="post">
         <?php print $PALANG['pCreate_mailbox_username'] . ":\n"; ?>
      </td>
      <td>
         <input type="text" name="fUsername" value="<?php print $tUsername; ?>" />
      </td>
      <td>
         <select name="fDomain">
         <?php
         for ($i = 0; $i < sizeof ($list_domains); $i++)
         {
            if ($tDomain == $list_domains[$i])
            {
               print "            <option value=\"$list_domains[$i]\" selected>$list_domains[$i]</option>\n";
            }
            else
            {
               print "            <option value=\"$list_domains[$i]\">$list_domains[$i]</option>\n";
            }
         }
         ?>
         </select>
         <?php print $pCreate_mailbox_username_text . "\n"; ?>
      </td>
   </tr>
   <tr>
      <td>
         <?php print $PALANG['pCreate_mailbox_password'] . ":\n"; ?>
      </td>
      <td>
         <input type="password" name="fPassword" />
      </td>
      <td>
         <?php print $pCreate_mailbox_password_text . "\n"; ?>
      </td>
   </tr>
   <tr>
      <td>
         <?php print $PALANG['pCreate_mailbox_password2'] . ":\n"; ?>
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
         <?php print $PALANG['pCreate_mailbox_name'] . ":\n"; ?>
      </td>
      <td>
         <input type="text" name="fName" value="<?php print $tName; ?>" />
      </td>
      <td>
         <?php print $pCreate_mailbox_name_text . "\n"; ?>
      </td>
   </tr>
   <tr>
<?php
if ($CONF['quota'] == 'YES')
{
   print "   <tr>\n";
   print "      <td>\n";
   print "         " . $PALANG['pCreate_mailbox_quota'] . ":\n";
   print "      </td>\n";
   print "      <td>\n";
   print "         <input type=\"text\" name=\"fQuota\" value=\"$tQuota\" />\n";
   print "      </td>\n";
   print "      <td>\n";
   print "         $pCreate_mailbox_quota_text\n";
   print "      </td>\n";
   print "   </tr>\n";
}
?>
   <tr>
      <td>
         <?php print $PALANG['pCreate_mailbox_active'] . ":\n"; ?>
       </td>
      <td>
         <input type="checkbox" name="fActive" checked />
      </td>
      <td>
         &nbsp;
      </td>
   </tr>
   <tr>
      <td>
         <?php print $PALANG['pCreate_mailbox_mail'] . ":\n"; ?>
       </td>
      <td>
         <input type="checkbox" name="fMail" checked />
      </td>
      <td>
         &nbsp;
      </td>
   </tr>
   <tr>
      <td align="center" colspan="3">
         <input type="submit" name="submit" value="<?php print $PALANG['pCreate_mailbox_button']; ?>" />
         </form>
      </td>
   </tr>
</table>
