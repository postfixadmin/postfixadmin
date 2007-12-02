<?php if( !defined('POSTFIXADMIN') ) die( "This file cannot be used standalone." ); ?>
<div id="edit_form">
<form name="alias" method="post">
<table>
   <tr>
      <td colspan="3"><h3><?php print $PALANG['pAdminEdit_admin_welcome']; ?></h3></td></tr>
   <tr>
      <td><?php print $PALANG['pAdminEdit_admin_username']; ?>:</td>
      <td><?php print $username; ?></td>
      <td>&nbsp;</td>
   </tr>
   <tr>
      <td><?php print $PALANG['pAdminEdit_admin_password']; ?>:</td>
      <td><input class="flat" type="password" autocomplete="off" name="fPassword" value=""/></td>
      <td><?php print $pAdminEdit_admin_password_text; ?></td>
   </tr>
   <tr>
      <td><?php print $PALANG['pAdminEdit_admin_password2']; ?>:</td>
      <td><input class="flat" type="password" name="fPassword2" value="" /></td>
      <td>&nbsp;</td>
   </tr>
   <tr>
      <td><?php print $PALANG['pAdminEdit_admin_active']; ?>:</td>
      <td><input class="flat" type="checkbox" name="fActive" <?php print (!empty ($tActive)) ? 'checked' : ''; ?> /></td>
      <td>&nbsp;</td>
   </tr>
   <tr>
      <td><?php print $PALANG['pAdminEdit_admin_super_admin']; ?>:</td>
      <td><input class="flat" type="checkbox" name="fSadmin" <?php print (!empty ($tSadmin)) ? 'checked' : ''; ?> /></td>
      <td>&nbsp;</td>
   </tr>
   <tr>
      <td colspan=3 align=center>
      <select name="fDomains[]" size="10" multiple="multiple">
      <?php
      foreach($tAllDomains as $domain) {
         // should escape $domain here to stop xss etc.
         $selected = '';
         if (in_array ($domain, $tDomains))  {
            $selected = "selected='selected'";
         }
         print "<option value='$domain' $selected>$domain</option>\n";
      }
      ?>
      </select>
      </td>
   </tr>
   <tr>
      <td colspan="3" class="hlp_center"><input class="button" type="submit" name="submit" value="<?php print $PALANG['pAdminEdit_admin_button']; ?>" /></td>
   </tr>
   <tr>
      <td colspan="3" class="standout"><?php print $tMessage; ?></td>
   </tr>
</table>
</form>
</div>
