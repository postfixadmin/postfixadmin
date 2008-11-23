<?php if( !defined('POSTFIXADMIN') ) die( "This file cannot be used standalone." ); ?>
<div id="edit_form">
<form name="mailbox" method="post">
<table>
   <tr>
      <td colspan="3"><h3><?php print $PALANG['pEdit_mailbox_welcome']; ?></h3></td>
   </tr>
   <tr>
      <td><?php print $PALANG['pEdit_mailbox_username']; ?></td>
      <td><?php print $fUsername; ?></td>
      <td><?php print $pEdit_mailbox_username_text; ?></td>
   </tr>
   <tr>
      <td><?php print $PALANG['pEdit_mailbox_password'] . ":"; ?></td>
      <td><input class="flat" type="password" name="fPassword" /></td>
      <td><?php print $pEdit_mailbox_password_text; ?></td>
   </tr>
   <tr>
      <td><?php print $PALANG['pEdit_mailbox_password2'] . ":"; ?></td>
      <td><input class="flat" type="password" name="fPassword2" /></td>
      <td>&nbsp;</td>
   </tr>
   <tr>
      <td><?php print $PALANG['pEdit_mailbox_name'] . ":"; ?></td>
      <td><input class="flat" type="text" name="fName" value="<?php print htmlspecialchars ($tName,ENT_QUOTES); ?>" /></td>
      <td><?php print $pEdit_mailbox_name_text; ?></td>
   </tr>
   <?php if ($CONF['quota'] == 'YES') { ?>
   <tr>
      <td><?php print $PALANG['pEdit_mailbox_quota'] . " (max: " . $tMaxquota . "):"; ?></td>
      <td><input class="flat" type="text" name="fQuota" value="<?php print $tQuota; ?>" /></td>
      <td><?php print $pEdit_mailbox_quota_text; ?></td>
   </tr>
   <?php } ?>
   <tr>
      <td><?php print $PALANG['pCreate_mailbox_active'] . ":"; ?></td>
      <td><input class="flat" type="checkbox" name="fActive" <?php print (!empty ($tActive)) ? 'checked' : '' ?> /></td>
      <td>&nbsp;</td>
   </tr>
   <tr>
      <td colspan="3" class="hlp_center">
        <input class="button" type="submit" name="submit" value="<?php print $PALANG['pEdit_mailbox_button']; ?>" />
        <input class="button" type="submit" name="cancel" value="<?php print $PALANG['exit']; ?>" action="main.php" />
     </td>
   </tr>
   <tr>
      <td colspan="3" class="standout"><?php print $tMessage; ?></td>
   </tr>
</table>
</form>
</div>
