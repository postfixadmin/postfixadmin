<?php if( !defined('POSTFIXADMIN') ) die( "This file cannot be used standalone." ); ?>
<?php
if(isset($_SESSION['list_virtual_sticky_domain'])) {
    echo "<a href='list-virtual.php?domain=" . htmlentities($_SESSION['list_virtual_sticky_domain'], ENT_QUOTES) . "'>" . $PALANG['pAdminMenu_list_virtual'] . "</a>";
}
?>
<div id="edit_form">
<form name="mailbox" method="post">
<table>
   <tr>
      <td colspan="3"><h3><?php print $PALANG['pCreate_mailbox_welcome']; ?></h3></td>
   </tr>
   <tr>
      <td><?php print $PALANG['pCreate_mailbox_username'] . ":"; ?></td>
      <td><input class="flat" type="text" name="fUsername" value="<?php print $tUsername; ?>" autocomplete="off"/></td>
      <td>@
      <select name="fDomain">
      <?php
      for ($i = 0; $i < sizeof ($list_domains); $i++)
      {
         if ($tDomain == $list_domains[$i])
         {
            print "<option value=\"$list_domains[$i]\" selected>$list_domains[$i]</option>\n";
         }
         else
         {
            print "<option value=\"$list_domains[$i]\">$list_domains[$i]</option>\n";
         }
      }
      ?>
      </select>
      <?php print $pCreate_mailbox_username_text; ?>
      </td>
   </tr>
   <tr>
      <td><?php print $PALANG['pCreate_mailbox_password'] . ":"; ?></td>
      <td><input class="flat" type="password" name="fPassword" /></td>
      <td><?php print $pCreate_mailbox_password_text; ?></td>
   </tr>
   <tr>
      <td><?php print $PALANG['pCreate_mailbox_password2'] . ":"; ?></td>
      <td><input class="flat" type="password" name="fPassword2" /></td>
      <td>&nbsp;</td>
   </tr>
   <tr>
      <td><?php print $PALANG['pCreate_mailbox_name'] . ":"; ?></td>
      <td><input class="flat" type="text" name="fName" value="<?php print $tName; ?>" /></td>
      <td><?php print $pCreate_mailbox_name_text; ?></td>
   </tr>
   <?php if ($CONF['quota'] == 'YES') { ?>
   <tr>
      <td><?php print $PALANG['pCreate_mailbox_quota'] . ":"; ?></td>
      <td><input class="flat" type="text" name="fQuota" value="<?php print $tQuota; ?>" /></td>
      <td><?php print $pCreate_mailbox_quota_text; ?></td>
   </tr>
   <?php } ?>
   <tr>
      <td><?php print $PALANG['pCreate_mailbox_active'] . ":"; ?></td>
      <td><input class="flat" type="checkbox" name="fActive" checked /></td>
      <td>&nbsp;</td>
   </tr>
   <tr>
      <td><?php print $PALANG['pCreate_mailbox_mail'] . ":"; ?></td>
      <td><input class="flat" type="checkbox" name="fMail" checked /></td>
      <td>&nbsp;</td>
   </tr>
   <tr>
      <td colspan="3" class="hlp_center"><input class="button" type="submit" name="submit" value="<?php print $PALANG['pCreate_mailbox_button']; ?>" /></td>
   </tr>
   <tr>
      <td colspan="3" class="standout"><?php print $tMessage; ?></td>
   </tr>
</table>
</form>
</div>
