<center>
<?php print $tMessage; ?>
<table class="form">
   <tr>
      <td align="center" colspan="3">
         <?php print $PALANG['pPassword_welcome'] . "\n"; ?>
         <br />
         <br />
      </td>
   </tr>
   <tr>
      <td>
         <form name="mailbox" method="post">
         <?php print $PALANG['pPassword_admin'] . ":\n"; ?>
      </td>
      <td>
         <?php print $SESSID_USERNAME . "\n";?>
      </td>
      <td>
         <?php print $pPassword_admin_text . "\n"; ?>
      </td>
   </tr>
   <tr>
      <td>
         <?php print $PALANG['pPassword_password_current'] . ":\n"; ?>
      </td>
      <td>
         <input type="password" name="fPassword_current" />
      </td>
      <td>
         <?php print $pPassword_password_current_text . "\n"; ?>
      </td>
   </tr>
   <tr>
      <td>
         <?php print $PALANG['pPassword_password'] . ":\n"; ?>
      </td>
      <td>
         <input type="password" name="fPassword" />
      </td>
      <td>
         <?php print $pPassword_password_text . "\n"; ?>
      </td>
   </tr>
   <tr>
      <td>
         <?php print $PALANG['pPassword_password2'] . ":\n"; ?>
      </td>
      <td>
         <input type="password" name="fPassword2" />
      </td>
      <td>
         &nbsp;
      </td>
   </tr>
   <tr>
      <td align="center" colspan="3">
         <input type="submit" name="submit" value="<?php print $PALANG['pPassword_button']; ?>" />
         </form>
      </td>
   </tr>
</table>
