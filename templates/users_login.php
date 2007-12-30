<?php if( !defined('POSTFIXADMIN') ) die( "This file cannot be used standalone." ); ?>
<div id="login">
<form name="login" method="post">
<table id="login_table" cellspacing="10">
   <tr>
      <td colspan="2"><h4><?php print $PALANG['pUsersLogin_welcome']; ?></h4></td>
   </tr>
   <tr>
      <td><?php print $PALANG['pUsersLogin_username'] . ":"; ?></td>
      <td><input class="flat" type="text" name="fUsername" value="<?php print $tUsername; ?>" /></td>
   </tr>
   <tr>
      <td><?php print $PALANG['pUsersLogin_password'] . ":"; ?></td>
      <td><input class="flat" type="password" name="fPassword" /></td>
   </tr>
   <tr>
      <td colspan="2">
         <?php echo language_selector(); ?>
      </td>
   </tr>
   <tr>
      <td colspan="2" class="hlp_center"><input class="button" type="submit" name="submit" value="<?php print $PALANG['pUsersLogin_button']; ?>" /></td>
   </tr>
   <tr>
      <td colspan="2" class="standout"><?php print $tMessage; ?></td>
   </tr>
</table>
</form>
</div>
