<?php if( !defined('POSTFIXADMIN') ) die( "This file cannot be used standalone." ); ?>
<div id="main_menu">
<table>
   <tr>
      <td nowrap><a target="_top" href="list-domain.php"><?php print $PALANG['pMenu_overview']; ?></a></td>
      <td><?php print $PALANG['pMain_overview']; ?></td>
   </tr>
   <tr>
      <td nowrap><a target="_top" href="create-alias.php"><?php print $PALANG['pMenu_create_alias']; ?></a></td>
      <td><?php print $PALANG['pMain_create_alias']; ?></td>
   </tr>
   <tr>
      <td nowrap><a target="_top" href="create-mailbox.php"><?php print $PALANG['pMenu_create_mailbox']; ?></a></td>
      <td><?php print $PALANG['pMain_create_mailbox']; ?></td>
   </tr>
<?php if ($CONF['sendmail'] == "YES") { ?>
   <tr>
      <td nowrap><a target="_top" href="sendmail.php"><?php print $PALANG['pMenu_sendmail']; ?></a></td>
      <td><?php print $PALANG['pMain_sendmail']; ?></td>
   </tr>
<?php } ?>
   <tr>
      <td nowrap><a target="_top" href="password.php"><?php print $PALANG['pMenu_password']; ?></a></td>
      <td><?php print $PALANG['pMain_password']; ?></td>
   </tr>
   <tr>
      <td nowrap><a target="_top" href="viewlog.php"><?php print $PALANG['pMenu_viewlog']; ?></a></td>
      <td><?php print $PALANG['pMain_viewlog']; ?></td>
   </tr>
   <tr>
      <td nowrap><a target="_top" href="logout.php"><?php print $PALANG['pMenu_logout']; ?></a></td>
      <td><?php print $PALANG['pMain_logout']; ?></td>
   </tr>
</table>
</div>
