<center>
<?php print $tMessage; ?>
<table class="form">
   <tr>
      <td align="center" colspan="3">
         <?php print $LANG['pUsersVacation_welcome'] . "\n"; ?>
         <br />
         <br />
      </td>
   </tr>
   <tr>
      <td>
         <form name="vacation" method="post">
         <?php print $LANG['pUsersVacation_subject'] . ":\n"; ?>
      </td>
      <td>
         <input type="text" name="fSubject" value="<?php print $LANG['pUsersVacation_subject_text']; ?>" />
      </td>
      <td>
         &nbsp;
      </td>
   </tr>
   <tr>
      <td>
         <?php print $LANG['pUsersVacation_body'] . ":\n"; ?>
      </td>
      <td>
<textarea rows="10" cols="80" name="fBody">
<?php print $LANG['pUsersVacation_body_text'] . "\n"; ?>
</textarea>
      </td>
      <td>
         &nbsp;
      </td>
   </tr>
   <tr>
      <td align="center" colspan="3">
         <input type="submit" name="fAway" value="<?php print $LANG['pUsersVacation_button_away']; ?>" />
         </form>
      </td>
   </tr>
</table>
