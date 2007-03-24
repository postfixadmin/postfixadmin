<div id="menu">
<ul>
   <li><a target="_top" href="list-admin.php"><?php print $PALANG['pAdminMenu_list_admin']; ?></a></li>
   <li><a target="_top" href="list-domain.php"><?php print $PALANG['pAdminMenu_list_domain']; ?></a></li>
   <li><a target="_top" href="list-virtual.php"><?php print $PALANG['pAdminMenu_list_virtual']; ?></a></li>
   <li><a target="_top" href="viewlog.php"><?php print $PALANG['pAdminMenu_viewlog']; ?></a></li>
<?php if ('pgsql'!=$CONF['database_type'] and $CONF['backup'] == 'YES') { ?>
   <li><a target="_top" href="backup.php"><?php print $PALANG['pAdminMenu_backup']; ?></a></li>
<?php } ?>
   <li><a target="_top" href="create-domain.php"><?php print $PALANG['pAdminMenu_create_domain']; ?></a></li>
   <li><a target="_top" href="create-admin.php"><?php print $PALANG['pAdminMenu_create_admin']; ?></a></li>
   <?php $url = "create-alias.php"; if (isset ($_GET['domain'])) $url .= "?domain=" . $_GET['domain']; ?>
   <li><a target="_top" href="<?php print $url; ?>"><?php print $PALANG['pAdminMenu_create_alias']; ?></a></li>
   <?php $url = "create-mailbox.php"; if (isset ($_GET['domain'])) $url .= "?domain=" . $_GET['domain']; ?>
   <li><a target="_top" href="<?php print $url; ?>"><?php print $PALANG['pAdminMenu_create_mailbox']; ?></a></li>
   <li><a target="_top" href="../logout.php"><?php print $PALANG['pMenu_logout']; ?></a></li>
</ul>
</div>

<?php
if (file_exists (realpath ("../motd-admin.txt")))
{
   print "<div id=\"motd\">\n";
   include ("../motd-admin.txt");
   print "</div>";
}
/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
