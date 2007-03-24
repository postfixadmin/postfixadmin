<center>
<?php print $tMessage . "\n"; ?>
<table class="form">
   <tr>
      <td align="center" colspan="3">
         <?php print $PALANG['pSendmail_welcome'] . "\n"; ?>
         <br />
         <br />
      </td>
   </tr>
   <tr>
      <td>
         <form name="mailbox" method="post">
         <?php print $PALANG['pSendmail_admin'] . ":\n"; ?>
      </td>
      <td>
         <?php print $SESSID_USERNAME . "\n"; ?>
      </td>
      <td>
         &nbsp;
      </td>
   </tr>
   <tr>
      <td>
         <?php print $PALANG['pSendmail_to'] . ":\n"; ?>
      </td>
      <td>
         <input type="text" name="fTo" />
      </td>
      <td>
         &nbsp;
      </td>
   </tr>
   <tr>
      <td>
         <?php print $PALANG['pSendmail_subject'] . ":\n"; ?>
      </td>
      <td>
         <input type="text" name="fSubject" value="<?php print $PALANG['pSendmail_subject_text']; ?>" />
      </td>
      <td>
         &nbsp;
      </td>
   </tr>

   <tr>
      <td>
         <?php print $PALANG['pSendmail_body'] . ":\n"; ?>
      </td>
      <td>
<textarea rows="20" cols="80" name="fBody">
<?php print $PALANG['pSendmail_body_text'] . "\n"; ?>
</textarea>
      </td>
      <td>
         &nbsp;
      </td>
   </tr>

   <tr>
      <td align="center" colspan="3">
         <input type="submit" name="submit" value="<?php print $PALANG['pSendmail_button']; ?>" />
         </form>
      </td>
   </tr>
</table>
