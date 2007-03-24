<center>
<?php print $tMessage; ?>
<table class="form">
   <tr>
      <td align="center" colspan="3">
         <?php print $LANG['pEdit_alias_welcome'] . "\n"; ?>
         <br />
         <br />
      </td>
   </tr>
   <tr>
      <td>
         <form name="mailbox" method="post">
         <?php print $LANG['pEdit_alias_address'] . ":\n"; ?>
      </td>
      <td>
         <?php print $USERID_USERNAME; ?>
      </td>
      <td>
         &nbsp;
      </td>
   </tr>
   <tr>
      <td>
         <?php print $LANG['pEdit_alias_goto'] . ":\n"; ?>
      </td>
      <td>
<textarea rows="4" cols="23" name="fGoto">
<?php
$array = preg_split ('/,/', $tGoto);
for ($i = 0 ; $i < sizeof ($array) ; $i++)
{
   if (empty ($array[$i])) continue;
   if ($array[$i] == $USERID_USERNAME) continue;
   print "$array[$i]\n";
}
?>
</textarea>
      </td>
      <td>
         &nbsp;
      </td>
   </tr>
   <tr>
      <td align="center" colspan="3">
         <input type="submit" name="submit" value="<?php print $LANG['pEdit_alias_button']; ?>" />
         </form>
      </td>
   </tr>
</table>
