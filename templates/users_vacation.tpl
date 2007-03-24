<div id="edit_form">
<form name="vacation" method="post">
<table>
   <tr>
      <td colspan="3"><h3><?php print $PALANG['pUsersVacation_welcome']; ?></h3></td>
   </tr>
   <tr>
      <td><?php print $PALANG['pUsersVacation_subject'] . ":"; ?></td>
      <td><input type="text" name="fSubject" value="<?php print $PALANG['pUsersVacation_subject_text']; ?>" /></td>
      <td>&nbsp;</td>
   </tr>
   <tr>
      <td><?php print $PALANG['pUsersVacation_body'] . ":"; ?></td>
      <td>
<textarea rows="10" cols="80" name="fBody">
<?php print $PALANG['pUsersVacation_body_text']; ?>
</textarea>
      </td>
      <td>&nbsp;</td>
   </tr>
   <tr>
      <td colspan="3" class="hlp_center"><input class="button" type="submit" name="fAway" value="<?php print $PALANG['pUsersVacation_button_away']; ?>" /></td>
   </tr>
   <tr>
      <td colspan="3" class="standout"><?php print $tMessage; ?></td>
   </tr>
</table>
</form>
</div>