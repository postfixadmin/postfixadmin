<center>
<?php print $tMessage; ?>
<table class="form">
   <tr>
      <td align="center" colspan="3">
         <?php print $PALANG['pAdminCreate_admin_welcome'] . "\n"; ?>
         <br />
         <br />
      </td>
   </tr>
   <tr>
      <td>
         <form name="alias" method="post">
         <?php print $PALANG['pAdminCreate_admin_username'] . ":\n"; ?>
      </td>
      <td>
         <input type="text" name="fUsername" value="<?php print $tUsername; ?>" />
      </td>
      <td>
         <?php print $pAdminCreate_admin_username_text . "\n"; ?>
      </td>
   </tr>
   <tr>
      <td>
         <?php print $PALANG['pAdminCreate_admin_password'] . ":\n"; ?>
      </td>
      <td>
         <input type="password" name="fPassword" value="<?php print $tPassword; ?>" />
      </td>
      <td>
         <?php print "$pAdminCreate_admin_password_text\n"; ?>
      </td>
   </tr>
   <tr>
      <td>
         <?php print $PALANG['pAdminCreate_admin_password2'] . ":\n"; ?>
      </td>
      <td>
         <input type="password" name="fPassword2" value="<?php print $tPassword2; ?>" />
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
         <input type="submit" name="submit" value="<?php print $PALANG['pAdminCreate_admin_button']; ?>" />
         </form>
      </td>
   </tr>
</table>
<p />
