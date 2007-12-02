<?php if( !defined('POSTFIXADMIN') ) die( "This file cannot be used standalone." ); ?>
<div id="edit_form">
<form name="create_admin" method="post">
<table>
   <tr>
      <td colspan="3"><h3><?php print $PALANG['pAdminCreate_admin_welcome']; ?></h3></td>
   </tr>
   <tr>
      <td><?php print $PALANG['pAdminCreate_admin_username'] . ":"; ?></td>
      <td><input class="flat" type="text" name="fUsername" value="<?php print $tUsername; ?>" /></td>
      <td><?php print $pAdminCreate_admin_username_text; ?></td>
   </tr>
   <tr>
      <td><?php print $PALANG['pAdminCreate_admin_password'] . ":"; ?></td>
      <td><input class="flat" type="password" name="fPassword" /></td>
      <td><?php print $pAdminCreate_admin_password_text; ?></td>
   </tr>
   <tr>
      <td><?php print $PALANG['pAdminCreate_admin_password2'] . ":"; ?></td>
      <td><input class="flat" type="password" name="fPassword2" /></td>
      <td>&nbsp;</td>
   </tr>
   <tr>
      <td><?php print $PALANG['pAdminCreate_admin_address'] . ":"; ?></td>
      <td>
      <select name="fDomains[]" size="10" multiple="multiple">
      <?php
      for ($i = 0; $i < sizeof ($list_domains); $i++)
      {  
         if (in_array ($list_domains[$i], $tDomains))
         {
            print "<option value=\"" . $list_domains[$i] . "\" selected=\"selected\">" . $list_domains[$i] . "</option>\n";
         }
         else
         {
            print "<option value=\"" . $list_domains[$i] . "\">" . $list_domains[$i] . "</option>\n";
         }
      }
      ?>
      </select>
      </td>
      <td>&nbsp;</td>
   </tr>
   <tr>
      <td colspan="3" class="hlp_center"><input class="button" type="submit" name="submit" value="<?php print $PALANG['pAdminCreate_admin_button']; ?>" /></td>
   </tr>
   <tr>
      <td colspan="3" class="standout"><?php print $tMessage; ?></td>
   </tr>
</table>
</form>
</div>
