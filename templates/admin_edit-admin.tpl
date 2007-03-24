<center>
<?php print $tMessage; ?>
<table class="form">
   <tr>
      <td align="center" colspan="3">
         <?php print $LANG['pAdminEdit_admin_welcome'] . "\n"; ?>
         <br />
         <br />
      </td>
   </tr>
   <tr>
      <td>
         <form name="alias" method="post">
         <?php print $LANG['pAdminEdit_admin_username'] . ":\n"; ?>
      </td>
      <td>
         <?php print $username . "\n"; ?>
      </td>
      <td>
         &nbsp;
      </td>
   </tr>
   <tr>
      <td>
         <?php print $LANG['pAdminEdit_admin_password'] . ":\n"; ?>
      </td>
      <td>
         <input type="password" name="fPassword" value="<?php print $tPassword; ?>" />
      </td>
      <td>
         <?php print $pAdminEdit_admin_password_text . "\n"; ?>
      </td>
   </tr>
   <tr>
      <td>
         <?php print $LANG['pAdminEdit_admin_password2'] . ":\n"; ?>
      </td>
      <td>
         <input type="password" name="fPassword2" value="<?php print $tPassword2; ?>" />
      </td>
      <td>
         &nbsp;
      </td>
   </tr>
   <tr>
      <td>
         <?php print $LANG['pAdminEdit_admin_active'] . ":\n"; ?>
       </td>
      <td>
         <?php $checked = (!empty ($tActive)) ? 'checked' : ''; ?>
         <input type="checkbox" name="fActive" <?php print $checked; ?>>
      </td>
      <td>
         &nbsp;
      </td>
   </tr>
   <tr>
      <td colspan=3 align=center>
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
   </tr>
   <tr>
      <td align="center" colspan="3">
         <input type="submit" name="submit" value="<?php print $LANG['pAdminEdit_admin_button']; ?>" />
         </form>
      </td>
   </tr>
</table>
<p />
