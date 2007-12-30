<?php if( !defined('POSTFIXADMIN') ) die( "This file cannot be used standalone." ); ?>
<div id="login">
<form name="login" method="post">
<table id="login_table" cellspacing="10">
   <tr>
      <td colspan="2"><h4><?php print $PALANG['pLogin_welcome']; ?></h4></td>
   </tr>
   <tr>
      <td><?php print $PALANG['pLogin_username'] . ":"; ?></td>
      <td><input class="flat" type="text" name="fUsername" value="<?php print $tUsername; ?>" /></td>
   </tr>
   <tr>
      <td><?php print $PALANG['pLogin_password'] . ":"; ?></td>
      <td><input class="flat" type="password" name="fPassword" /></td>
   </tr>
   <tr>
      <td colspan="2">
         <?php echo language_selector(); ?>
      </td>
   </tr>
   <tr>
      <td colspan="2" class="hlp_center"><input class="button" type="submit" name="submit" value="<?php print $PALANG['pLogin_button']; ?>" /></td>
   </tr>
   <tr>
      <td colspan="2" class="standout"><?php print $tMessage; ?></td>
   </tr>
   <tr>
      <td colspan="2"><a href="users/"><?php print $PALANG['pLogin_login_users']; ?></a></td>
   </tr>
</table>
</form>

<script tyle="text/javascript"><!--
	document.login.fUsername.focus();
// -->
</script>

</div>
<?php /* vim: set ft=php expandtab softtabstop=3 tabstop=3 shiftwidth=3: */ ?>
