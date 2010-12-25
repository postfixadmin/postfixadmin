<?php if( !defined('POSTFIXADMIN') ) die( "This file cannot be used standalone." ); ?>

<div id="edit_form">
<form name="alias" method="post">
<table>
   <tr>
      <td colspan="3"><h3><?php print $PALANG['pEdit_alias_welcome']; ?></h3></td>
   </tr>
   <tr>
      <td><?php print $PALANG['pEdit_alias_address'] . ":"; ?></td>
      <td><?php print $fAddress; ?></td>
      <td>&nbsp;</td>
   </tr>
   <tr>
      <td><?php print $PALANG['pEdit_alias_goto'] . ":"; ?></td>
      <td>
<textarea class="flat" rows="10" cols="60" name="fGoto">
<?php print $tGoto; ?>
</textarea>
      </td>
      <td>&nbsp;</td>
   </tr>
   <tr>
      <td colspan="3" class="hlp_center"><input class="button" type="submit" name="submit" value="<?php print $PALANG['pEdit_alias_button']; ?>" /></td>
   </tr>
   <tr>
      <td colspan="3" class="standout"><?php print $tMessage; ?></td>
   </tr>
</table>
</form>
</div>
