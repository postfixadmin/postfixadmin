<?php if( !defined('POSTFIXADMIN') ) die( "This file cannot be used standalone." ); ?>
<div id="edit_form">
<form name="broadcast-message" method="post">
<table>
   <tr>
      <td colspan="3"><h3><?php print $PALANG['pBroadcast_title']; ?></h3></td>
   </tr>
   <tr>
      <td><?php print $PALANG['pBroadcast_from'] . ":"; ?></td>
      <td><?php print $CONF['admin_email']; ?></td>
   </tr>
   <tr>
      <td><?php print $PALANG['pBroadcast_name'] . ':'; ?></td>
      <td><input class="flat" size="43" type="text" name="name"/></td>
   </tr>
   <tr>
      <td><?php print $PALANG['pBroadcast_subject'] . ":"; ?></td>
      <td><input class="flat" size="43" type="text" name="subject"/></td>
   </tr>
   <tr>
      <td><?php print $PALANG['pBroadcast_message'] . ":"; ?></td>
      <td><textarea class="flat" cols="40" rows="6" name="message"></textarea></td>
   </tr>
   <tr>
      <td colspan="3" class="hlp_center">
         <?php
         if($error == 1){
            echo '<br/><span class="error_msg">'.$PALANG['pBroadcast_error_empty'].'</span><br/><br/>' ;
         }
         ?>
         <input class="button" type="submit" name="submit" value="<?php print $PALANG['pBroadcast_send']; ?>" /></td>
   </tr>
</table>
</form>
</div>
