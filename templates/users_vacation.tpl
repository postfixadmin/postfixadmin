<center>
<?php print $tMessage . "\n"; ?>
<table class="form">
   <tr>
      <td align="center" colspan="3">
         <?php print $PALANG['pUsersVacation_welcome'] . "\n"; ?>
         <br />
         <br />
      </td>
   </tr>
   <tr>
      <td>
         <form name="vacation" method="post">
         <?php print $PALANG['pUsersVacation_subject'] . ":\n"; ?>
      </td>
      <td>
         <input type="text" name="fSubject" value="<?php print $PALANG['pUsersVacation_subject_text']; ?>" />
      </td>
      <td>
         &nbsp;
      </td>
   </tr>
   <tr>
      <td>
         <?php print $PALANG['pUsersVacation_body'] . ":\n"; ?>
      </td>
      <td>
<textarea rows="10" cols="80" name="fBody">
<?php print $PALANG['pUsersVacation_body_text'] . "\n"; ?>
</textarea>
      </td>
      <td>
         &nbsp;
      </td>
   </tr>
   <tr>
      <td align="center" colspan="3">
         <input type="submit" name="fAway" value="<?php print $PALANG['pUsersVacation_button_away']; ?>" />
         </form>
      </td>
   </tr>
</table>
