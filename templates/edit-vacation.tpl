<div id="edit_form">
<table>
   <tr>
      <td colspan="3"><h3><?php print $PALANG['pUsersVacation_welcome']; ?></h3></td>
   </tr>
   <tr>
      <td><?php print $PALANG['pUsersLogin_username'] . ":"; ?></td>
      <td><?php print $row['email']; ?></td>
      <td>&nbsp;</td>
   </tr>
   <tr>
      <td><?php print $PALANG['pUsersVacation_subject'] . ":"; ?></td>
      <td><textarea class="flat" cols="60" disabled="disabled"><?php print $row['subject']; ?></textarea></td>
      <td>&nbsp;</td>
   </tr>
   <tr>
      <td><?php print $PALANG['pUsersVacation_body'] . ":"; ?></td>
      <td><textarea class="flat" rows="10" cols="60" disabled="disabled"><?php print htmlentities($row['body'],ENT_QUOTES); ?></textarea></td>
      <td>&nbsp;</td>
   </tr>
   <tr>
      <td colspan="3" class="standout"><?php print $tMessage; ?></td>
   </tr>
</table>
</div>
