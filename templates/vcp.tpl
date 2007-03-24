<center>
<?php print $tMessage . "\n"; ?>
<table class="form">
   <tr>
      <td align="center" colspan="3">
         <?php print $PALANG['pVcp_welcome'] . "\n"; ?>
         <br />
         <br />
      </td>
   </tr>
   <tr>
      <td>
         <form name="mailbox" method="post">
         <?php print $PALANG['pVcp_username'] . ":\n"; ?>
      </td>
      <td>
         <input type="text" name="fUsername" value="<?php print $tUsername; ?>" />
      </td>
      <td>
         <?php print $pVcp_username_text . "\n"; ?>
      </td>
   </tr>
   <tr>
      <td>
         <?php print $PALANG['pVcp_password_current'] . ":\n"; ?>
      </td>
      <td>
         <input type="password" name="fPassword_current" />
      </td>
      <td>
         <?php print $pVcp_password_current_text . "\n"; ?>
      </td>
   </tr>
   <tr>
      <td>
         <?php print $PALANG['pVcp_password'] . ":\n"; ?>
      </td>
      <td>
         <input type="password" name="fPassword" />
      </td>
      <td>
         <?php print $pVcp_password_text . "\n"; ?>
      </td>
   </tr>
   <tr>
      <td>
         <?php print $PALANG['pVcp_password2'] . ":\n"; ?>
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
         <input type="submit" name="submit" value="<?php print $PALANG['pVcp_button']; ?>" />
         </form>
      </td>
   </tr>
</table>
