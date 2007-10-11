<div id="menu">
<ul>
   <li><a target="_top" href="main.php"><?php print $PALANG['pMenu_main']; ?></a></li>
   <li><a target="_top" href="overview.php"><?php print $PALANG['pMenu_overview']; ?></a></li>
   <?php $url = "create-alias.php"; if (isset ($_GET['domain'])) $url .= "?domain=" . $_GET['domain']; ?>
   <li><a target="_top" href="<?php print $url; ?>"><?php print $PALANG['pMenu_create_alias']; ?></a></li>
   <?php $url = "create-mailbox.php"; if (isset ($_GET['domain'])) $url .= "?domain=" . $_GET['domain']; ?>
   <li><a target="_top" href="<?php print $url; ?>"><?php print $PALANG['pMenu_create_mailbox']; ?></a></li>
   <?php if ($CONF['fetchmail'] == "YES") { ?>
      <li><a target="_top" href="fetchmail.php"><?php print $PALANG['pMenu_fetchmail']; ?></a></li>
   <?php } ?>
   <?php if ($CONF['sendmail'] == 'YES') { ?><li><a target="_top" href="sendmail.php"><?php print $PALANG['pMenu_sendmail']; ?></a></li><?php } ?>
   <?php if ($CONF['vacation'] == "YES") { ?>
   <li><a target="_top" href="edit-vacation.php"><?php print $PALANG['pUsersMenu_vacation']; ?></a></li>
   <?php } ?>
   <li><a target="_top" href="password.php"><?php print $PALANG['pMenu_password']; ?></a></li>
   <li><a target="_top" href="viewlog.php"><?php print $PALANG['pMenu_viewlog']; ?></a></li>
   <li><a target="_top" href="logout.php"><?php print $PALANG['pMenu_logout']; ?></a></li>
</ul>
</div>

<?php
if (file_exists (realpath ("motd.txt")))
{
   print "<div id=\"motd\">\n";
   include ("motd.txt");
   print "</div>";
}
?>
